<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ProjectMilestone extends Model
{
    protected $fillable = [
        'project_id', 'title', 'description', 'due_date', 'status',
        'progress', 'order', 'created_by', 'completed_at', 'completed_by'
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'progress' => 'integer',
        'order' => 'integer'
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('due_date', '<', now())
                    ->where('status', '!=', 'completed');
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'progress' => 100,
            'completed_at' => now(),
            'completed_by' => auth()->id()
        ]);

        $this->project->logActivity(
            'milestone_completed',
            __('Milestone ":title" was completed', ['title' => $this->title])
        );
    }

    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->status !== 'completed';
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'milestone_id');
    }

    public function calculateProgress(): int
    {
        $tasks = $this->tasks;
        if ($tasks->isEmpty()) {
            return $this->progress ?? 0; // Return 0 if no tasks and no manual progress
        }
        return (int) ($tasks->avg('progress') ?? 0); // Average of task progress, default to 0
    }

    public function updateProgressFromTasks(): void
    {
        $calculatedProgress = $this->calculateProgress();
        $this->update(['progress' => $calculatedProgress]);
        
        // Update project progress when milestone progress changes
        $this->project->updateProgressFromMilestones();
    }
}