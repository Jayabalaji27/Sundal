<?php

namespace App\Services\Ai\Tools;

use App\Actions\Invoices\UpdateInvoice;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;

/** Change a draft invoice's title, dates or notes. Items and amounts stay on the invoice page. */
class UpdateInvoiceTool extends AiTool implements HasForm
{
    use RevertsChanges;

    private const FIELDS = ['title', 'invoice_date', 'due_date', 'notes'];

    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly UpdateInvoice $updateInvoice,
    ) {}

    public function name(): string
    {
        return 'update_invoice';
    }

    public function description(): string
    {
        return 'Change the title, invoice date, due date or notes of a draft invoice. Only the values given change. '
            . 'Items and amounts are changed on the invoice page. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['invoice_update'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'invoice' => ['type' => 'string', 'required' => true, 'description' => 'Invoice number (e.g. INV-104) or id. Draft invoices only.'],
            'title' => ['type' => 'string', 'description' => 'New title.'],
            'invoice_date' => ['type' => 'string', 'description' => 'New invoice date, YYYY-MM-DD.'],
            'due_date' => ['type' => 'string', 'description' => 'New due date, YYYY-MM-DD.'],
            'notes' => ['type' => 'string', 'description' => 'New notes.'],
        ];
    }

    public function formTitle(): string
    {
        return __('Change an invoice');
    }

    public function formFields(User $user): array
    {
        return [
            new FormField('invoice', __('Invoice'), 'invoice', required: true),
            new FormField('title', __('Title'), 'text'),
            new FormField('invoice_date', __('Invoice date'), 'date'),
            new FormField('due_date', __('Due date'), 'date'),
            new FormField('notes', __('Notes'), 'text', maxLength: 2000),
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        /** @var Invoice $invoice */
        $invoice = $this->resolver->find($this->resolver->draftInvoices($user), (string) ($args['invoice'] ?? ''), 'invoice_number', __('draft invoice'));

        $changes = [];
        foreach (self::FIELDS as $field) {
            $value = isset($args[$field]) ? trim((string) $args[$field]) : '';
            if ($value === '') {
                continue;
            }
            $changes[$field] = in_array($field, ['invoice_date', 'due_date'], true) ? $this->resolver->date($value, __(ucfirst(str_replace('_', ' ', $field)))) : $value;
        }
        if (isset($changes['title']) && mb_strlen($changes['title']) > 255) {
            throw new ToolInputException(__('The invoice title can be at most 255 characters.'));
        }
        if ($changes === []) {
            throw new ToolInputException(__('What should change on invoice :number? Ask the user.', ['number' => $invoice->invoice_number]));
        }

        $invoiceDate = $changes['invoice_date'] ?? $invoice->invoice_date?->format('Y-m-d');
        $dueDate = $changes['due_date'] ?? $invoice->due_date?->format('Y-m-d');
        if ($invoiceDate && $dueDate && $dueDate < $invoiceDate) {
            throw new ToolInputException(__('The due date cannot be before the invoice date.'));
        }

        $old = $this->snapshot($invoice, array_keys($changes));

        return new PreparedAction(
            summary: __('Change invoice :number', ['number' => $invoice->invoice_number]),
            details: [
                __('Invoice') => $invoice->invoice_number,
                ...collect($changes)->mapWithKeys(fn ($value, $field) => [
                    __(ucfirst(str_replace('_', ' ', $field))) => trim(($old[$field] ?? '—') . ' → ' . $value),
                ])->all(),
            ],
            payload: ['invoice_id' => $invoice->id, 'changes' => $changes],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $invoice = $this->resolver->byId($this->resolver->draftInvoices($user), (int) $payload['invoice_id'], __('draft invoice'));
        $before = $this->snapshot($invoice, array_keys($payload['changes']));

        $this->updateInvoice->handle($user, $invoice, $payload['changes']);

        return new ToolOutcome(
            __('Updated invoice :number.', ['number' => $invoice->invoice_number]),
            $invoice,
            route('invoices.show', $invoice->id, false),
            $this->changeUndo($invoice, $before),
        );
    }

    public function undo(array $undo, User $user): string
    {
        $invoice = $this->resolver->byId($this->resolver->draftInvoices($user), (int) $undo['id'], __('draft invoice'));
        $this->ensureUnchanged($invoice, $undo, __('Invoice :number', ['number' => $invoice->invoice_number]));

        $this->updateInvoice->handle($user, $invoice, $undo['before']);

        return __('Invoice :number put back.', ['number' => $invoice->invoice_number]);
    }
}
