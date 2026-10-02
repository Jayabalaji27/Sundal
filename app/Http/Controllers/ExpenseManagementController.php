<?php

namespace App\Http\Controllers;

use App\Models\ProjectExpense;
use App\Models\ExpenseApproval;
use App\Models\Project;
use App\Services\BudgetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ExpenseManagementController extends Controller
{
    protected BudgetService $budgetService;
    
    public function __construct(BudgetService $budgetService)
    {
        $this->budgetService = $budgetService;
    }

    /**
     * Display submitted expenses awaiting approval
     */
    public function submittedExpenses(Request $request)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        $query = ProjectExpense::with([
            'project:id,name,workspace_id', 
            'budgetCategory:id,name,color,allocated_amount', 
            'submitter:id,name,avatar',
            'approvals' => function($q) {
                $q->latest()->with('approver:id,name');
            }
        ])->whereHas('project', function($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id);
        });

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'pending'); // Default to pending
        }

        // Filter by project
        if ($request->project_id) {
            $query->where('project_id', $request->project_id);
        }

        // Filter by date range
        if ($request->date_from) {
            $query->whereDate('expense_date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('expense_date', '<=', $request->date_to);
        }

        // Filter by amount range
        if ($request->amount_min) {
            $query->where('amount', '>=', $request->amount_min);
        }
        if ($request->amount_max) {
            $query->where('amount', '<=', $request->amount_max);
        }

        $expenses = $query->latest('expense_date')->paginate(20);

        // Get filter options
        $projects = Project::forWorkspace($workspace->id)
            ->select('id', 'name')
            ->get();

        // Get expense statistics
        $stats = $this->getExpenseStats($workspace->id);

        return Inertia::render('expenses/Management', [
            'expenses' => $expenses,
            'projects' => $projects,
            'stats' => $stats,
            'filters' => $request->only(['status', 'project_id', 'date_from', 'date_to', 'amount_min', 'amount_max'])
        ]);
    }

    /**
     * Process expense approval with budget update
     */
    public function processApproval(Request $request, ProjectExpense $expense)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject,request_info,resubmit',
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($expense->status !== 'pending') {
            return back()->withErrors(['error' => 'This expense has already been processed.']);
        }

        DB::transaction(function () use ($expense, $validated) {
            // Create approval record
            ExpenseApproval::create([
                'project_expense_id' => $expense->id,
                'approver_id' => auth()->id(),
                'status' => $validated['action'] === 'approve' ? 'approved' : 
                           ($validated['action'] === 'reject' ? 'rejected' : 'requires_info'),
                'notes' => $validated['notes'],
                'approved_at' => now(),
                'approval_level' => 1
            ]);

            // Update expense status
            $newStatus = match($validated['action']) {
                'approve' => 'approved',
                'reject' => 'rejected',
                'request_info' => 'requires_info',
                'resubmit' => 'pending'
            };
            
            $updateData = ['status' => $newStatus];
            
            // No additional fields needed for now
            
            $expense->update($updateData);

            // Update budget if approved - disabled temporarily
            // if ($validated['action'] === 'approve') {
            //     $this->budgetService->updateBudgetAfterApproval($expense);
            // }
        });

        $message = match($validated['action']) {
            'approve' => 'Expense approved and budget updated successfully!',
            'reject' => 'Expense rejected successfully!',
            'request_info' => 'Additional information requested from submitter!'
        };

        return back()->with('success', $message);
    }

    /**
     * Bulk process multiple expenses
     */
    public function bulkProcess(Request $request)
    {
        $validated = $request->validate([
            'expense_ids' => 'required|array|min:1',
            'expense_ids.*' => 'exists:project_expenses,id',
            'action' => 'required|in:approve,reject',
            'notes' => 'nullable|string|max:1000'
        ]);

        $expenses = ProjectExpense::whereIn('id', $validated['expense_ids'])
            ->where('status', 'pending')
            ->get();

        if ($expenses->isEmpty()) {
            return back()->withErrors(['error' => 'No valid expenses found for processing.']);
        }

        DB::transaction(function () use ($expenses, $validated) {
            foreach ($expenses as $expense) {
                // Create approval record
                ExpenseApproval::create([
                    'project_expense_id' => $expense->id,
                    'approver_id' => auth()->id(),
                    'status' => $validated['action'] === 'approve' ? 'approved' : 'rejected',
                    'notes' => $validated['notes'],
                    'approved_at' => now(),
                    'approval_level' => 1
                ]);

                // Update expense status
                $updateData = ['status' => $validated['action'] === 'approve' ? 'approved' : 'rejected'];
                
                // No additional fields needed for now
                
                $expense->update($updateData);

                // Update budget if approved - disabled temporarily
                // if ($validated['action'] === 'approve') {
                //     $this->budgetService->updateBudgetAfterApproval($expense);
                // }
            }
        });

        $count = $expenses->count();
        $action = $validated['action'] === 'approve' ? 'approved' : 'rejected';
        
        return back()->with('success', "{$count} expenses {$action} successfully!");
    }

    /**
     * Get expense statistics for dashboard
     */
    private function getExpenseStats(int $workspaceId): array
    {
        $baseQuery = ProjectExpense::whereHas('project', function($q) use ($workspaceId) {
            $q->where('workspace_id', $workspaceId);
        });

        return [
            'pending_count' => (clone $baseQuery)->where('status', 'pending')->count(),
            'pending_amount' => (clone $baseQuery)->where('status', 'pending')->sum('amount'),
            'approved_today' => (clone $baseQuery)->where('status', 'approved')
                ->whereDate('updated_at', today())->count(),
            'approved_this_month' => (clone $baseQuery)->where('status', 'approved')
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)->sum('amount'),
            'rejected_count' => (clone $baseQuery)->where('status', 'rejected')->count(),
            'requires_info_count' => (clone $baseQuery)->where('status', 'requires_info')->count(),
        ];
    }

    /**
     * Export expenses data
     */
    public function export(Request $request)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        $query = ProjectExpense::with([
            'project:id,title',
            'budgetCategory:id,name',
            'submitter:id,name'
        ])->whereHas('project', function($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id);
        });

        // Apply same filters as index
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->project_id) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->date_from) {
            $query->whereDate('expense_date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('expense_date', '<=', $request->date_to);
        }

        $expenses = $query->latest('expense_date')->get();

        $csvData = $expenses->map(function ($expense) {
            return [
                'Date' => $expense->expense_date->format('Y-m-d'),
                'Project' => $expense->project->title,
                'Category' => $expense->budgetCategory?->name ?? 'N/A',
                'Title' => $expense->title,
                'Amount' => $expense->amount,
                'Currency' => $expense->currency,
                'Status' => ucfirst($expense->status),
                'Submitted By' => $expense->submitter->name,
                'Submitted At' => $expense->created_at->format('Y-m-d H:i:s'),
            ];
        });

        $filename = 'expenses_' . now()->format('Y_m_d_H_i_s') . '.csv';
        
        return response()->streamDownload(function () use ($csvData) {
            $handle = fopen('php://output', 'w');
            
            // Add CSV headers
            if ($csvData->isNotEmpty()) {
                fputcsv($handle, array_keys($csvData->first()));
            }
            
            // Add data rows
            foreach ($csvData as $row) {
                fputcsv($handle, $row);
            }
            
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ]);
    }
}