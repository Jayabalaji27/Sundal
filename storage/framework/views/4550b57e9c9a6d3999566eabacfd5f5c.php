<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice <?php echo e($invoice->invoice_number); ?></title>
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
                <h1><?php echo e($invoice->title ?: __('Invoice')); ?></h1>
                <p class="muted" style="margin: 0;"><?php echo e($invoice->invoice_number); ?></p>
                <p style="margin: 8px 0 0 0;"><span class="badge"><?php echo e(strtoupper(str_replace('_', ' ', $invoice->status))); ?></span></p>
            </td>
            <td style="width: 40%; text-align: right;">
                <p class="section-label"><?php echo e(__('Invoice Date')); ?></p>
                <p style="margin: 0 0 8px 0;"><?php echo e(optional($invoice->invoice_date)->format('M d, Y') ?? '-'); ?></p>
                <p class="section-label"><?php echo e(__('Due Date')); ?></p>
                <p style="margin: 0;"><?php echo e(optional($invoice->due_date)->format('M d, Y') ?? '-'); ?></p>
            </td>
        </tr>
    </table>

    <table class="header-table">
        <tr>
            <td style="width: 50%;">
                <p class="section-label"><?php echo e(__('Billed To')); ?></p>
                <p style="margin: 0; font-weight: bold;"><?php echo e($invoice->client->name ?? '-'); ?></p>
                <?php if($invoice->client?->email): ?>
                    <p class="muted" style="margin: 0;"><?php echo e($invoice->client->email); ?></p>
                <?php endif; ?>
            </td>
            <td style="width: 50%;">
                <p class="section-label"><?php echo e(__('Project')); ?></p>
                <p style="margin: 0; font-weight: bold;"><?php echo e($invoice->project->title ?? '-'); ?></p>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th><?php echo e(__('Description')); ?></th>
                <th><?php echo e(__('Type')); ?></th>
                <th class="amount"><?php echo e(__('Rate')); ?></th>
                <th class="amount"><?php echo e(__('Amount')); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $invoice->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><?php echo e($item->description); ?></td>
                    <td><?php echo e(ucfirst($item->type)); ?></td>
                    <td class="amount"><?php echo e($currencySymbol); ?><?php echo e(number_format($item->rate, 2)); ?></td>
                    <td class="amount"><?php echo e($currencySymbol); ?><?php echo e(number_format($item->amount, 2)); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="4" class="muted"><?php echo e(__('No line items')); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td><?php echo e(__('Subtotal')); ?></td>
            <td class="amount"><?php echo e($currencySymbol); ?><?php echo e(number_format($invoice->subtotal, 2)); ?></td>
        </tr>
        <?php if($invoice->discount_amount > 0): ?>
            <tr>
                <td><?php echo e(__('Discount')); ?></td>
                <td class="amount">-<?php echo e($currencySymbol); ?><?php echo e(number_format($invoice->discount_amount, 2)); ?></td>
            </tr>
        <?php endif; ?>
        <?php if($invoice->tax_amount > 0): ?>
            <tr>
                <td><?php echo e(__('Tax')); ?></td>
                <td class="amount"><?php echo e($currencySymbol); ?><?php echo e(number_format($invoice->tax_amount, 2)); ?></td>
            </tr>
        <?php endif; ?>
        <tr class="grand-total">
            <td><?php echo e(__('Total')); ?></td>
            <td class="amount"><?php echo e($currencySymbol); ?><?php echo e(number_format($invoice->total_amount, 2)); ?></td>
        </tr>
        <tr>
            <td><?php echo e(__('Paid')); ?></td>
            <td class="amount"><?php echo e($currencySymbol); ?><?php echo e(number_format($invoice->paid_amount, 2)); ?></td>
        </tr>
        <tr>
            <td style="font-weight: bold;"><?php echo e(__('Balance Due')); ?></td>
            <td class="amount" style="font-weight: bold;"><?php echo e($currencySymbol); ?><?php echo e(number_format($invoice->total_amount - $invoice->paid_amount, 2)); ?></td>
        </tr>
    </table>

    <?php if($invoice->notes): ?>
        <div class="notes">
            <p class="section-label"><?php echo e(__('Notes')); ?></p>
            <p style="margin: 0;"><?php echo e($invoice->notes); ?></p>
        </div>
    <?php endif; ?>

    <?php if($invoice->terms): ?>
        <div class="notes">
            <p class="section-label"><?php echo e(__('Terms')); ?></p>
            <p style="margin: 0;"><?php echo e($invoice->terms); ?></p>
        </div>
    <?php endif; ?>
</body>
</html>
<?php /**PATH C:\Users\vgopalakrishnan\Desktop\AI\sundal\main-file\resources\views/pdf/invoice.blade.php ENDPATH**/ ?>