<?php

namespace App\Listeners;

use App\Events\ProjectCreated;
use App\Services\WebhookService;

class WebhookProjectCreateListener
{
    public function __construct(
        private WebhookService $webhookService
    ) {
    }

    public function handle(ProjectCreated $event): void
    {
        try {
            $project = $event->project;
            
            // Check if webhook exists for this module before triggering
            $webhook = \App\Models\Webhook::where('module', 'New Project')
                ->where('user_id', $project->created_by)
                ->first();
                
            if ($webhook) {
                // Trigger webhooks for New Project
                $this->webhookService->triggerWebhooks('New Project', $project->toArray(), $project->created_by);
            }
        } catch (\Exception $e) {
            \Log::error('Webhook failed for New Project: ' . $e->getMessage());
        }
    }
}