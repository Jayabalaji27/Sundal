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

        if ($this->isOwnExpense($expense)) {
            return back()->with('error', __('You cannot approve or reject your own expense. The workspace owner reviews it.'));
        }
        
        $validated = $request->validate([
            'notes' => 'nullable|string'
        ]);

        // Shared with the AI assistant: approval record, status, budget update, Slack event.
        app(\App\Actions\Expenses\DecideExpense::class)->handle(auth()->user(), $expense, 'approved', $validated['notes'] ?? null);

        return back()->with('success', __('Expense approved and budget updated successfully!'));
    }

    public function reject(Request $request, ProjectExpense $expense)
    {
        $this->authorizePermission('expense_approval_reject');

        if ($this->isOwnExpense($expense)) {
            return back()->with('error', __('You cannot approve or reject your own expense. The workspace owner reviews it.'));
        }
        
        // The submitter needs to know what to fix, as with timesheet rejection.
        // Validated outside the try so a missing reason is a field error, not
        // the generic "Failed to reject" below.
        $validated = $request->validate([
            'notes' => 'required|string|max:1000'
        ]);

        try {
            app(\App\Actions\Expenses\DecideExpense::class)->handle(auth()->user(), $expense, 'rejected', $validated['notes']);

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
            ->get()
            ->reject(fn (ProjectExpense $expense) => $this->isOwnExpense($expense));

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
        
        $query = $this->reviewableExpenses()
            ->with(['project:id,title', 'budgetCategory:id,name,color', 'submitter:id,name,avatar'])
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
            'pending_count' => $this->reviewableExpenses()->where('project_expenses.status', 'pending')->count(),
            
            'requires_info_count' => $this->reviewableExpenses()->where('project_expenses.status', 'requires_info')->count(),
            
            'approved_today' => $this->reviewableExpenses()->where('project_expenses.status', 'approved')->whereDate('project_expenses.updated_at', today())->count(),
            
            'pending_amount' => $this->reviewableExpenses()->where('project_expenses.status', 'pending')->sum('project_expenses.amount')
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
                // Only the workspace owner reviews their own expenses
                'review_own' => $workspace->isOwner($user),
            ]
        ]);
    }

    public function pendingApprovals()
    {
        $pendingExpenses = $this->reviewableExpenses()
            ->with(['project', 'budgetCategory', 'submitter'])
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
        $stats = [
            'pending_count' => $this->reviewableExpenses()->where('project_expenses.status', 'pending')->count(),
            
            'approved_today' => $this->reviewableExpenses()->where('project_expenses.status', 'approved')
              ->whereDate('project_expenses.updated_at', today())->count(),
              
            'total_approved_amount' => $this->reviewableExpenses()->where('project_expenses.status', 'approved')->sum('project_expenses.amount'),
            
            'pending_amount' => $this->reviewableExpenses()->where('project_expenses.status', 'pending')->sum('project_expenses.amount')
        ];
        
        return response()->json($stats);
    }

    /**
     * Expenses in the reviewer's queue: the whole workspace for the owner, only
     * assigned/created projects for a manager (same scope as the Expenses page).
     * The list and its stats both use this so the numbers match what's shown.
     */
    private function reviewableExpenses()
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        $query = ProjectExpense::whereHas('project', function ($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id);
        });

        if (!$workspace->isOwner($user) && $workspace->getMemberRole($user) === 'manager') {
            $query->whereHas('project', function ($q) use ($user) {
                $q->where(function ($projectQuery) use ($user) {
                    $projectQuery->whereHas('members', fn ($memberQuery) => $memberQuery->where('user_id', $user->id))
                        ->orWhere('created_by', $user->id);
                });
            });
        }

        return $query;
    }

    /**
     * Nobody reviews their own expense, except the workspace owner: there is no
     * one above them to send it to.
     */
    private function isOwnExpense(ProjectExpense $expense): bool
    {
        $user = auth()->user();

        return (int) $expense->submitted_by === (int) $user->id
            && !$user->currentWorkspace?->isOwner($user);
    }
}
