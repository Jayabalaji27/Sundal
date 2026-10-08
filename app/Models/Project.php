<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use App\Models\Concerns\BelongsToWorkspace;

class Project extends Model
{
    use SoftDeletes, BelongsToWorkspace, \App\Models\Concerns\RecordsCreator;

    protected $fillable = [
        'workspace_id', 'portfolio_id', 'title', 'description', 'client_id', 'status', 'priority',
        'start_date', 'deadline', 'estimated_hours', 'actual_hours', 'budget',
        'progress', 'is_public', 'visibility', 'portal_enabled', 'portal_token', 'portal_message',
        'created_by', 'updated_by', 'shared_settings', 'password'
    ];

    protected $casts = [
        'start_date' => 'date',
        'deadline' => 'date',
        'is_public' => 'boolean',
        'progress' => 'integer',
        'estimated_hours' => 'integer',
        'actual_hours' => 'integer',
        'budget' => 'decimal:2',
        'shared_settings' => 'array',
    ];

    // Relationships
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function sprints()
    {
        return $this->hasMany(Sprint::class);
    }

    public function projectClients(): HasMany
    {
        return $this->hasMany(ProjectClient::class);
    }

    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_clients')
                    ->withPivot('assigned_at', 'assigned_by')
                    ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
                    ->withPivot('role', 'assigned_at', 'assigned_by')
                    ->withTimestamps();
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('order');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ProjectNote::class)->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProjectActivity::class)->latest();
    }

    public function budget(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProjectBudget::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ProjectExpense::class);
    }

    public function bugs(): HasMany
    {
        return $this->hasMany(Bug::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function timesheetEntries(): HasMany
    {
        return $this->hasMany(TimesheetEntry::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }



    // Scopes
    public function scopeForWorkspace(Builder $query, $workspaceId): Builder
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /**
     * Restrict to projects the given user is allowed to see. Owners see every
     * project in the workspace; everyone else sees only projects they created,
     * are assigned to (member or client), or that are explicitly workspace-wide.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $workspace = $user->currentWorkspace;

        if ($workspace && $workspace->isOwner($user)) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('visibility', 'workspace')
                ->orWhere('created_by', $user->id)
                ->orWhereHas('members', fn ($m) => $m->where('user_id', $user->id))
                ->orWhereHas('clients', fn ($c) => $c->where('user_id', $user->id));
        });
    }

    /**
     * IDs of the workspace projects the client is assigned to whose shared
     * settings switch on the given section (e.g. 'task'). Clients only see a
     * project section when the owner has shared it with them.
     */
    public static function idsSharedWithClient(User $client, int $workspaceId, string $section): array
    {
        return static::where('workspace_id', $workspaceId)
            ->whereHas('clients', fn ($q) => $q->where('user_id', $client->id))
            ->get(['id', 'shared_settings'])
            ->filter(fn ($project) => !empty($project->shared_settings[$section]))
            ->pluck('id')
            ->all();
    }

    public function scopeSearch(Builder $query, $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    public function scopeByStatus(Builder $query, $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByPriority(Builder $query, $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    // Helper methods
    public function isOverdue(): bool
    {
        return $this->deadline && $this->deadline->isPast() && $this->status !== 'completed';
    }

    public function calculateProgress(): int
    {
        $milestones = $this->milestones;
        if ($milestones->isEmpty()) {
            // Calculate progress based on tasks if no milestones
            return $this->calculateProgressFromTasks();
        }
        
        // Only count milestones that have assigned tasks
        $milestonesWithTasks = $milestones->filter(function ($milestone) {
            return $milestone->tasks()->count() > 0;
        });
        
        if ($milestonesWithTasks->isEmpty()) {
            // Calculate progress based on tasks if no milestones have tasks
            return $this->calculateProgressFromTasks();
        }
        
        return (int) $milestonesWithTasks->avg('progress'); // Average of milestone progress with tasks
    }

    public function calculateProgressFromTasks(): int
    {
        $tasks = $this->tasks;
        if ($tasks->isEmpty()) {
            return $this->progress; // Return manual progress if no tasks
        }
        
        return (int) $tasks->avg('progress');
    }

    public function updateProgressFromMilestones(): void
    {
        $calculatedProgress = $this->calculateProgress();
        $this->update(['progress' => $calculatedProgress]);
    }

    public function logActivity(string $action, string $description, array $metadata = [], $userId = null): void
    {
        $this->activities()->create([
            'user_id' => $userId ?? auth()->id() ?? $this->created_by,
            'action' => $action,
            'description' => $description,
            'metadata' => array_merge($metadata, \App\Services\Ai\AiActionContext::metadata())
        ]);
    }

    /**
     * Boot the model and set up event listeners for cascade deletion
     */
    /**
     * Counts of records that go with this project when it's deleted (shown in the
     * delete confirmation). Invoices are financial records and are never deleted
     * with a project - a project that has any blocks deletion instead.
     */
    public function deletionSummary(): array
    {
        return [
            'tasks' => $this->tasks()->count(),
            'bugs' => $this->bugs()->count(),
            'milestones' => $this->milestones()->count(),
            'budgets' => $this->budget()->count(),
            'expenses' => $this->expenses()->count(),
            'timesheet_entries' => $this->timesheetEntries()->count(),
            'attachments' => $this->attachments()->count(),
            'notes' => $this->notes()->count(),
            'invoices' => $this->invoices()->count(),
        ];
    }

    protected static function booted()
    {
        static::deleting(function ($project) {
            DB::transaction(function () use ($project) {
                // Delete all tasks and their related data
                $tasks = $project->tasks()->with(['comments', 'checklists', 'attachments', 'timesheetEntries'])->get();
                foreach ($tasks as $task) {
                    // Delete task comments
                    $task->comments()->delete();
                    
                    // Delete task checklists
                    $task->checklists()->delete();
                    
                    // Delete task attachments (files will be handled by media library)
                    $task->attachments()->delete();
                    
                    // Delete timesheet entries for this task
                    $task->timesheetEntries()->delete();
                    
                    // Delete task member assignments
                    $task->members()->detach();
                    
                    // Delete the task itself
                    $task->delete();
                }
                
                // Delete bugs and their comments/attachments
                foreach ($project->bugs()->get() as $bug) {
                    $bug->comments()->delete();
                    $bug->attachments()->delete();
                    $bug->delete();
                }

                // Delete project attachments
                $project->attachments()->delete();
                
                // Delete project notes
                $project->notes()->delete();
                
                // Delete project activities
                $project->activities()->delete();
                
                // Delete project milestones
                $project->milestones()->delete();
                
                // Delete project budget and related expenses
                $budget = $project->budget();
                if ($budget->exists()) {
                    $budgetModel = $budget->first();
                    // Delete budget categories
                    $budgetModel->categories()->delete();
                    
                    // Delete budget revisions
                    $budgetModel->revisions()->delete();
                    
                    // Delete the budget itself
                    $budgetModel->delete();
                }
                
                // Delete project expenses
                $expenses = $project->expenses()->with(['approvals', 'attachments'])->get();
                foreach ($expenses as $expense) {
                    // Delete expense approvals
                    $expense->approvals()->delete();
                    
                    // Delete expense attachments
                    $expense->attachments()->delete();
                    
                    // Delete the expense itself
                    $expense->delete();
                }
                
                // Delete project member assignments
                $project->members()->delete();
                
                // Delete project client assignments
                $project->projectClients()->delete();
                
                // Delete timesheet entries related to this project
                $project->timesheetEntries()->delete();
            });
        });
    }
}