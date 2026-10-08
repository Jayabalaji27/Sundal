<?php

namespace App\Actions\Invoices;

use App\Events\InvoiceStatusUpdated;
use App\Models\Invoice;
use App\Models\User;

/**
 * Marks an invoice as sent and fires the status event. Shared by
 * InvoiceController and the AI assistant.
 */
class SendInvoice
{
    public function handle(User $actor, Invoice $invoice): Invoice
    {
        $oldStatus = $invoice->status;
        $invoice->update(['status' => 'sent', 'sent_at' => now()]);

        if (!config('app.is_demo', true)) {
            event(new InvoiceStatusUpdated($invoice, $oldStatus, 'sent'));
        }

        return $invoice;
    }
}
