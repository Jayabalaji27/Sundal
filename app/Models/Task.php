<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Task extends Model
{
    use LogsActivity;
    protected $fillable = [
        'project_id', 'task_stage_id', 'milestone_id', 'title', 'description',
        'priority', 'start_date', 'end_date', 'assigned_to', 'created_by', 'progress'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'progress' => 'integer'
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function taskStage(): BelongsTo
    {
        return $this->belongsTo(TaskStage::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class, 'milestone_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->latest();
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(TaskChecklist::class)->orderBy('order');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_members')
                    ->withPivot('assigned_by', 'assigned_at')
                    ->withTimestamps();
    }

    public function timesheetEntries(): HasMany
    {
        return $this->hasMany(TimesheetEntry::class);
    }

    /**
     * Tasks the user can see (the Tasks page's rule, shared with the dashboard):
     * tasks in projects visible to them, and for members only the tasks they're
     * assigned to or created unless they're looking inside one project.
     */
    public function scopeVisibleTo($query, User $user, bool $onlyOwnForMembers = true)
    {
        $query->whereHas('project', fn ($q) => $q->forWorkspace($user->current_workspace_id)->visibleTo($user));

        if ($onlyOwnForMembers && $user->currentWorkspace?->getMemberRole($user) === 'member') {
            $query->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhere('created_by', $user->id));
        }

        return $query;
    }

    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeByStage($query, $stageId)
    {
        return $query->where('task_stage_id', $stageId);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function isCompleted(): bool
    {
        return $this->progress >= 100 || (bool) $this->taskStage?->is_completed;
    }

    public function isOverdue(): bool
    {
        return $this->end_date && $this->end_date->lt(now()->startOfDay()) && !$this->isCompleted();
    }

    /** Tasks that aren't finished: not at 100% and not sitting in a completed stage. */
    public function scopeOpen($query)
    {
        return $query->where('progress', '<', 100)
            ->where(function ($q) {
                $q->whereNull('task_stage_id')
                    ->orWhereIn('task_stage_id', TaskStage::withoutGlobalScopes()->where('is_completed', false)->select('id'));
            });
    }

    public function calculateProgress(): int
    {
        $checklists = $this->checklists;
        if ($checklists->isEmpty()) {
            return $this->progress;
        }

        $completed = $checklists->where('is_completed', true)->count();
        return (int) (($completed / $checklists->count()) * 100);
    }

    /** Re-derive progress from the checklist after an item is added, removed or toggled. */
    public function syncProgressFromChecklists(): void
    {
        $this->unsetRelation('checklists');
        $this->update(['progress' => $this->calculateProgress()]);
    }

    protected function getActivityDescription(string $action): string
    {
        return match($action) {
            'created' => __('Task ":title" was created', ['title' => $this->title]),
            'updated' => __('Task ":title" was updated', ['title' => $this->title]),
            'deleted' => __('Task ":title" was deleted', ['title' => $this->title]),
            default => parent::getActivityDescription($action)
        };
    }

    public function logStatusChange(string $oldStage, string $newStage)
    {
        $this->logActivity('stage_changed', [
            'old_stage' => $oldStage,
            'new_stage' => $newStage,
            'description' => __('Task ":title" moved from :old_stage to :new_stage', [
                'title' => $this->title,
                'old_stage' => $oldStage,
                'new_stage' => $newStage
            ])
        ]);
    }

    public function logAssignment(User $user)
    {
        $this->logActivity('assigned', [
            'assigned_to' => $user->name,
            'description' => __('Task ":title" was assigned to :user', [
                'title' => $this->title,
                'user' => $user->name
            ])
        ]);
    }

    public function updateMilestoneProgress(): void
    {
        if ($this->milestone_id) {
            $this->milestone->updateProgressFromTasks();
        }
        
        // Always update project progress
        $this->project->updateProgressFromMilestones();
    }

    protected static function booted()
    {
        // Moving a task into a completed stage ("Done") completes it; moving it back
        // out of one re-opens it. Covers every path that changes the stage (kanban
        // drag, edit form, dashboard quick-update).
        static::saving(function (Task $task) {
            if (!$task->task_stage_id || !$task->isDirty('task_stage_id')) {
                return;
            }

            $isCompletedStage = (bool) TaskStage::withoutGlobalScopes()->whereKey($task->task_stage_id)->value('is_completed');

            if ($isCompletedStage) {
                $task->progress = 100;
            } elseif ($task->getOriginal('task_stage_id')
                && (int) $task->progress === 100
                && TaskStage::withoutGlobalScopes()->whereKey($task->getOriginal('task_stage_id'))->value('is_completed')) {
                $task->progress = 0;
            }
        });

        static::updated(function ($task) {
            $task->updateMilestoneProgress();
        });
        
        static::created(function ($task) {
            $task->updateMilestoneProgress();
        });
        
        static::deleted(function ($task) {
            $task->updateMilestoneProgress();
        });
    }
}