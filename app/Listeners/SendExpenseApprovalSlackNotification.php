<?php

namespace App\Listeners;

use App\Events\ExpenseApprovalRequested;
use App\Services\SlackService;

class SendExpenseApprovalSlackNotification
{
    public function handle(ExpenseApprovalRequested $event): void
    {
        $expense = $event->expense;
        $userId = $expense->created_by ?? auth()->id();
        $workspaceId = $expense->project->workspace_id ?? null;

        if (!$userId)
            return;
        if (isSlackNotificationEnabled('expense_approval', $userId, $workspaceId)) {

            $data = [
                'title' => 'Expense Approval Required',
                'message' => "Expense '{$expense->title}' for {$expense->amount} requires approval.",
                'expense_title' => $expense->title,
                'expense_amount' => $expense->amount,
                'project_name' => $expense->project->title ?? 'Unknown Project',
                'submitted_by' => $expense->user->name ?? 'Unknown User',
                'url' => route('expenses.show', $expense->id)
            ];

            SlackService::send('expense_approval', $data, $userId, $workspaceId);
        }
    }
}