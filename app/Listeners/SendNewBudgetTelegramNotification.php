<?php

namespace App\Listeners;

use App\Events\BudgetCreated;
use App\Services\TelegramService;

class SendNewBudgetTelegramNotification
{
    public function handle(BudgetCreated $event): void
    {
        $budget = $event->budget;
        $userId = $budget->created_by ?? auth()->id();
        $workspaceId = $budget->workspace_id ?? null;
        
        if (!$userId) return;

        if (isTelegramNotificationEnabled('new_budget', $userId, $workspaceId)) {
            $data = [
                'title' => 'New Budget Created',
                'message' => "A new budget for '{$budget->project->title}' has been created with total amount {$budget->total_budget}.",
                'project_name' => $budget->project->title ?? 'Unknown Project',
                'total_budget' => $budget->total_budget,
                'period_type' => $budget->period_type,
                'url' => route('budgets.show', $budget->id)
            ];

            TelegramService::send('new_budget', $data, $userId, $workspaceId);
        }
    }
}