<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Workspace extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'owner_id', 'settings', 'is_active',
        'timesheet_enabled', 'timesheet_approval_required', 'timesheet_auto_submit',
        'timesheet_reminder_days', 'default_work_start', 'default_work_end'
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'timesheet_enabled' => 'boolean',
        'timesheet_auto_submit' => 'boolean',
        'timesheet_reminder_days' => 'integer',
        'default_work_start' => 'datetime:H:i',
        'default_work_end' => 'datetime:H:i'
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('status', 'active');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    public function pendingInvitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class)->whereNull('accepted_at');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
                    ->withPivot('role', 'status', 'joined_at')
                    ->withTimestamps();
    }

    public function taskStages(): HasMany
    {
        return $this->hasMany(\App\Models\TaskStage::class);
    }

    public function bugStatuses(): HasMany
    {
        return $this->hasMany(\App\Models\BugStatus::class);
    }

    public function contractTypes(): HasMany
    {
        return $this->hasMany(\App\Models\ContractType::class);
    }

    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function getMemberRole(User $user): ?string
    {
        $member = $this->members()->where('user_id', $user->id)->first();
        return $member?->role;
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class);
    }
    

    
    /**
     * Get available roles for invitation (excluding roles at limit)
     */
    public function getAvailableInvitationRoles(): array
    {
        $roles = ['member', 'manager', 'client'];
        
        // In non-SaaS mode, all roles are available
        if (!isSaasMode()) {
            return $roles;
        }
        
        $planLimitService = app(\App\Services\PlanLimitService::class);
        
        return array_values(array_filter($roles, function($role) use ($planLimitService) {
            $result = $planLimitService->canAddUserToWorkspace($this, $role);
            return $result['allowed'];
        }));
    }

}