<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'task_id',
        'expense_id',
        'timesheet_entry_id',
        'type',
        'description',
        'rate',
        'amount',
        'sort_order'
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(ProjectExpense::class, 'expense_id');
    }

    public function timesheetEntry(): BelongsTo
    {
        return $this->belongsTo(TimesheetEntry::class);
    }

    protected static function booted()
    {
        static::saving(function ($item) {
            $item->amount = $item->rate;
        });

        static::saved(function ($item) {
            $item->invoice->calculateTotals();
        });

        static::deleted(function ($item) {
            $item->invoice->calculateTotals();
        });
    }
}