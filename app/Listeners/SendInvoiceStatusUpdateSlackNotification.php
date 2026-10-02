<?php

namespace App\Listeners;

use App\Events\InvoiceStatusUpdated;
use App\Services\SlackService;

class SendInvoiceStatusUpdateSlackNotification
{
    public function handle(InvoiceStatusUpdated $event): void
    {
        $invoice = $event->invoice;
        $userId = $invoice->created_by ?? auth()->id();
        $workspaceId = $invoice->project->workspace_id ?? null;

        if (!$userId)
            return;
        if (isSlackNotificationEnabled('invoice_status_updated', $userId, $workspaceId)) {

            $data = [
                'title' => 'Invoice Status Updated',
                'message' => "Invoice #{$invoice->invoice_number} status updated from '{$event->oldStatus}' to '{$event->newStatus}'.",
                'invoice_number' => $invoice->invoice_number,
                'project_name' => $invoice->project->title ?? 'Unknown Project',
                'old_status' => $event->oldStatus,
                'new_status' => $event->newStatus,
                'amount' => $invoice->total_amount,
                'url' => route('invoices.show', $invoice->id)
            ];

            SlackService::send('invoice_status_updated', $data, $userId, $workspaceId);
        }
    }
}