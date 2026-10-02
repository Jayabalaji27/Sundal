<?php

namespace App\Http\Controllers;

use App\Models\ProjectBudget;
use App\Models\ProjectExpense;
use App\Models\BudgetCategory;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Inertia\Inertia;

class BudgetDashboardController extends Controller
{
    use HasPermissionChecks;
    public function index()
    {
        $this->authorizePermission('budget_dashboard_view');
        
        $user = auth()->user();
        $workspace = $this->getCurrentWorkspace($user);
        
        // Get initial dashboard data
        $overviewData = $this->getOverviewData($workspace ? $workspace->id : null);
        
        return Inertia::render('budgets/Dashboard', [
            'initialData' => $overviewData,
            'permissions' => [
                'view_any' => $this->checkPermission('budget_view_any'),
                'create' => $this->checkPermission('budget_create'),
                'update' => $this->checkPermission('budget_update'),
                'delete' => $this->checkPermission('budget_delete'),
            ]
        ]);
    }

    public function overview()
    {
        $this->authorizePermission('budget_dashboard_view');
        
        $user = auth()->user();
        $workspace = $this->getCurrentWorkspace($user);
        
        $overviewData = $this->getOverviewData($workspace ? $workspace->id : null);
        
        return response()->json($overviewData);
    }
    
    private function getCurrentWorkspace($user)
    {
        try {
            // current_workspace_id on the User model is the single source of truth for
            // tenant context (see CLAUDE.md and DashboardController::getCurrentWorkspace).
            if ($user->currentWorkspace) {
                return $user->currentWorkspace;
            }

            return $user->workspaces()->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getOverviewData($workspaceId)
    {
        if (!$workspaceId) {
            return $this->getEmptyData();
        }
        
        try {
            // Get all budgets for workspace
            $budgets = ProjectBudget::with(['project', 'categories'])
                ->whereHas('project', function($q) use ($workspaceId) {
                    $q->where('workspace_id', $workspaceId);
                })
                ->get();

            // Calculate totals
            $totalBudget = $budgets->sum('total_budget');
            $totalSpent = $budgets->sum('total_spent');
            $averageUtilization = $budgets->count() > 0 ? $budgets->avg('utilization_percentage') : 0;

            // Get recent expenses
            $recentExpenses = ProjectExpense::with(['project', 'submitter', 'budgetCategory'])
                ->whereHas('project', function($q) use ($workspaceId) {
                    $q->where('workspace_id', $workspaceId);
                })
                ->latest()
                ->limit(10)
                ->get()
                ->map(function($expense) {
                    $submitter = $expense->submitter;
                    if ($submitter) {
                        $submitter->avatar = check_file($submitter->avatar)
                            ? get_file($submitter->avatar)
                            : get_file('avatars/avatar.png');
                    }
                    return $expense;
                });

            // Get pending approvals count
            $pendingApprovals = ProjectExpense::whereHas('project', function($q) use ($workspaceId) {
                $q->where('workspace_id', $workspaceId);
            })->where('status', 'pending')->count();

            // Get budget alerts (budgets over 75% utilization)
            $budgetAlerts = $budgets->filter(function($budget) {
                return $budget->utilization_percentage >= 75;
            })->map(function($budget) {
                return [
                    'project' => $budget->project->title,
                    'utilization' => round($budget->utilization_percentage, 1),
                    'remaining' => $budget->remaining_budget
                ];
            })->values();

            return [
                'summary' => [
                    'total_budget' => $totalBudget,
                    'total_spent' => $totalSpent,
                    'remaining_budget' => $totalBudget - $totalSpent,
                    'average_utilization' => round($averageUtilization, 1),
                    'active_budgets' => $budgets->where('status', 'active')->count(),
                    'pending_approvals' => $pendingApprovals
                ],
                'recent_expenses' => $recentExpenses,
                'budget_alerts' => $budgetAlerts,
                'top_categories' => $this->getTopCategories($workspaceId)
            ];
        } catch (\Exception $e) {
            return $this->getEmptyData();
        }
    }
    
    private function getEmptyData()
    {
        return [
            'summary' => [
                'total_budget' => 0,
                'total_spent' => 0,
                'remaining_budget' => 0,
                'average_utilization' => 0,
                'active_budgets' => 0,
                'pending_approvals' => 0
            ],
            'recent_expenses' => [],
            'budget_alerts' => [],
            'top_categories' => []
        ];
    }

    public function alerts()
    {
        $this->authorizePermission('budget_dashboard_view');
        
        $user = auth()->user();
        $workspace = $this->getCurrentWorkspace($user);
        
        if (!$workspace) {
            return response()->json(['alerts' => []]);
        }
        
        try {
            $budgets = ProjectBudget::with(['project', 'categories'])
                ->whereHas('project', function($q) use ($workspace) {
                    $q->where('workspace_id', $workspace->id);
                })
                ->get();

            $alerts = [];

            foreach ($budgets as $budget) {
                $utilization = $budget->utilization_percentage;
                
                // Budget level alerts
                if ($utilization >= 90) {
                    $alerts[] = [
                        'type' => 'budget_critical',
                        'level' => 'critical',
                        'message' => "Budget for {$budget->project->title} is {$utilization}% utilized",
                        'project' => $budget->project->title,
                        'utilization' => round($utilization, 1)
                    ];
                } elseif ($utilization >= 75) {
                    $alerts[] = [
                        'type' => 'budget_warning',
                        'level' => 'warning',
                        'message' => "Budget for {$budget->project->title} is {$utilization}% utilized",
                        'project' => $budget->project->title,
                        'utilization' => round($utilization, 1)
                    ];
                }

                // Category level alerts
                foreach ($budget->categories as $category) {
                    if (isset($category->utilization_percentage) && $category->utilization_percentage >= 90) {
                        $alerts[] = [
                            'type' => 'category_critical',
                            'level' => 'critical',
                            'message' => "{$category->name} category in {$budget->project->title} is {$category->utilization_percentage}% utilized",
                            'project' => $budget->project->title,
                            'category' => $category->name,
                            'utilization' => round($category->utilization_percentage, 1)
                        ];
                    }
                }
            }

            return response()->json(['alerts' => $alerts]);
        } catch (\Exception $e) {
            return response()->json(['alerts' => []]);
        }
    }

    public function trends(Request $request)
    {
        $this->authorizePermission('budget_dashboard_view');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        $months = $request->months ?? 6;
        $startDate = Carbon::now()->subMonths($months)->startOfMonth();
        
        $expenses = ProjectExpense::whereHas('project', function($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id);
        })
        ->where('status', 'approved')
        ->where('expense_date', '>=', $startDate)
        ->get();

        // Group by month
        $monthlyTrends = $expenses->groupBy(function($expense) {
            return Carbon::parse($expense->expense_date)->format('Y-m');
        })->map(function($monthExpenses, $month) {
            return [
                'month' => $month,
                'total_amount' => $monthExpenses->sum('amount'),
                'expense_count' => $monthExpenses->count(),
                'categories' => $monthExpenses->groupBy('budget_category_id')->map(function($categoryExpenses) {
                    $category = $categoryExpenses->first()->budgetCategory;
                    return [
                        'name' => $category ? $category->name : 'Uncategorized',
                        'amount' => $categoryExpenses->sum('amount'),
                        'count' => $categoryExpenses->count()
                    ];
                })->values()
            ];
        })->sortKeys();

        return response()->json(['trends' => $monthlyTrends]);
    }

    private function getTopCategories($workspaceId)
    {
        try {
            return BudgetCategory::whereHas('projectBudget.project', function($q) use ($workspaceId) {
                $q->where('workspace_id', $workspaceId);
            })
            ->withSum(['expenses' => function($q) {
                $q->where('status', 'approved');
            }], 'amount')
            ->having('expenses_sum_amount', '>', 0)
            ->orderBy('expenses_sum_amount', 'desc')
            ->limit(5)
            ->get()
            ->map(function($category) {
                return [
                    'name' => $category->name,
                    'total_spent' => $category->expenses_sum_amount ?? 0,
                    'allocated_amount' => $category->allocated_amount ?? 0,
                    'color' => $category->color ?? '#6B7280'
                ];
            });
        } catch (\Exception $e) {
            return collect([]);
        }
    }
}