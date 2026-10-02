<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetRevisionApproval extends Model
{
    protected $fillable = [
        'budget_revision_id',
        'approver_id',
        'status',
        'notes',
        'approved_at'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function budgetRevision(): BelongsTo
    {
        return $this->belongsTo(BudgetRevision::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}