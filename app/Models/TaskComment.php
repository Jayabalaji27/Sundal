<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskComment extends Model
{
    protected $fillable = [
        'task_id', 'user_id', 'comment', 'mentions'
    ];

    protected $casts = [
        'mentions' => 'array'
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForTask($query, $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    public function canBeUpdatedBy(User $user): bool
    {
        // Comment creator can always update
        if ($this->user_id === $user->id) {
            return true;
        }

        // Workspace owner can update any comment
        $workspace = $this->task->project->workspace;
        return $workspace->owner_id === $user->id;
    }

    public function canBeDeletedBy(User $user): bool
    {
        // Comment creator can always delete
        if ($this->user_id === $user->id) {
            return true;
        }

        // Workspace owner can delete any comment
        $workspace = $this->task->project->workspace;
        return $workspace->owner_id === $user->id;
    }
}