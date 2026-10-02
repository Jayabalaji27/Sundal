<?php

namespace App\Listeners;

use App\Events\BudgetCreated;
use App\Services\SlackService;

class SendNewBudgetSlackNotification
{
    public function handle(BudgetCreated $event): void
    {
        $budget = $event->budget;
        $userId = $budget->created_by ?? auth()->id();
        $workspaceId = $budget->project->workspace_id ?? null;
        
        if (!$userId) return;

        if (isSlackNotificationEnabled('new_budget', $userId, $workspaceId)) {
            $data = [
                'title' => 'New Budget Created',
                'message' => "A new budget '{$budget->title}' has been created with amount {$budget->amount}.",
                'budget_title' => $budget->title,
                'budget_amount' => $budget->amount,
                'project_name' => $budget->project->title ?? 'Unknown Project',
                'url' => route('budgets.show', $budget->id)
            ];

            SlackService::send('new_budget', $data, $userId, $workspaceId);
        }
    }
}