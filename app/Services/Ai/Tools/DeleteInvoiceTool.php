<?php

namespace App\Services\Ai\Tools;

use App\Actions\Invoices\DeleteInvoice;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;

/**
 * Delete a draft invoice. A hard delete with no undo, so the card needs the
 * invoice number typed. Sent or paid invoices stay on the Invoices screen.
 */
class DeleteInvoiceTool extends AiTool implements HasForm
{
    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly DeleteInvoice $deleteInvoice,
    ) {}

    public function name(): string
    {
        return 'delete_invoice';
    }

    public function description(): string
    {
        return 'Permanently delete a draft invoice. The user must type the invoice number on the confirmation card. Sent or paid invoices cannot be deleted here.';
    }

    public function permissions(): array
    {
        return ['invoice_delete'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'invoice' => ['type' => 'string', 'required' => true, 'description' => 'Invoice number (e.g. INV-104) or id. Draft invoices only.'],
        ];
    }

    public function formTitle(): string
    {
        return __('Delete an invoice');
    }

    public function formFields(User $user): array
    {
        return [new FormField('invoice', __('Invoice'), 'invoice', required: true, question: __('Which draft invoice should be deleted?'))];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        /** @var Invoice $invoice */
        $invoice = $this->resolver->find($this->resolver->draftInvoices($user), (string) ($args['invoice'] ?? ''), 'invoice_number', __('draft invoice'));
        $invoice->loadMissing('client:id,name', 'project:id,title');

        return new PreparedAction(
            summary: __('Delete draft invoice :number', ['number' => $invoice->invoice_number]),
            details: array_filter([
                __('Invoice') => $invoice->invoice_number,
                __('Project') => $invoice->project?->title,
                __('Client') => $invoice->client?->name,
                __('Total') => number_format((float) $invoice->total_amount, 2),
                __('Note') => __('This cannot be undone.'),
            ]),
            payload: ['invoice_id' => $invoice->id],
            confirmPhrase: (string) $invoice->invoice_number,
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $invoice = $this->resolver->byId($this->resolver->draftInvoices($user), (int) $payload['invoice_id'], __('draft invoice'));
        $number = $invoice->invoice_number;

        $this->deleteInvoice->handle($user, $invoice);

        return new ToolOutcome(__('Deleted draft invoice :number.', ['number' => $number]), null, route('invoices.index', [], false));
    }
}
