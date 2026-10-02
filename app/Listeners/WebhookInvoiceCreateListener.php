<?php

namespace App\Listeners;

use App\Events\InvoiceCreated;
use App\Services\WebhookService;

class WebhookInvoiceCreateListener
{
    public function __construct(
        private WebhookService $webhookService
    ) {
    }

    public function handle(InvoiceCreated $event): void
    {
        try {
            $invoice = $event->invoice;
            
            // Check if webhook exists for this module before triggering
            $webhook = \App\Models\Webhook::where('module', 'New Invoice')
                ->where('user_id', $invoice->created_by)
                ->first();
                
            if ($webhook) {
                // Trigger webhooks for New Invoice
                $this->webhookService->triggerWebhooks('New Invoice', $invoice->toArray(), $invoice->created_by);
            }
        } catch (\Exception $e) {
            \Log::error('Webhook failed for New Invoice: ' . $e->getMessage());
        }
    }
}