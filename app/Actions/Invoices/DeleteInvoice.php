<?php

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Models\User;

/**
 * Deletes an invoice (a hard delete). Shared by InvoiceController and the
 * AI assistant, which only offers it for draft invoices.
 */
class DeleteInvoice
{
    public function handle(User $actor, Invoice $invoice): void
    {
        $invoice->delete();
    }
}
