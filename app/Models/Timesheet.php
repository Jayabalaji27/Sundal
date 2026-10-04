<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Concerns\BelongsToWorkspace;

class Timesheet extends Model
{
    use LogsActivity, BelongsToWorkspace;
    protected $fillable = [
        'user_id', 'workspace_id', 'start_date', 'end_date', 'status',
        'total_hours', 'billable_hours', 'notes', 'submitted_at', 'approved_at', 'approved_by'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_hours' => 'decimal:2',
        'billable_hours' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TimesheetEntry::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(TimesheetApproval::class);
    }

    /** The most recent approval round - carries the rejection reason. */
    public function latestApproval(): HasOne
    {
        return $this->hasOne(TimesheetApproval::class)->latestOfMany();
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('start_date', [$startDate, $endDate]);
    }

    public function canSubmit(): bool
    {
        return $this->status === 'draft' && $this->entries()->exists();
    }

    public function canApprove(): bool
    {
        return $this->status === 'submitted';
    }

    /** Submitted/approved timesheets are frozen; rejecting one reopens it for edits. */
    public function isLocked(): bool
    {
        return in_array($this->status, ['submitted', 'approved'], true);
    }

    public function calculateTotals(): void
    {
        $entries = $this->entries;
        $this->total_hours = $entries->sum('hours');
        $this->billable_hours = $entries->where('is_billable', true)->sum('hours');
        $this->save();
    }

    protected function getProjectId()
    {
        return $this->entries()->first()?->task?->project_id;
    }

    protected function getActivityDescription(string $action): string
    {
        $period = $this->start_date->format('M d') . ' - ' . $this->end_date->format('M d, Y');
        return match($action) {
            'created' => __('Timesheet for :period was created (:hours hours)', [
                'period' => $period,
                'hours' => $this->total_hours
            ]),
            'updated' => __('Timesheet for :period was updated', ['period' => $period]),
            'deleted' => __('Timesheet for :period was deleted', ['period' => $period]),
            default => parent::getActivityDescription($action)
        };
    }

    public function logStatusChange(string $oldStatus, string $newStatus)
    {
        $period = $this->start_date->format('M d') . ' - ' . $this->end_date->format('M d, Y');
        $this->logActivity('status_changed', [
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'description' => __('Timesheet for :period status changed from :old_status to :new_status', [
                'period' => $period,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ])
        ]);
    }
}