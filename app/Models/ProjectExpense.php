<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectExpense extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'project_id',
        'budget_category_id',
        'task_id',
        'submitted_by',
        'amount',
        'currency',
        'expense_date',
        'title',
        'description',
        'vendor',
        'status',
        'is_recurring',
        'receipt_required',
        'approved_amount',
        'approved_at',
        'approved_by'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'expense_date' => 'date',
        'approved_at' => 'datetime',
        'is_recurring' => 'boolean',
        'receipt_required' => 'boolean',
    ];

    protected $appends = [
        'formatted_amount',
        'can_edit',
        'can_delete',
        'status_color',
        'days_since_submission',
        'is_overdue'
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function projectBudget(): BelongsTo
    {
        return $this->belongsTo(ProjectBudget::class, 'project_id', 'project_id');
    }

    public function budgetCategory(): BelongsTo
    {
        return $this->belongsTo(BudgetCategory::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ExpenseApproval::class)->latest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ExpenseAttachment::class);
    }

    public function getCurrentApprovalAttribute()
    {
        return $this->approvals()->where('status', 'pending')->first();
    }

    public function getIsApprovedAttribute()
    {
        return $this->status === 'approved';
    }

    public function getIsPendingAttribute()
    {
        return $this->status === 'pending';
    }

    public function getIsRejectedAttribute()
    {
        return $this->status === 'rejected';
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeForCategory($query, $categoryId)
    {
        return $query->where('budget_category_id', $categoryId);
    }

    public function scopeSubmittedBy($query, $userId)
    {
        return $query->where('submitted_by', $userId);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate]);
    }

    public function scopeInAmountRange($query, $minAmount, $maxAmount)
    {
        return $query->whereBetween('amount', [$minAmount, $maxAmount]);
    }

    /**
     * Get the formatted amount with currency
     */
    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2) . ' ' . $this->currency;
    }

    /**
     * Check if expense can be edited
     */
    public function getCanEditAttribute()
    {
        return in_array($this->status, ['pending', 'requires_info']);
    }

    /**
     * Check if expense can be deleted
     */
    public function getCanDeleteAttribute()
    {
        return $this->status === 'pending' || ($this->status === 'requires_info' && $this->submitted_by === auth()->id());
    }

    /**
     * Get the status color for UI
     */
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'pending' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            'requires_info' => 'blue',
            default => 'gray'
        };
    }

    /**
     * Get days since submission
     */
    public function getDaysSinceSubmissionAttribute()
    {
        return $this->created_at->diffInDays(now());
    }

    /**
     * Check if expense is overdue for approval
     */
    public function getIsOverdueAttribute()
    {
        return $this->status === 'pending' && $this->days_since_submission > 7; // 7 days threshold
    }

    protected function getActivityDescription(string $action): string
    {
        return match($action) {
            'created' => "Expense '{$this->title}' was submitted for {$this->formatted_amount}",
            'updated' => "Expense '{$this->title}' was updated",
            'deleted' => "Expense '{$this->title}' was deleted",
            default => parent::getActivityDescription($action)
        };
    }

    public function logStatusChange(string $oldStatus, string $newStatus)
    {
        $this->logActivity('status_changed', [
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'description' => "Expense '{$this->title}' status changed from {$oldStatus} to {$newStatus}"
        ]);
    }
}