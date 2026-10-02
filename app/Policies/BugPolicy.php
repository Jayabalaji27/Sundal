<?php

namespace App\Policies;

use App\Models\Bug;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BugPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->currentWorkspace && $user->currentWorkspace->hasMember($user);
    }

    public function view(User $user, Bug $bug): bool
    {
        return $bug->canBeViewedBy($user);
    }

    public function create(User $user): bool
    {
        $workspace = $user->currentWorkspace;
        if (!$workspace || !$workspace->hasMember($user)) {
            return false;
        }
        
        $userRole = $workspace->getMemberRole($user);
        return in_array($userRole, ['owner', 'manager', 'member', 'client']);
    }

    public function update(User $user, Bug $bug): bool
    {
        return $bug->canBeUpdatedBy($user);
    }

    public function delete(User $user, Bug $bug): bool
    {
        return $bug->canBeDeletedBy($user);
    }

    public function changeStatus(User $user, Bug $bug): bool
    {
        return $bug->canBeUpdatedBy($user);
    }

    public function assign(User $user, Bug $bug): bool
    {
        $workspace = $bug->project->workspace;
        $userRole = $workspace->getMemberRole($user);
        
        return $workspace->isOwner($user) || in_array($userRole, ['manager']);
    }
}