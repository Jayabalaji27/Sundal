<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; margin: 0; }
        h1 { font-size: 22px; margin: 0 0 4px 0; }
        .muted { color: #6b7280; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .header-table td { vertical-align: top; }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            background: #f3f4f6;
            color: #374151;
        }
        .section { margin-bottom: 20px; }
        .section-label { font-size: 9px; text-transform: uppercase; color: #6b7280; margin: 0 0 4px 0; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.items th {
            background: #f3f4f6;
            text-align: left;
            padding: 6px 8px;
            font-size: 9px;
            text-transform: uppercase;
            color: #6b7280;
        }
        table.items td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
        table.items td.amount, table.items th.amount { text-align: right; }
        table.totals { width: 260px; margin-left: auto; border-collapse: collapse; }
        table.totals td { padding: 4px 0; }
        table.totals td.amount { text-align: right; }
        table.totals tr.grand-total td { font-size: 13px; font-weight: bold; border-top: 1px solid #e5e7eb; padding-top: 8px; }
        .notes { margin-top: 24px; font-size: 10px; color: #4b5563; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                <h1>{{ $invoice->title ?: __('Invoice') }}</h1>
                <p class="muted" style="margin: 0;">{{ $invoice->invoice_number }}</p>
                <p style="margin: 8px 0 0 0;"><span class="badge">{{ strtoupper(str_replace('_', ' ', $invoice->status)) }}</span></p>
            </td>
            <td style="width: 40%; text-align: right;">
                <p class="section-label">{{ __('Invoice Date') }}</p>
                <p style="margin: 0 0 8px 0;">{{ optional($invoice->invoice_date)->format('M d, Y') ?? '-' }}</p>
                <p class="section-label">{{ __('Due Date') }}</p>
                <p style="margin: 0;">{{ optional($invoice->due_date)->format('M d, Y') ?? '-' }}</p>
            </td>
        </tr>
    </table>

    <table class="header-table">
        <tr>
            <td style="width: 50%;">
                <p class="section-label">{{ __('Billed To') }}</p>
                <p style="margin: 0; font-weight: bold;">{{ $invoice->client->name ?? '-' }}</p>
                @if($invoice->client?->email)
                    <p class="muted" style="margin: 0;">{{ $invoice->client->email }}</p>
                @endif
            </td>
            <td style="width: 50%;">
                <p class="section-label">{{ __('Project') }}</p>
                <p style="margin: 0; font-weight: bold;">{{ $invoice->project->title ?? '-' }}</p>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>{{ __('Description') }}</th>
                <th>{{ __('Type') }}</th>
                <th class="amount">{{ __('Rate') }}</th>
                <th class="amount">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td>{{ ucfirst($item->type) }}</td>
                    <td class="amount">{{ $currencySymbol }}{{ number_format($item->rate, 2) }}</td>
                    <td class="amount">{{ $currencySymbol }}{{ number_format($item->amount, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="muted">{{ __('No line items') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>{{ __('Subtotal') }}</td>
            <td class="amount">{{ $currencySymbol }}{{ number_format($invoice->subtotal, 2) }}</td>
        </tr>
        @if($invoice->discount_amount > 0)
            <tr>
                <td>{{ __('Discount') }}</td>
                <td class="amount">-{{ $currencySymbol }}{{ number_format($invoice->discount_amount, 2) }}</td>
            </tr>
        @endif
        @if($invoice->tax_amount > 0)
            <tr>
                <td>{{ __('Tax') }}</td>
                <td class="amount">{{ $currencySymbol }}{{ number_format($invoice->tax_amount, 2) }}</td>
            </tr>
        @endif
        <tr class="grand-total">
            <td>{{ __('Total') }}</td>
            <td class="amount">{{ $currencySymbol }}{{ number_format($invoice->total_amount, 2) }}</td>
        </tr>
        <tr>
            <td>{{ __('Paid') }}</td>
            <td class="amount">{{ $currencySymbol }}{{ number_format($invoice->paid_amount, 2) }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">{{ __('Balance Due') }}</td>
            <td class="amount" style="font-weight: bold;">{{ $currencySymbol }}{{ number_format($invoice->total_amount - $invoice->paid_amount, 2) }}</td>
        </tr>
    </table>

    @if($invoice->notes)
        <div class="notes">
            <p class="section-label">{{ __('Notes') }}</p>
            <p style="margin: 0;">{{ $invoice->notes }}</p>
        </div>
    @endif

    @if($invoice->terms)
        <div class="notes">
            <p class="section-label">{{ __('Terms') }}</p>
            <p style="margin: 0;">{{ $invoice->terms }}</p>
        </div>
    @endif
</body>
</html>
