<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskChecklist extends Model
{
    protected $fillable = [
        'task_id',
        'title',
        'is_completed',
        'order',
        'assigned_to',
        'due_date',
        'created_by'
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'order' => 'integer',
        'due_date' => 'date'
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_completed', false);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public function toggle(): void
    {
        $this->update(['is_completed' => !$this->is_completed]);

        // Update parent task progress
        $this->task->update([
            'progress' => $this->task->calculateProgress()
        ]);
    }

    public function canBeUpdatedBy(User $user): bool
    {
        // Workspace owner can update any checklist
        $workspace = $this->task->project->workspace;
        if ($workspace->owner_id === $user->id) {
            return true;
        }

        // Members can only update checklists they created
        // Allow if created_by is null (legacy data)
        return $this->created_by === $user->id || $this->created_by === null;
    }

    public function canBeDeletedBy(User $user): bool
    {
        // Workspace owner can delete any checklist
        $workspace = $this->task->project->workspace;
        if ($workspace->owner_id === $user->id) {
            return true;
        }

        // Members can only delete checklists they created
        // Allow if created_by is null (legacy data)
        return $this->created_by === $user->id || $this->created_by === null;
    }
}
