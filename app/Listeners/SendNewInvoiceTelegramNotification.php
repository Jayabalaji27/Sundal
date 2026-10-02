<?php

namespace App\Listeners;

use App\Events\InvoiceCreated;
use App\Services\TelegramService;

class SendNewInvoiceTelegramNotification
{
    public function handle(InvoiceCreated $event): void
    {
        $invoice = $event->invoice;
        $userId = $invoice->created_by ?? auth()->id();
        $workspaceId = $invoice->workspace_id ?? null;
        
        if (!$userId) return;

        if (isTelegramNotificationEnabled('new_invoice', $userId, $workspaceId)) {
            $data = [
                'title' => 'New Invoice Created',
                'message' => "A new invoice #{$invoice->invoice_number} has been created.",
                'invoice_number' => $invoice->invoice_number,
                'client_name' => $invoice->client->name ?? 'Unknown Client',
                'amount' => $invoice->total_amount ?? '0.00',
                'url' => route('invoices.show', $invoice->id)
            ];

            TelegramService::send('new_invoice', $data, $userId, $workspaceId);
        }
    }
}