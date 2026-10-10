<?php

namespace App\Services\Ai\Tools;

use App\Actions\Invoices\MarkInvoicePaid;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;

/**
 * Record a payment received outside a payment gateway (bank transfer,
 * cash…), like "Mark as paid" on the invoice. Money: the card needs the
 * invoice number typed.
 */
class MarkInvoicePaidTool extends AiTool implements HasForm
{
    use RevertsChanges;

    private const METHODS = ['bank_transfer', 'cash', 'cheque', 'card', 'other'];
    private const FIELDS = ['status', 'paid_amount', 'paid_at', 'payment_method', 'payment_reference'];

    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly MarkInvoicePaid $markPaid,
    ) {}

    public function name(): string
    {
        return 'mark_invoice_paid';
    }

    public function description(): string
    {
        return 'Mark a sent invoice as paid, recording a payment received outside the payment gateways. '
            . 'The amount defaults to the full total. The user must type the invoice number on the confirmation card.';
    }

    public function permissions(): array
    {
        return ['invoice_manage_payments'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'invoice' => ['type' => 'string', 'required' => true, 'description' => 'Invoice number (e.g. INV-104) or id.'],
            'amount' => ['type' => 'number', 'description' => 'Amount received. Leave out for the full total.'],
            'method' => ['type' => 'enum', 'options' => self::METHODS, 'description' => 'How it was paid.'],
            'reference' => ['type' => 'string', 'description' => 'Payment reference, e.g. a bank transaction id.'],
        ];
    }

    public function formTitle(): string
    {
        return __('Mark an invoice as paid');
    }

    public function formFields(User $user): array
    {
        return [
            new FormField('invoice', __('Invoice'), 'unpaid_invoice', required: true, question: __('Which invoice was paid?')),
            new FormField('amount', __('Amount paid'), 'number'),
            new FormField('method', __('Payment method'), 'enum', options: FormField::labels(self::METHODS)),
            new FormField('reference', __('Reference'), 'text'),
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        /** @var Invoice $invoice */
        $invoice = $this->resolver->find($this->resolver->unpaidInvoices($user), (string) ($args['invoice'] ?? ''), 'invoice_number', __('unpaid invoice'));
        $invoice->loadMissing('client:id,name');

        $total = (float) $invoice->total_amount;
        $amount = $this->resolver->number($args['amount'] ?? null, __('Amount paid'), $total) ?? $total;

        $method = !empty($args['method']) ? (string) $args['method'] : null;
        if ($method !== null && !in_array($method, self::METHODS, true)) {
            throw new ToolInputException(__('The payment method must be one of: :list.', ['list' => implode(', ', self::METHODS)]));
        }
        $reference = isset($args['reference']) && trim((string) $args['reference']) !== '' ? mb_substr(trim((string) $args['reference']), 0, 255) : null;

        return new PreparedAction(
            summary: __('Mark invoice :number as paid (:amount)', ['number' => $invoice->invoice_number, 'amount' => number_format($amount, 2)]),
            details: array_filter([
                __('Invoice') => $invoice->invoice_number,
                __('Client') => $invoice->client?->name,
                __('Total') => number_format($total, 2),
                __('Amount paid') => number_format($amount, 2),
                __('Payment method') => $method ? __(ucfirst(str_replace('_', ' ', $method))) : null,
                __('Reference') => $reference,
            ]),
            payload: ['invoice_id' => $invoice->id, 'amount' => $amount, 'method' => $method, 'reference' => $reference],
            confirmPhrase: (string) $invoice->invoice_number,
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $invoice = $this->resolver->byId($this->resolver->unpaidInvoices($user), (int) $payload['invoice_id'], __('unpaid invoice'));
        $before = [...$this->snapshot($invoice, self::FIELDS), 'paid_at' => $invoice->paid_at?->format('Y-m-d H:i:s')];

        $this->markPaid->handle($user, $invoice, (float) $payload['amount'], $payload['method'], $payload['reference']);

        return new ToolOutcome(
            __('Marked invoice :number as paid.', ['number' => $invoice->invoice_number]),
            $invoice,
            route('invoices.show', $invoice->id, false),
            $this->changeUndo($invoice, $before),
        );
    }

    public function undo(array $undo, User $user): string
    {
        $invoice = $this->resolver->byId(ListInvoices::visible($user), (int) $undo['id'], __('invoice'));
        $this->ensureUnchanged($invoice, [...$undo, 'after' => array_diff_key($undo['after'], ['paid_at' => 0])], __('Invoice :number', ['number' => $invoice->invoice_number]));

        $invoice->update($undo['before']);

        return __('Invoice :number is back to :status.', ['number' => $invoice->invoice_number, 'status' => $invoice->status]);
    }
}
