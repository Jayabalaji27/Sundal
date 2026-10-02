<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_budget_id',
        'revised_by',
        'previous_amount',
        'new_amount',
        'reason',
        'status',
        'approved_by',
        'approved_at'
    ];

    protected $casts = [
        'previous_amount' => 'decimal:2',
        'new_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function projectBudget(): BelongsTo
    {
        return $this->belongsTo(ProjectBudget::class);
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revised_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getChangeAmountAttribute()
    {
        return $this->new_amount - $this->previous_amount;
    }

    public function getChangePercentageAttribute()
    {
        return $this->previous_amount > 0 ? (($this->change_amount / $this->previous_amount) * 100) : 0;
    }

    public function getIsIncreaseAttribute()
    {
        return $this->change_amount > 0;
    }

    public function getIsDecreaseAttribute()
    {
        return $this->change_amount < 0;
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}