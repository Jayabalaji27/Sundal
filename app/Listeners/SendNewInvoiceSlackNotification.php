<?php

namespace App\Listeners;

use App\Events\InvoiceCreated;
use App\Services\SlackService;

class SendNewInvoiceSlackNotification
{
    public function handle(InvoiceCreated $event): void
    {
        $invoice = $event->invoice;
        $userId = $invoice->created_by ?? auth()->id();
        $workspaceId = $invoice->project->workspace_id ?? null;
        
        if (!$userId) return;

        if (isSlackNotificationEnabled('new_invoice', $userId, $workspaceId)) {
            $data = [
                'title' => 'New Invoice Created',
                'message' => "A new invoice #{$invoice->invoice_number} has been created for {$invoice->total_amount}.",
                'invoice_number' => $invoice->invoice_number,
                'project_name' => $invoice->project->title ?? 'Unknown Project',
                'client_name' => $invoice->client->name ?? 'Unknown Client',
                'amount' => $invoice->total_amount,
                'url' => route('invoices.show', $invoice->id)
            ];

            SlackService::send('new_invoice', $data, $userId, $workspaceId);
        }
    }
}