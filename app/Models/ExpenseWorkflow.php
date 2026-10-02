<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseWorkflow extends Model
{
    protected $fillable = [
        'project_expense_id',
        'step',
        'approver_id',
        'status',
        'notes',
        'processed_at'
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'step' => 'integer'
    ];

    public function projectExpense(): BelongsTo
    {
        return $this->belongsTo(ProjectExpense::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCurrentStep($query, $step)
    {
        return $query->where('step', $step);
    }
}