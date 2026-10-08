<?php

namespace App\Actions\Projects;

use App\Actions\ActionException;
use App\Events\ProjectMemberAssigned;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\Workspace;

/**
 * Adds people to a project with a project role. The workspace owner may give
 * any role; a manager may only add members/clients, and only to projects
 * they manage. Shared by ProjectController and the AI assistant.
 */
class AssignProjectMembers
{
    /** @return string[] project roles the actor may hand out on this project */
    public function allowedRoles(User $actor, Project $project): array
    {
        $workspace = Workspace::find($actor->current_workspace_id);
        if (!$workspace || (int) $project->workspace_id !== (int) $workspace->id) {
            throw new ActionException(__('Project not found in your workspace.'));
        }

        if ($workspace->isOwner($actor)) {
            return ['owner', 'manager', 'member', 'client'];
        }

        if ($workspace->getMemberRole($actor) === 'manager') {
            $manages = (int) $project->created_by === (int) $actor->id
                || $project->members()->where('user_id', $actor->id)->whereIn('role', ['owner', 'manager'])->exists();
            if (!$manages) {
                throw new ActionException(__('You can only staff projects you manage.'));
            }

            // Managers staff a project with members/clients, never owner/manager.
            return ['member', 'client'];
        }

        throw new ActionException(__('You are not allowed to assign project members.'));
    }

    /** @param  User[]  $users */
    public function handle(User $actor, Project $project, iterable $users, string $role = 'member'): void
    {
        if (!in_array($role, $this->allowedRoles($actor, $project), true)) {
            throw new ActionException(__('You are not allowed to assign that project role.'));
        }

        $names = [];
        foreach ($users as $user) {
            ProjectMember::updateOrCreate(
                ['project_id' => $project->id, 'user_id' => $user->id],
                ['role' => $role, 'assigned_by' => $actor->id]
            );
            $names[] = $user->name;

            if (!config('app.is_demo', true)) {
                event(new ProjectMemberAssigned($project, $user, $actor, $role));
            }
        }

        $project->logActivity('members_assigned', "Members '" . implode(', ', $names) . "' were assigned to project", [], $actor->id);
    }
}
