<?php

namespace App\Listeners;

use App\Events\TaskCreated;
use App\Services\WebhookService;

class WebhookTaskCreateListener
{
    public function __construct(
        private WebhookService $webhookService
    ) {
    }

    public function handle(TaskCreated $event): void
    {
        try {
            $task = $event->task;
            
            // Check if webhook exists for this module before triggering
            $webhook = \App\Models\Webhook::where('module', 'New Task')
                ->where('user_id', $task->created_by)
                ->first();
                
            if ($webhook) {
                // Trigger webhooks for New Task
                $this->webhookService->triggerWebhooks('New Task', $task->toArray(), $task->created_by);
            }
        } catch (\Exception $e) {
            \Log::error('Webhook failed for New Task: ' . $e->getMessage());
        }
    }
}