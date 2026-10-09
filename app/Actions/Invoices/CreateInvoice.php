<?php

namespace App\Actions\Invoices;

use App\Actions\ActionException;
use App\Events\InvoiceCreated;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a draft invoice billing project tasks. Shared by
 * InvoiceController and the AI assistant.
 *
 * $data: project_id, client_id?, title, description?, invoice_date,
 * due_date, selected_taxes? (ids), notes?, terms?,
 * items: [{task_id, amount}] (type "task" only, as on the screen).
 */
class CreateInvoice
{
    public function handle(User $actor, array $data): Invoice
    {
        $items = array_values($data['items'] ?? []);
        if ($items === []) {
            throw new ActionException(__('An invoice needs at least one task to bill.'));
        }
        foreach ($items as $item) {
            if ((float) ($item['amount'] ?? 0) <= 0) {
                throw new ActionException(__('Every invoice item needs an amount greater than 0.'));
            }
        }
        self::ensureTasksNotBilled($items);

        $project = Project::findOrFail($data['project_id']);

        $subtotal = collect($items)->sum(fn ($item) => (float) $item['amount']);
        $appliedTaxes = Tax::whereIn('id', $data['selected_taxes'] ?? [])->get(['id', 'name', 'rate']);
        $taxAmount = $appliedTaxes->sum(fn (Tax $tax) => ($subtotal * $tax->rate) / 100);

        $invoice = DB::transaction(function () use ($actor, $data, $items, $project, $subtotal, $appliedTaxes, $taxAmount) {
            $invoice = Invoice::create([
                'project_id' => $project->id,
                'workspace_id' => $project->workspace_id,
                'client_id' => $data['client_id'] ?? null,
                'created_by' => $actor->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'],
                'tax_rate' => self::taxRows($appliedTaxes),
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $subtotal + $taxAmount,
            ]);

            foreach ($items as $index => $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'type' => 'task',
                    'description' => Task::find($item['task_id'])?->title ?? 'Task',
                    'rate' => $item['amount'],
                    'amount' => $item['amount'],
                    'task_id' => $item['task_id'],
                    'sort_order' => $index + 1,
                ]);
            }

            return $invoice;
        });

        if (!config('app.is_demo', true)) {
            event(new InvoiceCreated($invoice));
        }

        return $invoice;
    }

    /** Stops a task from being billed twice (BUG-14). Errors are keyed like the form: items.N.task_id. */
    public static function billedErrors(array $items, ?int $exceptInvoiceId = null): array
    {
        $billed = InvoiceItem::billedTaskIds(array_column($items, 'task_id'), $exceptInvoiceId);

        $errors = [];
        foreach (array_values($items) as $index => $item) {
            if (!empty($item['task_id']) && in_array((int) $item['task_id'], $billed, true)) {
                $errors["items.{$index}.task_id"] = __('This task is already on a sent or paid invoice.');
            }
        }

        return $errors;
    }

    public static function ensureTasksNotBilled(array $items, ?int $exceptInvoiceId = null): void
    {
        if (self::billedErrors($items, $exceptInvoiceId)) {
            throw new ActionException(__('A task you picked is already on a sent or paid invoice.'));
        }
    }

    /** The applied taxes as stored on the invoice: [{id, name, rate}]. */
    public static function taxRows($taxes): array
    {
        return collect($taxes)->map(fn (Tax $tax) => ['id' => $tax->id, 'name' => $tax->name, 'rate' => $tax->rate])->values()->all();
    }
}
