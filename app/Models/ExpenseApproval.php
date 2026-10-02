<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_expense_id',
        'approver_id',
        'status',
        'notes',
        'approved_at',
        'approval_level'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'approval_level' => 'integer',
    ];

    public function projectExpense(): BelongsTo
    {
        return $this->belongsTo(ProjectExpense::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function getIsApprovedAttribute()
    {
        return $this->status === 'approved';
    }

    public function getIsRejectedAttribute()
    {
        return $this->status === 'rejected';
    }

    public function getIsPendingAttribute()
    {
        return $this->status === 'pending';
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForApprover($query, $approverId)
    {
        return $query->where('approver_id', $approverId);
    }
}