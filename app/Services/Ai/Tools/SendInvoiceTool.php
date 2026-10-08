<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;
use App\Actions\Invoices\SendInvoice;
use App\Models\Invoice;
use App\Models\User;

/**
 * Money-related: the card needs the invoice number typed to confirm.
 */
class SendInvoiceTool extends AiTool implements HasForm
{
    public function __construct(private readonly SendInvoice $sendInvoice) {}

    public function name(): string
    {
        return 'send_invoice';
    }

    public function description(): string
    {
        return 'Send a draft invoice to its client. The user must type the invoice number on the confirmation card to send it.';
    }

    public function permissions(): array
    {
        return ['invoice_send'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'invoice' => ['type' => 'string', 'required' => true, 'description' => 'Invoice number (e.g. INV-104) or id.'],
        ];
    }

    public function formTitle(): string
    {
        return __('Send an invoice');
    }

    public function formFields(User $user): array
    {
        return [new FormField('invoice', __('Invoice'), 'invoice', required: true)];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $invoice = $this->find($user, (string) ($args['invoice'] ?? ''));
        $invoice->loadMissing('client:id,name,email', 'project:id,title');

        if ($invoice->status !== 'draft') {
            throw new ToolInputException(__('Invoice :number is already :status; only draft invoices are sent from here.', [
                'number' => $invoice->invoice_number, 'status' => $invoice->status,
            ]));
        }

        return new PreparedAction(
            summary: __('Send invoice :number to :client', ['number' => $invoice->invoice_number, 'client' => $invoice->client?->name ?? __('the client')]),
            details: array_filter([
                __('Invoice') => $invoice->invoice_number,
                __('Client') => $invoice->client ? "{$invoice->client->name} ({$invoice->client->email})" : null,
                __('Project') => $invoice->project?->title,
                __('Total') => number_format((float) $invoice->total_amount, 2),
                __('Due') => $invoice->due_date?->format('Y-m-d'),
            ]),
            payload: ['invoice_id' => $invoice->id],
            confirmPhrase: (string) $invoice->invoice_number,
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $invoice = ListInvoices::visible($user)->whereKey((int) $payload['invoice_id'])->first()
            ?? throw new ToolInputException(__('This invoice no longer exists or is no longer visible to you.'));

        if ($invoice->status !== 'draft') {
            throw new ToolInputException(__('Invoice :number was already sent.', ['number' => $invoice->invoice_number]));
        }

        $this->sendInvoice->handle($user, $invoice);

        return new ToolOutcome(
            __('Sent invoice :number.', ['number' => $invoice->invoice_number]),
            $invoice,
            route('invoices.show', $invoice->id, false),
        );
    }

    private function find(User $user, string $ref): Invoice
    {
        $ref = trim($ref);
        $visible = ListInvoices::visible($user);

        $invoice = (clone $visible)->where('invoice_number', $ref)->first()
            ?? (preg_match('/^#?(\d+)$/', $ref, $m) ? (clone $visible)->whereKey((int) $m[1])->first() : null);

        if ($invoice) {
            return $invoice;
        }

        $matches = (clone $visible)->where('invoice_number', 'like', '%' . addcslashes($ref, '%_\\') . '%')->limit(6)->get();

        return match (true) {
            $matches->count() === 1 => $matches->first(),
            $matches->isEmpty() => throw new ToolInputException(__('No invoice matches ":ref" that this user can see.', ['ref' => $ref])),
            default => throw new ToolInputException(__('Several invoices match ":ref": :list. Ask the user which one.', [
                'ref' => $ref, 'list' => $matches->pluck('invoice_number')->implode(', '),
            ])),
        };
    }
}
