<?php

namespace App\Services\Ai\Tools;

use App\Models\Payment;
use App\Models\User;

/**
 * "How much did we bill / collect in September?" Billed = invoices issued in
 * the period (drafts and cancelled excluded); collected = payments received.
 * Uses the same invoice visibility as the Invoices screen.
 */
class GetRevenueSummary extends AiTool
{
    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'get_revenue_summary';
    }

    public function description(): string
    {
        return 'Revenue for a period: amount billed (invoices issued), amount collected (payments received), outstanding balance, and the split by project.';
    }

    public function permissions(): array
    {
        return ['invoice_view_any'];
    }

    public function parameters(): array
    {
        return [
            'from' => ['type' => 'string', 'required' => true, 'description' => 'Start date, YYYY-MM-DD.'],
            'to' => ['type' => 'string', 'required' => true, 'description' => 'End date, YYYY-MM-DD.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $from = $this->resolver->date($args['from'] ?? null, __('From'))
            ?? throw new ToolInputException(__('Give a start date.'));
        $to = $this->resolver->date($args['to'] ?? null, __('To'))
            ?? throw new ToolInputException(__('Give an end date.'));

        $issued = ListInvoices::visible($user)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereBetween('invoice_date', [$from, $to])
            ->with('project:id,title')
            ->get();

        $visibleIds = ListInvoices::visible($user)->select('id');
        $collected = (float) Payment::whereIn('invoice_id', $visibleIds)->whereBetween('payment_date', [$from, $to])->sum('amount');

        $outstanding = (float) ListInvoices::visible($user)
            ->whereIn('status', ['sent', 'viewed', 'partial_paid', 'overdue'])
            ->get()
            ->sum(fn ($i) => (float) $i->total_amount - (float) $i->paid_amount);

        return [
            'from' => $from,
            'to' => $to,
            'billed' => round((float) $issued->sum('total_amount'), 2),
            'invoices_issued' => $issued->count(),
            'collected' => round($collected, 2),
            'outstanding_now' => round($outstanding, 2),
            'billed_by_project' => $issued->groupBy(fn ($i) => $i->project?->title ?? 'No project')
                ->map(fn ($rows) => round((float) $rows->sum('total_amount'), 2))
                ->sortDesc()
                ->all(),
            'note' => 'Amounts are in each invoice\'s own currency; mixed currencies are added as-is.',
        ];
    }
}
