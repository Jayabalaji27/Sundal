<?php

namespace App\Actions\Invoices;

use App\Actions\ActionException;
use App\Events\InvoiceCreated;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Task;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Changes an invoice. Only the keys present in $data change:
 * client_id, title, description, invoice_date, due_date, notes, terms,
 * selected_taxes (ids) and items ([{type, description?, amount, task_id?,
 * expense_id?, timesheet_entry_id?}], replacing all items). Totals are
 * recalculated. Shared by InvoiceController and the AI assistant.
 */
class UpdateInvoice
{
    public const FIELDS = ['client_id', 'title', 'description', 'invoice_date', 'due_date', 'notes', 'terms'];

    public function handle(User $actor, Invoice $invoice, array $data): Invoice
    {
        $invoiceDate = $data['invoice_date'] ?? $invoice->invoice_date?->format('Y-m-d');
        $dueDate = $data['due_date'] ?? $invoice->due_date?->format('Y-m-d');
        if ($invoiceDate && $dueDate && $dueDate < $invoiceDate) {
            throw new ActionException(__('The due date cannot be before the invoice date.'));
        }
        if (array_key_exists('items', $data)) {
            CreateInvoice::ensureTasksNotBilled($data['items'], $invoice->id);
        }

        DB::transaction(function () use ($invoice, $data) {
            $changes = array_intersect_key($data, array_flip(self::FIELDS));
            if (array_key_exists('selected_taxes', $data)) {
                $changes['tax_rate'] = CreateInvoice::taxRows(Tax::whereIn('id', $data['selected_taxes'] ?? [])->get(['id', 'name', 'rate']));
            }
            $invoice->update($changes);

            if (array_key_exists('items', $data)) {
                $invoice->items()->delete();
                foreach (array_values($data['items']) as $index => $item) {
                    $description = $item['description']
                        ?? (!empty($item['task_id']) ? Task::find($item['task_id'])?->title : null)
                        ?? 'Item';
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'type' => $item['type'],
                        'description' => $description,
                        'rate' => $item['amount'],
                        'amount' => $item['amount'],
                        'task_id' => $item['task_id'] ?? null,
                        'expense_id' => $item['expense_id'] ?? null,
                        'timesheet_entry_id' => $item['timesheet_entry_id'] ?? null,
                        'sort_order' => $index + 1,
                    ]);
                }
            }

            if (array_key_exists('items', $data) || array_key_exists('selected_taxes', $data)) {
                $invoice->load('items')->calculateTotals();
            }
        });

        if (!config('app.is_demo', true)) {
            event(new InvoiceCreated($invoice));
        }

        return $invoice;
    }
}
