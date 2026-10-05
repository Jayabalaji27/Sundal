<?php

namespace App\Http\Controllers;

use App\Services\PermissionService;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    use HasPermissionChecks;
    
    public function __construct(private PermissionService $permissionService)
    {
    }
    public function index()
    {
        $user = auth()->user();
        
        // Super admin gets their own dashboard
        if ($user->type === 'superadmin' || $user->type === 'super admin') {
            return $this->renderSuperAdminDashboard();
        }
        
        // Check if user has dashboard permission or is a company user
        if ($this->checkPermission('dashboard_view') || $user->type === 'company') {
            return $this->renderDashboard();
        }
        
        // Redirect to first available page
        return $this->redirectToFirstAvailablePage();
    }
    
    public function redirectToFirstAvailablePage()
    {
        $user = auth()->user();
        
        // Define available routes with their permissions
        $routes = [
            ['route' => 'projects.index', 'permission' => 'project_view_any'],
            ['route' => 'tasks.index', 'permission' => 'task_view_any'],
            ['route' => 'timesheets.index', 'permission' => 'timesheet_view_any'],
            ['route' => 'expenses.index', 'permission' => 'expense_view_any'],
            ['route' => 'budgets.index', 'permission' => 'budget_view_any'],
            ['route' => 'invoices.index', 'permission' => 'invoice_view_any'],
            ['route' => 'bugs.index', 'permission' => 'bug_view_any'],
            ['route' => 'workspaces.index', 'permission' => 'workspace_view_any'],
            ['route' => 'plans.index', 'permission' => 'plan_view_any'],
            ['route' => 'companies.index', 'permission' => 'company_view_any'],
            ['route' => 'users.index', 'permission' => 'user_view_any'],
        ];
        
        // Find first available route
        foreach ($routes as $routeData) {
            if ($this->checkPermission($routeData['permission'])) {
                return redirect()->route($routeData['route']);
            }
        }
        
        // If no permissions found, logout user
        auth()->logout();
        return redirect()->route('login')->with('error', __('No access permissions found.'));
    }
    
    private function renderDashboard()
    {
        try {
            $user = auth()->user();
            $workspace = $this->getCurrentWorkspace($user);
            $role = $this->getUserWorkspaceRole($user, $workspace);
            
            // Build cards based on workspace role and permissions
            $cards = [];
            
            // Only show user count for company workspace role (owner)
            if ($role === 'company' && $this->checkPermission('user_view_any')) {
                $cards[] = [
                    'title' => __('Total Users'),
                    'value' => $this->getTotalUsers($user, $workspace, $role),
                    'icon' => 'Users',
                    'link' => ($workspace && $this->checkPermission('team_view'))
                        ? route('team.index', $workspace->id)
                        : null,
                ];
            }

            // Show projects if user has permission
            if ($this->checkPermission('project_view_any')) {
                $cards[] = [
                    'title' => __('Active Projects'),
                    'value' => $this->getActiveProjects($workspace, $user, $role),
                    'icon' => 'Activity',
                    'link' => route('projects.index', ['status' => 'active']),
                ];
            }
            
            // Show tasks if user has permission. Clients don't have task_view_any (no
            // standalone Tasks list - see RoleSeeder), but they do get task_calendar_view,
            // so route them to the task calendar instead of the list they can't open.
            $canViewTaskList = $this->checkPermission('task_view_any');
            $canViewTaskCalendar = $this->checkPermission('task_calendar_view');
            if ($canViewTaskList || $canViewTaskCalendar) {
                $cards[] = [
                    'title' => __('Upcoming Deadlines'),
                    'value' => $this->getUpcomingDeadlines($workspace, $user, $role),
                    'icon' => 'Calendar',
                    'link' => $canViewTaskList
                        ? route('tasks.index', ['sort_field' => 'end_date', 'sort_direction' => 'asc', 'view' => 'list'])
                        : route('task-calendar.index'),
                ];
            }

            // Show revenue only for company workspace role with invoice permission
            if ($role === 'company' && $this->checkPermission('invoice_view_any')) {
                $cards[] = [
                    'title' => __('Total Received'),
                    'value' => $this->getRevenue($user, $workspace, $role),
                    'format' => 'currency',
                    'icon' => 'DollarSign',
                    'link' => route('invoices.index', ['status' => 'paid']),
                ];
            }
            
            $dashboardData = [
                'cards' => $cards,
                'projects' => $this->checkPermission('project_view_any') ? $this->getProjectStats($workspace, $user, $role) : null,
                'tasks' => $this->checkPermission('task_view_any') ? $this->getTaskStats($workspace, $user, $role) : null,
                'taskStages' => $this->checkPermission('task_view_any') ? $this->getTaskStages($workspace, $user, $role) : null,
                'myTasks' => ($role !== 'client' && $this->checkPermission('task_view_any')) ? $this->getMyTasks($workspace, $user) : null,
                'taskStageOptions' => $this->checkPermission('task_view_any') ? $this->getTaskStageOptions($workspace) : null,
                'projectProgress' => $role === 'client' ? $this->getClientProjectProgress($workspace, $user) : null,
                'budgets' => $this->checkPermission('budget_view_any') ? $this->getBudgetStats($workspace, $user, $role) : null,
                'expenses' => $this->checkPermission('expense_view_any') ? $this->getExpenseStats($workspace, $user, $role) : null,
                'invoices' => (($role === 'company' || $role === 'client') && $this->checkPermission('invoice_view_any')) ? $this->getInvoiceStats($workspace, $user, $role) : null,
                'recentActivities' => $role === 'client' ? [] : $this->getRecentActivities($workspace, $user, $role),
                'currentWorkspace' => $workspace
            ];

            return Inertia::render('dashboard', [
                'dashboardData' => $dashboardData,
                'userWorkspaceRole' => $role,
                'permissions' => []
            ]);
        } catch (\Exception $e) {
            // Log the error and return a basic response
            \Log::error('Dashboard Error: ' . $e->getMessage());
            
            return Inertia::render('dashboard', [
                'dashboardData' => [
                    'cards' => [],
                    'error' => 'Dashboard loading error'
                ],
                'permissions' => []
            ]);
        }
    }
    
    private function getCurrentWorkspace($user)
    {
        try {
            // current_workspace_id on the User model is the single source of truth for
            // tenant context (see CLAUDE.md). A stale session('current_workspace_id')
            // value here (e.g. from before workspace switching moved to that column)
            // could point Dashboard at a different workspace than the rest of the app
            // uses, producing an undercounted project total here vs. an accurate one
            // in ProjectController/PlanLimitService.
            if ($user->currentWorkspace) {
                return $user->currentWorkspace;
            }

            // Get first workspace user belongs to
            return $user->workspaces()->first();
        } catch (\Exception $e) {
            return null;
        }
    }
    
    private function getUserWorkspaceRole($user, $workspace)
    {
        try {
            if (!$workspace) return 'member';
            
            // Check if user is workspace owner
            if ($workspace->owner_id === $user->id) {
                return 'company';
            }
            
            $member = \App\Models\WorkspaceMember::where('user_id', $user->id)
                ->where('workspace_id', $workspace->id)
                ->first();
                
            return $member ? $member->role : 'member';
        } catch (\Exception $e) {
            return 'member';
        }
    }
    
    private function getTotalUsers($user, $workspace, $role)
    {
        try {
            // Only company workspace role sees user count
            if ($role !== 'company' || !$workspace) {
                return 0;
            }
            
            if (class_exists('\App\Models\WorkspaceMember')) {
                return \App\Models\WorkspaceMember::where('workspace_id', $workspace->id)
                    ->where('status', 'active')
                    ->count();
            }
            
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    private function getActiveProjects($workspace, $user, $role)
    {
        try {
            if (!class_exists('\App\Models\Project') || !$workspace) {
                return 0;
            }
            
            // Use the same visibility rule as the Projects page (Project::scopeVisibleTo):
            // owners see everything, everyone else sees projects they created, are a
            // member/client of, or that are marked workspace-wide visible. The old ad hoc
            // "members table only" check here missed workspace-visible projects, which is
            // why this card could read 0 even when the Projects page showed active projects.
            return \App\Models\Project::where('workspace_id', $workspace->id)
                ->where('status', 'active')
                ->visibleTo($user)
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    private function getUpcomingDeadlines($workspace, $user, $role)
    {
        try {
            if (!class_exists('\App\Models\Task') || !$workspace) {
                return 0;
            }

            // Task visibility follows the same project visibility rule used everywhere
            // else (Project::scopeVisibleTo) instead of an ad hoc members/clients-only
            // check — the old check missed tasks on workspace-visible or user-created
            // projects, the same undercount bug fixed for getActiveProjects() above.
            $query = \App\Models\Task::whereHas('project', function ($q) use ($workspace, $user) {
                $q->where('workspace_id', $workspace->id)->visibleTo($user);
            })
                ->open()
                ->whereNotNull('end_date')
                ->whereBetween('end_date', [now()->startOfDay(), now()->addDays(7)->endOfDay()]);

            // Members only see their own tasks (mirrors TaskController@index).
            // Clients only see tasks assigned to them or on projects they're a client of.
            if ($role === 'member') {
                $query->where(function ($q) use ($user) {
                    $q->where('assigned_to', $user->id)->orWhere('created_by', $user->id);
                });
            } elseif ($role === 'client') {
                $query->where(function ($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                        ->orWhereHas('project.clients', fn ($pm) => $pm->where('user_id', $user->id));
                });
            }

            return $query->count();
        } catch (\Exception $e) {
            return 0;
        }
    }
    

    
    private function getRevenue($user, $workspace, $role)
    {
        try {
            if ($role !== 'company' || !$workspace) {
                return 0;
            }
            
            if (class_exists('\App\Models\Invoice')) {
                return \App\Models\Invoice::whereHas('project', function($q) use ($workspace) {
                    $q->where('workspace_id', $workspace->id);
                })->where('status', 'paid')
                ->sum('total_amount') ?? 0;
            }
            
            return 0;
        } catch (\Exception $e) {
            \Log::error('getRevenue error: ' . $e->getMessage());
            return 0;
        }
    }
    
    private function getProjectStats($workspace, $user, $role)
    {
        try {
            if (!class_exists('\App\Models\Project') || !$workspace) {
                return ['total' => 0, 'active' => 0, 'completed' => 0, 'overdue' => 0];
            }
            
            // See getActiveProjects() above for why this uses the shared visibleTo scope
            // instead of an ad hoc members-table check.
            $baseQuery = \App\Models\Project::where('workspace_id', $workspace->id)->visibleTo($user);

            $total = (clone $baseQuery)->count();
            $active = (clone $baseQuery)->where('status', 'active')->count();
            $completed = (clone $baseQuery)->where('status', 'completed')->count();
            $overdue = (clone $baseQuery)->where('deadline', '<', now()->toDateString())
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count();
                
            return [
                'total' => $total,
                'active' => $active,
                'completed' => $completed,
                'overdue' => $overdue
            ];
        } catch (\Exception $e) {
            return ['total' => 0, 'active' => 0, 'completed' => 0, 'overdue' => 0];
        }
    }
    
    /**
     * Per-project progress list for the Client dashboard's "Project Progress"
     * widget — clients care about overall project completion, not individual
     * task/ticket-level detail, so this replaces the task-level "My Tasks" widget.
     */
    private function getClientProjectProgress($workspace, $user)
    {
        try {
            if (!class_exists('\App\Models\Project') || !$workspace) {
                return [];
            }

            return \App\Models\Project::where('workspace_id', $workspace->id)
                ->whereHas('clients', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->orderBy('created_at', 'desc')
                ->limit(8)
                ->get(['id', 'title', 'status', 'deadline', 'progress'])
                ->map(function ($project) {
                    return [
                        'id' => $project->id,
                        'title' => $project->title,
                        'status' => $project->status,
                        'deadline' => $project->deadline,
                        'is_overdue' => $project->deadline && $project->deadline->isPast() && $project->status !== 'completed',
                        'progress' => $project->calculateProgress(),
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getTaskStats($workspace, $user, $role)
    {
        try {
            if (!class_exists('\App\Models\Task') || !$workspace) {
                return ['total' => 0, 'pending' => 0, 'inProgress' => 0, 'completed' => 0];
            }
            
            // Same visibility as the Tasks page, so the two never disagree
            $total = \App\Models\Task::visibleTo($user)->count();

            $stages = \App\Models\TaskStage::where('workspace_id', $workspace->id)
                ->withCount(['tasks' => fn ($q) => $q->visibleTo($user)])
                ->get();
            
            // "Completed" = tasks in a completed stage (TaskStage::is_completed, e.g.
            // Done) - not whichever stage happens to be third. Pending = the first
            // open stage; everything else open is in progress.
            $stages = $stages->sortBy('order')->values();
            $completed = $stages->where('is_completed', true)->sum('tasks_count');
            $openStages = $stages->where('is_completed', false)->values();
            $pending = $openStages->first() ? $openStages->first()->tasks_count : 0;
            $inProgress = $openStages->skip(1)->sum('tasks_count');
            
            return [
                'total' => $total,
                'pending' => $pending,
                'inProgress' => $inProgress,
                'completed' => $completed
            ];
        } catch (\Exception $e) {
            return ['total' => 0, 'pending' => 0, 'inProgress' => 0, 'completed' => 0];
        }
    }
    
    private function getTaskStages($workspace, $user, $role)
    {
        try {
            if (!class_exists('\App\Models\TaskStage') || !$workspace) {
                return [];
            }
            
            $stages = \App\Models\TaskStage::where('workspace_id', $workspace->id)
                ->withCount(['tasks' => function($q) use ($workspace, $user, $role) {
                    $q->whereHas('project', function($pq) use ($workspace) {
                        $pq->where('workspace_id', $workspace->id);
                    });
                    if ($role !== 'company') {
                        $q->where('assigned_to', $user->id);
                    }
                }])->get();
            
            return $stages->map(function($stage) {
                return [
                    'name' => $stage->name,
                    'color' => $stage->color,
                    'count' => $stage->tasks_count
                ];
            })->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Tasks assigned to the current user, for the "My Tasks" quick-update
     * widget on the dashboard — lets members update a task's stage without
     * navigating into the project.
     */
    private function getMyTasks($workspace, $user)
    {
        try {
            if (!class_exists('\App\Models\Task') || !$workspace) {
                return [];
            }

            return \App\Models\Task::whereHas('project', function ($q) use ($workspace, $user) {
                    $q->where('workspace_id', $workspace->id)->visibleTo($user);
                })
                ->where('assigned_to', $user->id)
                ->open()
                ->with(['project:id,title', 'taskStage:id,name,color,is_completed'])
                ->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
                ->orderByRaw('end_date IS NULL, end_date asc')
                ->limit(8)
                ->get(['id', 'project_id', 'task_stage_id', 'title', 'priority', 'end_date', 'progress'])
                ->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'priority' => $task->priority,
                        'end_date' => $task->end_date,
                        'is_overdue' => $task->isOverdue(),
                        'project' => $task->project ? ['id' => $task->project->id, 'title' => $task->project->title] : null,
                        'task_stage_id' => $task->task_stage_id,
                        'stage_name' => $task->taskStage->name ?? null,
                        'stage_color' => $task->taskStage->color ?? null,
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getTaskStageOptions($workspace)
    {
        try {
            if (!class_exists('\App\Models\TaskStage') || !$workspace) {
                return [];
            }

            return \App\Models\TaskStage::where('workspace_id', $workspace->id)
                ->orderBy('order')
                ->get(['id', 'name', 'color'])
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getBudgetStats($workspace, $user, $role)
    {
        try {
            if (!class_exists('\App\Models\ProjectBudget') || !class_exists('\App\Models\ProjectExpense') || !$workspace) {
                return ['totalBudget' => 0, 'spent' => 0, 'remaining' => 0, 'utilization' => 0];
            }
            
            $budgetQuery = \App\Models\ProjectBudget::whereHas('project', function($q) use ($workspace, $user, $role) {
                $q->where('workspace_id', $workspace->id);
                if ($role === 'client') {
                    $q->whereHas('clients', function($m) use ($user) {
                        $m->where('user_id', $user->id);
                    });
                } elseif ($role !== 'company') {
                    $q->whereHas('members', function($m) use ($user) {
                        $m->where('user_id', $user->id);
                    });
                }
            });
            
            // Spent = each budget's own approved expenses inside its period (see
            // ProjectBudget::spentExpenses), so it matches the Budgets page.
            $budgets = $budgetQuery->get();
            $totalBudget = $budgets->sum('total_budget') ?? 0;
            $spent = $budgets->sum(fn ($budget) => (float) $budget->total_spent);
            $remaining = $totalBudget - $spent;
            $utilization = $totalBudget > 0 ? ($spent / $totalBudget) * 100 : 0;
            
            return [
                'totalBudget' => (int)$totalBudget,
                'spent' => (int)$spent,
                'remaining' => (int)$remaining,
                'utilization' => round($utilization, 1)
            ];
        } catch (\Exception $e) {
            return ['totalBudget' => 0, 'spent' => 0, 'remaining' => 0, 'utilization' => 0];
        }
    }
    
    private function getInvoiceStats($workspace, $user, $role)
    {
        try {
            if (!class_exists('\App\Models\Invoice') || !$workspace) {
                return ['total' => 0, 'paid' => 0, 'pending' => 0, 'overdue' => 0];
            }
            
            // Same role scope as the /invoices list (a client only sees sent invoices
            // addressed to them, not every invoice on their projects).
            $baseQuery = \App\Models\Invoice::where('workspace_id', $workspace->id)
                ->where('status', '!=', 'cancelled')
                ->visibleToRole($user, $role === 'company' ? 'owner' : $role);

            $total = (clone $baseQuery)->count();
            $paid = (clone $baseQuery)->where('status', 'paid')->count();
            $pending = (clone $baseQuery)->whereIn('status', ['draft', 'sent', 'viewed', 'partial_paid'])->notOverdue()->count();
            $overdue = (clone $baseQuery)->overdue()->count();
            
            return [
                'total' => $total,
                'paid' => $paid,
                'pending' => $pending,
                'overdue' => $overdue
            ];
        } catch (\Exception $e) {
            return ['total' => 0, 'paid' => 0, 'pending' => 0, 'overdue' => 0];
        }
    }
    
    private function getExpenseStats($workspace, $user, $role)
    {
        try {
            if (!class_exists('\App\Models\ProjectExpense') || !$workspace) {
                return ['pending' => 0, 'approved' => 0, 'total' => 0];
            }
            
            $baseQuery = \App\Models\ProjectExpense::whereHas('project', function($q) use ($workspace, $user, $role) {
                $q->where('workspace_id', $workspace->id);
                if ($role === 'client') {
                    $q->whereHas('clients', function($m) use ($user) {
                        $m->where('user_id', $user->id);
                    });
                } elseif ($role !== 'company') {
                    $q->whereHas('members', function($m) use ($user) {
                        $m->where('user_id', $user->id);
                    });
                }
            });

            // Members only see their own expenses on the Expenses page, so count only those
            if ($role === 'member') {
                $baseQuery->where('submitted_by', $user->id);
            }

            $total = (clone $baseQuery)->count();
            $pending = (clone $baseQuery)->where('status', 'pending')->count();
            $approved = (clone $baseQuery)->where('status', 'approved')->count();
            
            return [
                'total' => $total,
                'pending' => $pending,
                'approved' => $approved
            ];
        } catch (\Exception $e) {
            return ['pending' => 0, 'approved' => 0, 'total' => 0];
        }
    }
    
    private function getRecentActivities($workspace, $user, $role)
    {
        try {
            if (!$workspace) {
                return config('app.demo_mode', false) ? $this->getDefaultActivities() : [];
            }
            
            $query = \App\Models\ProjectActivity::whereHas('project', function($q) use ($workspace, $user, $role) {
                $q->where('workspace_id', $workspace->id);
                // Non-company workspace roles only see activities from their projects
                if ($role === 'client') {
                    $q->whereHas('clients', function($m) use ($user) {
                        $m->where('user_id', $user->id);
                    });
                } elseif ($role !== 'company') {
                    $q->whereHas('members', function($m) use ($user) {
                        $m->where('user_id', $user->id);
                    });
                }
            });
            
            // Non-company workspace roles only see their own activities
            if ($role !== 'company') {
                $query->where('user_id', $user->id);
            }
            
            $activities = $query->with('user')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function($activity) {
                    return [
                        'id' => $activity->id,
                        'type' => $activity->type ?? 'activity',
                        'description' => $activity->description,
                        'user' => $activity->user->name ?? 'Unknown User',
                        'time' => $activity->created_at->diffForHumans(),
                        'avatar' => check_file($activity->user->avatar) ? get_file($activity->user->avatar) : get_file('avatars/avatar.png'),

                    ];
                });
            
            // If no activities found, return default activities only in demo mode
            if ($activities->isEmpty()) {
                return config('app.demo_mode', false) ? $this->getDefaultActivities() : [];
            }
            
            return $activities->toArray();
        } catch (\Exception $e) {
            return config('app.demo_mode', false) ? $this->getDefaultActivities() : [];
        }
    }
    
    private function getDefaultActivities()
    {
        return [
            [
                'id' => 1, 
                'type' => 'task', 
                'description' => 'Task "API Integration for Payment Gateway" completed successfully', 
                'user' => 'John Doe', 
                'time' => '2 hours ago'
            ],
            [
                'id' => 2, 
                'type' => 'project', 
                'description' => 'New project "E-commerce Mobile App" created with initial setup', 
                'user' => 'Jane Smith', 
                'time' => '3 hours ago'
            ],
            [
                'id' => 3, 
                'type' => 'expense', 
                'description' => 'Travel expense of $450 approved for client meeting', 
                'user' => 'Mike Johnson', 
                'time' => '4 hours ago'
            ],
            [
                'id' => 4, 
                'type' => 'bug', 
                'description' => 'Critical security bug fixed in user authentication system', 
                'user' => 'Sarah Wilson', 
                'time' => '5 hours ago'
            ],
            [
                'id' => 5, 
                'type' => 'invoice', 
                'description' => 'Invoice #INV-2024-001 sent to client for $2,500', 
                'user' => 'David Brown', 
                'time' => '6 hours ago'
            ],
            [
                'id' => 6, 
                'type' => 'timesheet', 
                'description' => 'Weekly timesheet submitted for approval (40 hours)', 
                'user' => 'Emily Davis', 
                'time' => '8 hours ago'
            ],
            [
                'id' => 7, 
                'type' => 'project', 
                'description' => 'Project "Website Redesign" milestone completed ahead of schedule', 
                'user' => 'Alex Chen', 
                'time' => '10 hours ago'
            ],
            [
                'id' => 8, 
                'type' => 'task', 
                'description' => 'Database optimization task assigned to development team', 
                'user' => 'Lisa Wang', 
                'time' => '12 hours ago'
            ],
            [
                'id' => 9, 
                'type' => 'expense', 
                'description' => 'Software license renewal expense of $199 submitted', 
                'user' => 'Tom Anderson', 
                'time' => '1 day ago'
            ],
            [
                'id' => 10, 
                'type' => 'bug', 
                'description' => 'UI responsiveness issue reported on mobile devices', 
                'user' => 'Rachel Green', 
                'time' => '1 day ago'
            ]
        ];
    }
    
    private function renderSuperAdminDashboard()
    {
        try {
            // Get actual data for super admin
            $totalCompanies = $this->getTotalCompanies();
            $totalPlans = $this->getTotalPlans();
            $totalOrders = $this->getTotalOrders();
            $totalRevenue = $this->getTotalRevenue();
            
            $companies = $this->getCompanyStats();
            $plans = $this->getPlanStats();
            $planOrders = $this->getPlanOrderStats();
            $planRequests = $this->getPlanRequestStats();
            $coupons = $this->getCouponStats();
            $revenue = $this->getRevenueStats();
            $mostBoughtPlan = $this->getMostBoughtPlan();
            $mostUsedCoupon = $this->getMostUsedCoupon();
            $recentActivities = $this->getSuperAdminRecentActivities();
            
            $dashboardData = [
                'cards' => [
                    [
                        'title' => __('Total Companies'),
                        'value' => $companies['total'] ?? $totalCompanies,
                        'icon' => 'Building2',
                    ],
                    [
                        'title' => __('Total Plans'),
                        'value' => $plans['total'] ?? $totalPlans,
                        'icon' => 'Package',
                    ],
                    [
                        'title' => __('Total Orders'),
                        'value' => $planOrders['total'] ?? $totalOrders,
                        'icon' => 'ShoppingCart',
                    ],
                    [
                        'title' => __('Total Revenue'),
                        'value' => $totalRevenue,
                        'format' => 'currency',
                        'icon' => 'DollarSign',
                    ]
                ],
                'companies' => $companies,
                'plans' => $plans,
                'planOrders' => $planOrders,
                'planRequests' => $planRequests,
                'coupons' => $coupons,
                'revenue' => $revenue,
                'mostBoughtPlan' => $mostBoughtPlan,
                'mostUsedCoupon' => $mostUsedCoupon,
                'recentActivities' => $recentActivities,
                'recentCompanies' => $this->getRecentCompanies()
            ];

            return Inertia::render('dashboard', [
                'dashboardData' => $dashboardData,
                'isSuperAdmin' => true,
                'permissions' => []
            ]);
        } catch (\Exception $e) {
            // Log the error and return a basic response
            \Log::error('Super Admin Dashboard Error: ' . $e->getMessage());
            
            return Inertia::render('dashboard', [
                'dashboardData' => [
                    'cards' => [],
                    'error' => 'Dashboard loading error'
                ],
                'isSuperAdmin' => true,
                'permissions' => []
            ]);
        }
    }
    
    private function getTotalCompanies()
    {
        try {
            // 'users.type' defaults to 'company' for every non-superadmin user (owners,
            // managers, members, clients alike) — filter by the Spatie 'company' role,
            // which is what actually marks a workspace-owning tenant.
            return \App\Models\User::whereHas('roles', fn ($q) => $q->where('name', 'company'))->count();
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    private function getTotalPlans()
    {
        try {
            if (class_exists('\App\Models\Plan')) {
                return \App\Models\Plan::count();
            }
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    private function getTotalOrders()
    {
        try {
            if (class_exists('\App\Models\PlanOrder')) {
                return \App\Models\PlanOrder::count();
            }
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    private function getTotalRevenue()
    {
        try {
            if (class_exists('\App\Models\PlanOrder')) {
                return \App\Models\PlanOrder::whereIn('status', ['approved', 'completed', 'paid'])
                    ->sum('final_price') ?? 0;
            }
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    private function getCompanyStats()
    {
        try {
            // Same rule as the Companies list: active = login enabled AND plan not expired.
            $total = \App\Models\User::whereHas('roles', fn ($q) => $q->where('name', 'company'))->count();
            $active = \App\Models\User::whereHas('roles', fn ($q) => $q->where('name', 'company'))
                ->where(fn ($q) => $q->where('status', 'active')->orWhereNull('status'))
                ->where(fn ($q) => $q->whereNull('plan_expire_date')
                    ->orWhere('plan_expire_date', '>=', now()))
                ->count();
            $inactive = $total - $active;
            
            return [
                'total' => $total,
                'active' => $active,
                'inactive' => $inactive
            ];
        } catch (\Exception $e) {
            return ['total' => 0, 'active' => 0, 'inactive' => 0];
        }
    }
    
    private function getPlanStats()
    {
        try {
            if (class_exists('\App\Models\Plan')) {
                $total = \App\Models\Plan::count();
                $active = \App\Models\Plan::where('is_plan_enable', 'on')->count();
                $inactive = $total - $active;
                
                return [
                    'total' => $total,
                    'active' => $active,
                    'inactive' => $inactive
                ];
            }
            return ['total' => 0, 'active' => 0, 'inactive' => 0];
        } catch (\Exception $e) {
            return ['total' => 0, 'active' => 0, 'inactive' => 0];
        }
    }
    
    private function getPlanOrderStats()
    {
        try {
            if (class_exists('\App\Models\PlanOrder')) {
                $total = \App\Models\PlanOrder::count();
                $pending = \App\Models\PlanOrder::where('status', 'pending')->count();
                $approved = \App\Models\PlanOrder::where('status', 'approved')->count();
                $rejected = \App\Models\PlanOrder::where('status', 'rejected')->count();
                
                return [
                    'total' => $total,
                    'pending' => $pending,
                    'approved' => $approved,
                    'rejected' => $rejected
                ];
            }
            return ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
        } catch (\Exception $e) {
            return ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
        }
    }
    
    private function getPlanRequestStats()
    {
        try {
            if (class_exists('\App\Models\PlanRequest')) {
                $total = \App\Models\PlanRequest::count();
                $pending = \App\Models\PlanRequest::where('status', 'pending')->count();
                $approved = \App\Models\PlanRequest::where('status', 'approved')->count();
                $rejected = \App\Models\PlanRequest::where('status', 'rejected')->count();
                
                return [
                    'total' => $total,
                    'pending' => $pending,
                    'approved' => $approved,
                    'rejected' => $rejected
                ];
            }
            return ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
        } catch (\Exception $e) {
            return ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
        }
    }
    
    private function getCouponStats()
    {
        try {
            if (class_exists('\App\Models\Coupon')) {
                $total = \App\Models\Coupon::count();
                $active = \App\Models\Coupon::where('status', true)->count();
                $expired = $total - $active;
                
                return [
                    'total' => $total,
                    'active' => $active,
                    'expired' => $expired
                ];
            }
            return ['total' => 0, 'active' => 0, 'expired' => 0];
        } catch (\Exception $e) {
            return ['total' => 0, 'active' => 0, 'expired' => 0];
        }
    }
    
    private function getRevenueStats()
    {
        try {
            if (class_exists('\App\Models\PlanOrder')) {
                $total = \App\Models\PlanOrder::whereIn('status', ['approved', 'completed', 'paid'])
                    ->sum('final_price') ?? 0;
                $monthly = \App\Models\PlanOrder::whereIn('status', ['approved', 'completed', 'paid'])
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->sum('final_price') ?? 0;
                
                return [
                    'total' => $total,
                    'monthly' => $monthly
                ];
            }
            return ['total' => 0, 'monthly' => 0];
        } catch (\Exception $e) {
            return ['total' => 0, 'monthly' => 0];
        }
    }
    

    
    private function getSuperAdminRecentActivities()
    {
        try {
            $activities = collect();
            
            // Get recent plan orders
            if (class_exists('\App\Models\PlanOrder')) {
                $planOrders = \App\Models\PlanOrder::with('user', 'plan')->latest()->take(5)->get();
                foreach ($planOrders as $order) {
                    $activities->push([
                        'id' => $order->id,
                        'type' => 'plan_order',
                        'description' => "Plan order for {$order->plan->name}",
                        'user' => $order->user->name,
                        'avatar' => check_file($order->user->avatar) ? get_file($order->user->avatar) : get_file('avatars/avatar.png'),
                        'time' => $order->created_at->diffForHumans(),
                        'status' => $order->status
                    ]);
                }
            }
            
            // Get recent plan requests
            if (class_exists('\App\Models\PlanRequest')) {
                $planRequests = \App\Models\PlanRequest::with('user', 'plan')->latest()->take(5)->get();
                foreach ($planRequests as $request) {
                    $activities->push([
                        'id' => $request->id,
                        'type' => 'plan_request',
                        'description' => "Plan request for {$request->plan->name}",
                        'user' => $request->user->name,
                        'avatar' => check_file($request->user->avatar) ? get_file($request->user->avatar) : get_file('avatars/avatar.png'),
                        'time' => $request->created_at->diffForHumans(),
                        'status' => $request->status
                    ]);
                }
            }
            
            // Get recent company registrations
            $companies = \App\Models\User::whereHas('roles', fn ($q) => $q->where('name', 'company'))->latest()->take(3)->get();
            foreach ($companies as $user) {
                $activities->push([
                    'id' => $user->id,
                    'type' => 'company_registration',
                    'description' => "New company registered",
                    'user' => $user->name,
                    'avatar' => check_file($user->avatar) ? get_file($user->avatar) : get_file('avatars/avatar.png'),
                    'time' => $user->created_at->diffForHumans(),
                    'status' => 'active'
                ]);
            }
            
            return $activities->sortByDesc('time')->take(10)->values()->toArray();
        } catch (\Exception $e) {
            return config('app.demo_mode', false) ? $this->getDefaultActivities() : [];
        }
    }
    
    private function getMostBoughtPlan()
    {
        try {
            if (!class_exists('\App\Models\PlanOrder') || !class_exists('\App\Models\Plan')) {
                return null;
            }
            
            $planOrder = \App\Models\PlanOrder::select('plan_id', \DB::raw('COUNT(*) as order_count'))
                ->where('status', 'approved')
                ->groupBy('plan_id')
                ->orderBy('order_count', 'desc')
                ->with('plan')
                ->first();
                
            return $planOrder ? [
                'name' => $planOrder->plan->name ?? 'Unknown Plan',
                'count' => $planOrder->order_count
            ] : null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    private function getMostUsedCoupon()
    {
        try {
            if (!class_exists('\App\Models\PlanOrder') || !class_exists('\App\Models\Coupon')) {
                return null;
            }
            
            $couponOrder = \App\Models\PlanOrder::select('coupon_id', \DB::raw('COUNT(*) as usage_count'))
                ->whereNotNull('coupon_id')
                ->groupBy('coupon_id')
                ->orderBy('usage_count', 'desc')
                ->with('coupon')
                ->first();
                
            return $couponOrder ? [
                'name' => $couponOrder->coupon->name ?? 'Unknown Coupon',
                'code' => $couponOrder->coupon->code ?? '',
                'count' => $couponOrder->usage_count
            ] : null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    private function getRecentCompanies()
    {
        try {
            $companies = \App\Models\User::whereHas('roles', fn ($q) => $q->where('name', 'company'))
                ->latest()
                ->take(5)
                ->get()
                ->map(function($company) {
                    $plan = null;
                    if (class_exists('\App\Models\PlanOrder')) {
                        $latestOrder = \App\Models\PlanOrder::where('user_id', $company->id)
                            ->where('status', 'approved')
                            ->with('plan')
                            ->latest()
                            ->first();
                        $plan = $latestOrder ? $latestOrder->plan->name : null;
                    }
                    
                    return [
                        'id' => $company->id,
                        'name' => $company->name,
                        'email' => $company->email,
                        'plan' => $plan,
                        'registered_at' => $company->created_at->diffForHumans()
                    ];
                });
            
            return $companies->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }
}