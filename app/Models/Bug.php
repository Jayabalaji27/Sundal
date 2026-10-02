<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bug extends Model
{
    use LogsActivity;
    
    protected $fillable = [
        'project_id', 'bug_status_id', 'milestone_id', 'title', 'description',
        'priority', 'severity', 'steps_to_reproduce', 'expected_behavior', 
        'actual_behavior', 'environment', 'assigned_to', 'reported_by', 
        'resolved_by', 'start_date', 'end_date', 'resolution_notes'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date'
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function bugStatus(): BelongsTo
    {
        return $this->belongsTo(BugStatus::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class, 'milestone_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BugComment::class)->latest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(BugAttachment::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class)->through('project');
    }

    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeByStatus($query, $statusId)
    {
        return $query->where('bug_status_id', $statusId);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeForWorkspace($query, $workspaceId)
    {
        return $query->whereHas('project', function($q) use ($workspaceId) {
            $q->where('workspace_id', $workspaceId);
        });
    }

    public function scopeAccessibleByUser($query, User $user, $workspaceRole = null)
    {
        $workspaceRole = $workspaceRole ?? $user->currentWorkspace?->getMemberRole($user);

        if ($workspaceRole === 'owner') {
            return $query;
        }

        // Every non-owner role is additionally restricted to bugs whose project
        // they're allowed to see — a member assigned/reporting a bug in a
        // private project they're not on shouldn't see it via this route either.
        $query->whereHas('project', fn ($q) => $q->visibleTo($user));

        if ($workspaceRole === 'client') {
            return $query->where('reported_by', $user->id);
        }

        if ($workspaceRole === 'member') {
            return $query->where(function ($bugQuery) use ($user) {
                $bugQuery->where('assigned_to', $user->id)
                    ->orWhere('reported_by', $user->id);
            });
        }

        return $query; // manager: project visibility alone already applied above
    }

    public function isOverdue(): bool
    {
        return $this->end_date && $this->end_date->isPast() && !$this->isResolved();
    }

    public function isResolved(): bool
    {
        return in_array($this->bugStatus->name, ['Resolved', 'Closed']);
    }

    public function getActivityDescription(string $action): string
    {
        return match($action) {
            'created' => __('Bug ":title" was reported', ['title' => $this->title]),
            'updated' => __('Bug ":title" was updated', ['title' => $this->title]),
            'deleted' => __('Bug ":title" was deleted', ['title' => $this->title]),
            default => __('Bug ":title" was :action', ['title' => $this->title, 'action' => $action])
        };
    }

    public function logStatusChange(string $oldStatus, string $newStatus)
    {
        $this->logActivity('status_changed', [
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'description' => __('Bug ":title" moved from :old_status to :new_status', [
                'title' => $this->title,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ])
        ]);
    }

    public function logAssignment(User $user)
    {
        $this->logActivity('assigned', [
            'assigned_to' => $user->name,
            'description' => __('Bug ":title" was assigned to :user', [
                'title' => $this->title,
                'user' => $user->name
            ])
        ]);
    }

    public function logResolution(User $user)
    {
        $this->logActivity('resolved', [
            'resolved_by' => $user->name,
            'description' => __('Bug ":title" was resolved by :user', [
                'title' => $this->title,
                'user' => $user->name
            ])
        ]);
    }

    public function canBeViewedBy(User $user): bool
    {
        $workspace = $this->project->workspace;
        $userRole = $workspace->getMemberRole($user);
        
        if ($workspace->isOwner($user) || $userRole === 'manager') {
            return true;
        }
        
        if ($userRole === 'client') {
            return $this->reported_by === $user->id;
        }
        
        if ($userRole === 'member') {
            return $this->assigned_to === $user->id || $this->reported_by === $user->id;
        }
        
        return false;
    }

    public function canBeUpdatedBy(User $user): bool
    {
        $workspace = $this->project->workspace;
        $userRole = $workspace->getMemberRole($user);
        
        if ($workspace->isOwner($user) || $userRole === 'manager') {
            return true;
        }
        
        if ($userRole === 'client') {
            return $this->reported_by === $user->id;
        }
        
        if ($userRole === 'member') {
            return $this->assigned_to === $user->id || $this->reported_by === $user->id;
        }
        
        return false;
    }

    public function canBeDeletedBy(User $user): bool
    {
        $workspace = $this->project->workspace;
        $userRole = $workspace->getMemberRole($user);
        
        if ($workspace->isOwner($user) || $userRole === 'manager') {
            return true;
        }
        
        if ($userRole === 'client') {
            return $this->reported_by === $user->id;
        }
        
        return $this->reported_by === $user->id;
    }

    public static function getProjectMembers($projectId)
    {
        if (!$projectId) return collect();
        
        $project = Project::with('members.user')->find($projectId);
        if (!$project) return collect();
        
        return $project->members->pluck('user')->filter();
    }

    public static function getProjectMilestones($projectId)
    {
        if (!$projectId) return collect();
        
        $project = Project::with('milestones')->find($projectId);
        if (!$project) return collect();
        
        return $project->milestones;
    }
}