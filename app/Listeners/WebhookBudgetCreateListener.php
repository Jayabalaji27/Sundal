<?php

namespace App\Listeners;

use App\Events\BudgetCreated;
use App\Services\WebhookService;

class WebhookBudgetCreateListener
{
    public function __construct(
        private WebhookService $webhookService
    ) {
    }

    public function handle(BudgetCreated $event): void
    {
        try {
            $budget = $event->budget;
            
            // Check if webhook exists for this module before triggering
            $webhook = \App\Models\Webhook::where('module', 'New Budget')
                ->where('user_id', $budget->created_by)
                ->first();
                
            if ($webhook) {
                // Trigger webhooks for New Budget
                $this->webhookService->triggerWebhooks('New Budget', $budget->toArray(), $budget->created_by);
            }
        } catch (\Exception $e) {
            \Log::error('Webhook failed for New Budget: ' . $e->getMessage());
        }
    }
}