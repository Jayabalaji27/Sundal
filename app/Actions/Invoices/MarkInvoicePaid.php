<?php

namespace App\Actions\Invoices;

use App\Actions\ActionException;
use App\Events\InvoiceStatusUpdated;
use App\Models\Invoice;
use App\Models\User;

/**
 * Records that an invoice was paid outside a payment gateway (cash, bank
 * transfer…). Shared by InvoiceController and the AI assistant.
 */
class MarkInvoicePaid
{
    public function handle(User $actor, Invoice $invoice, ?float $amount = null, ?string $method = null, ?string $reference = null, ?array $details = null): Invoice
    {
        if ($amount !== null && ($amount < 0 || $amount > (float) $invoice->total_amount)) {
            throw new ActionException(__('The amount paid must be between 0 and the invoice total (:total).', ['total' => number_format((float) $invoice->total_amount, 2)]));
        }

        $oldStatus = $invoice->status;
        $invoice->markAsPaid($amount, $method, $reference, $details);

        if (!config('app.is_demo', true)) {
            event(new InvoiceStatusUpdated($invoice, $oldStatus, 'paid'));
        }

        return $invoice;
    }
}
