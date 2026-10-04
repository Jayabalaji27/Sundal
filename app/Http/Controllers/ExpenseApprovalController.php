<?php

namespace App\Http\Controllers;

use App\Models\ExpenseApproval;
use App\Models\ProjectExpense;
use App\Services\BudgetService;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ExpenseApprovalController extends Controller
{
    use HasPermissionChecks;
    
    protected BudgetService $budgetService;
    
    public function __construct(BudgetService $budgetService)
    {
        $this->budgetService = $budgetService;
    }
    public function approve(Request $request, ProjectExpense $expense)
    {
        $this->authorizePermission('expense_approval_approve');
        
        $validated = $request->validate([
            'notes' => 'nullable|string'
        ]);

        DB::transaction(function () use ($expense, $validated) {
            // Create or update approval record
            ExpenseApproval::updateOrCreate(
                [
                    'project_expense_id' => $expense->id,
                    'approver_id' => auth()->id()
                ],
                [
                    'status' => 'approved',
                    'notes' => $validated['notes'],
                    'approved_at' => now(),
                    'approval_level' => 1
                ]
            );

            // Update expense status
            $expense->update(['status' => 'approved']);

            // Update budget after approval
            $this->budgetService->updateBudgetAfterApproval($expense);
            // Fire event for Slack notification
            if (!config('app.is_demo', true)) {
                event(new \App\Events\ExpenseApprovalRequested($expense));
            }
        });

        return back()->with('success', __('Expense approved and budget updated successfully!'));
    }

    public function reject(Request $request, ProjectExpense $expense)
    {
        $this->authorizePermission('expense_approval_reject');
        
        // The submitter needs to know what to fix, as with timesheet rejection.
        // Validated outside the try so a missing reason is a field error, not
        // the generic "Failed to reject" below.
        $validated = $request->validate([
            'notes' => 'required|string|max:1000'
        ]);

        try {

            DB::transaction(function () use ($expense, $validated) {
                // Create or update approval record
                ExpenseApproval::updateOrCreate(
                    [
                        'project_expense_id' => $expense->id,
                        'approver_id' => auth()->id()
                    ],
                    [
                        'status' => 'rejected',
                        'notes' => $validated['notes'],
                        'approved_at' => now(),
                        'approval_level' => 1
                    ]
                );

                // Update expense status
                $expense->update(['status' => 'rejected']);
            });

            return back()->with('success', __('Expense rejected successfully!'));
        } catch (\Exception $e) {
            \Log::error('Failed to reject expense: ' . $e->getMessage(), [
                'expense_id' => $expense->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            
            return back()->with('error', __('Failed to reject expense. Please try again.'));
        }
    }

    public function requestInfo(Request $request, ProjectExpense $expense)
    {
        $this->authorizePermission('expense_approval_request_info');
        
        $validated = $request->validate([
            'notes' => 'required|string'
        ]);

        // Create or update approval record
        $approval = ExpenseApproval::updateOrCreate(
            [
                'project_expense_id' => $expense->id,
                'approver_id' => auth()->id()
            ],
            [
                'status' => 'requires_info',
                'notes' => $validated['notes'],
                'approved_at' => now(),
                'approval_level' => 1
            ]
        );

        // Update expense status
        $expense->update(['status' => 'requires_info']);

        return back()->with('success', __('Additional information requested successfully!'));
    }

    public function bulkApprove(Request $request)
    {
        $this->authorizePermission('expense_approval_approve');
        
        $validated = $request->validate([
            'expense_ids' => 'required|array',
            'expense_ids.*' => 'exists:project_expenses,id',
            'notes' => 'nullable|string'
        ]);

        $expenses = ProjectExpense::whereIn('id', $validated['expense_ids'])
            ->where('status', 'pending')
            ->get();

        DB::transaction(function () use ($expenses, $validated) {
            foreach ($expenses as $expense) {
                // Create approval record
                ExpenseApproval::create([
                    'project_expense_id' => $expense->id,
                    'approver_id' => auth()->id(),
                    'status' => 'approved',
                    'notes' => $validated['notes'],
                    'approved_at' => now(),
                    'approval_level' => 1
                ]);

                // Update expense status
                $expense->update(['status' => 'approved']);

                // Update budget after approval
                $this->budgetService->updateBudgetAfterApproval($expense);
            }
        });

        return back()->with('success', __(count($expenses) . ' expenses approved and budgets updated successfully!'));
    }

    public function index(Request $request)
    {
        $this->authorizePermission('expense_approval_view_any');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        $query = ProjectExpense::with(['project:id,title', 'budgetCategory:id,name,color', 'submitter:id,name,avatar'])
            ->whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })
            ->whereIn('project_expenses.status', ['pending', 'requires_info']);
            
        // Apply filters
        if ($request->status && $request->status !== 'all') {
            $query->where('project_expenses.status', $request->status);
        }
        
        if ($request->project_id && $request->project_id !== 'all') {
            $query->where('project_id', $request->project_id);
        }
        
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%')
                  ->orWhere('vendor', 'like', '%' . $request->search . '%')
                  ->orWhereHas('submitter', function($subQ) use ($request) {
                      $subQ->where('name', 'like', '%' . $request->search . '%');
                  })
                  ->orWhereHas('project', function($projQ) use ($request) {
                      $projQ->where('title', 'like', '%' . $request->search . '%');
                  });
            });
        }
        
        // Add sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        // Validate sort fields
        $allowedSortFields = ['created_at', 'amount', 'expense_date', 'title', 'status', 'project.title', 'submitter.name'];
        if ($sortBy === 'project.title') {
            $query->join('projects as sort_projects', 'project_expenses.project_id', '=', 'sort_projects.id')
                  ->orderBy('sort_projects.title', $sortOrder)
                  ->select('project_expenses.*');
        } elseif ($sortBy === 'submitter.name') {
            $query->join('users as sort_users', 'project_expenses.submitted_by', '=', 'sort_users.id')
                  ->orderBy('sort_users.name', $sortOrder)
                  ->select('project_expenses.*');
        } elseif ($sortBy === 'status') {
            $query->orderBy('project_expenses.status', $sortOrder);
        } elseif (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy('project_expenses.' . $sortBy, $sortOrder);
        } else {
            $query->latest('project_expenses.created_at');
        }
        
        $perPage = $request->get('per_page', 12);
        $pendingExpenses = $query->paginate($perPage);
        
        // Get filter options
        $projects = \App\Models\Project::forWorkspace($workspace->id)
            ->select('id', 'title')
            ->get();
        
        // Get overview stats
        $stats = [
            'pending_count' => ProjectExpense::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->where('project_expenses.status', 'pending')->count(),
            
            'requires_info_count' => ProjectExpense::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->where('project_expenses.status', 'requires_info')->count(),
            
            'approved_today' => ProjectExpense::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->where('project_expenses.status', 'approved')->whereDate('project_expenses.updated_at', today())->count(),
            
            'pending_amount' => ProjectExpense::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->where('project_expenses.status', 'pending')->sum('project_expenses.amount')
        ];

        return Inertia::render('expenses/Approvals', [
            'expenses' => $pendingExpenses,
            'stats' => $stats,
            'projects' => $projects,
            'filters' => $request->only(['status', 'project_id', 'search', 'per_page', 'sort_by', 'sort_order', 'view']),
            'permissions' => [
                'approve' => $this->checkPermission('expense_approval_approve'),
                'reject' => $this->checkPermission('expense_approval_reject'),
                'request_info' => $this->checkPermission('expense_approval_request_info'),
            ]
        ]);
    }

    public function pendingApprovals()
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        $pendingExpenses = ProjectExpense::with(['project', 'budgetCategory', 'submitter'])
            ->whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })
            ->where('project_expenses.status', 'pending')
            ->latest('project_expenses.created_at')
            ->paginate(12);

        return response()->json([
            'expenses' => $pendingExpenses
        ]);
    }

    /**
     * Get budget summary for a project
     */
    public function getBudgetSummary(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id'
        ]);
        
        $summary = $this->budgetService->getProjectBudgetSummary($validated['project_id']);
        
        return response()->json($summary);
    }

    /**
     * Get expense approval statistics
     */
    public function getApprovalStats()
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        $stats = [
            'pending_count' => ProjectExpense::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->where('project_expenses.status', 'pending')->count(),
            
            'approved_today' => ProjectExpense::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->where('project_expenses.status', 'approved')
              ->whereDate('project_expenses.updated_at', today())->count(),
              
            'total_approved_amount' => ProjectExpense::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->where('project_expenses.status', 'approved')->sum('project_expenses.amount'),
            
            'pending_amount' => ProjectExpense::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->where('project_expenses.status', 'pending')->sum('project_expenses.amount')
        ];
        
        return response()->json($stats);
    }
}