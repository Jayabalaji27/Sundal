<?php

namespace App\Http\Controllers;

use App\Models\ExpenseWorkflow;
use App\Models\ProjectExpense;
use Illuminate\Http\Request;

class ExpenseWorkflowController extends Controller
{
    public function processStep(Request $request, ExpenseWorkflow $workflow)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject,request_info',
            'notes' => 'nullable|string'
        ]);

        $workflow->update([
            'status' => $validated['action'] === 'approve' ? 'approved' : 
                       ($validated['action'] === 'reject' ? 'rejected' : 'requires_info'),
            'notes' => $validated['notes'],
            'processed_at' => now()
        ]);

        $expense = $workflow->projectExpense;

        if ($validated['action'] === 'approve') {
            // Check if this is the final step
            $nextStep = ExpenseWorkflow::where('project_expense_id', $expense->id)
                ->where('step', '>', $workflow->step)
                ->where('status', 'waiting')
                ->orderBy('step')
                ->first();

            if ($nextStep) {
                // Move to next approval step
                $nextStep->update(['status' => 'pending']);
            } else {
                // Final approval - approve the expense
                $expense->update(['status' => 'approved']);
            }
        } else {
            // Rejection or info request - stop workflow
            $expense->update(['status' => $validated['action'] === 'reject' ? 'rejected' : 'requires_info']);
            
            // Mark remaining steps as cancelled
            ExpenseWorkflow::where('project_expense_id', $expense->id)
                ->where('step', '>', $workflow->step)
                ->update(['status' => 'cancelled']);
        }

        return back();
    }

    public function bulkProcess(Request $request)
    {
        $validated = $request->validate([
            'workflow_ids' => 'required|array',
            'workflow_ids.*' => 'exists:expense_workflows,id',
            'action' => 'required|in:approve,reject',
            'notes' => 'nullable|string'
        ]);

        $workflows = ExpenseWorkflow::whereIn('id', $validated['workflow_ids'])
            ->where('status', 'pending')
            ->get();

        foreach ($workflows as $workflow) {
            $this->processStep(new Request([
                'action' => $validated['action'],
                'notes' => $validated['notes']
            ]), $workflow);
        }

        return back();
    }

    public function myApprovals()
    {
        $user = auth()->user();
        
        $pendingApprovals = ExpenseWorkflow::with(['projectExpense.project', 'projectExpense.submitter'])
            ->where('approver_id', $user->id)
            ->where('status', 'pending')
            ->latest()
            ->paginate(20);

        return response()->json([
            'approvals' => $pendingApprovals
        ]);
    }
}