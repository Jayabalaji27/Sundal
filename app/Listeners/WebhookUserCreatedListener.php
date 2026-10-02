<?php

namespace App\Listeners;

use App\Events\WorkspaceInvited;
use App\Services\WebhookService;

class WebhookUserCreatedListener
{
    public function __construct(
        private WebhookService $webhookService
    ) {
    }

    public function handle(WorkspaceInvited $event): void
    {
        try {
            $invitation = $event->invitation;
            
            // Check if webhook exists for this module before triggering
            $webhook = \App\Models\Webhook::where('module', 'Workspace Invitation')
                ->where('user_id', $invitation->invited_by)
                ->first();
                
            if ($webhook) {
                // Trigger webhooks for Workspace Invitation
                $this->webhookService->triggerWebhooks('Workspace Invitation', $invitation->toArray(), $invitation->invited_by);
            }
        } catch (\Exception $e) {
            \Log::error('Webhook failed for Workspace Invitation: ' . $e->getMessage());
        }
    }
}