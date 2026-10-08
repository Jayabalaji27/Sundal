<?php

namespace App\Actions\Workspace;

use App\Actions\ActionException;
use App\Events\WorkspaceInvited;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\MailConfigService;
use App\Services\WorkspaceService;

/**
 * Invites someone to the actor's workspace with a role the actor may give
 * (owner: manager/member/client; manager: member only). A pending invitation
 * to the same email is updated and resent instead. Same rules as
 * WorkspaceInvitationController::store, through the shared WorkspaceService.
 */
class InviteToWorkspace
{
    public function __construct(private readonly WorkspaceService $workspaces) {}

    /** @return string[] */
    public static function rolesActorMayInvite(User $actor, Workspace $workspace): array
    {
        $role = $workspace->isOwner($actor) ? 'owner' : $workspace->getMemberRole($actor);

        return match ($role) {
            'owner' => ['manager', 'member', 'client'],
            'manager' => ['member'],
            default => [],
        };
    }

    /** @return array{invitation: WorkspaceInvitation, resent: bool, email_configured: bool} */
    public function handle(User $actor, string $email, string $role): array
    {
        $workspace = Workspace::find($actor->current_workspace_id)
            ?? throw new ActionException(__('No workspace found. Please select a workspace.'));

        if (!in_array($role, self::rolesActorMayInvite($actor, $workspace), true)) {
            throw new ActionException(__('You are not allowed to invite a member with that role.'));
        }

        $emailConfigured = MailConfigService::isEmailConfigured($workspace->owner_id ?? $actor->id, $workspace->id);

        $existing = WorkspaceInvitation::where('workspace_id', $workspace->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->first();

        if ($existing) {
            if ($existing->role !== $role) {
                $existing->update(['role' => $role]);
            }
            $existing->load(['workspace', 'invitedBy']);
            if ($emailConfigured && !config('app.is_demo', true)) {
                event(new WorkspaceInvited($existing));
            }

            return ['invitation' => $existing, 'resent' => true, 'email_configured' => $emailConfigured];
        }

        try {
            $invitation = $this->workspaces->inviteUser($workspace, $email, $role, $actor);
        } catch (\Exception $e) {
            // Already a member, plan limit reached, and similar: safe to show.
            throw new ActionException($e->getMessage());
        }

        return ['invitation' => $invitation, 'resent' => false, 'email_configured' => $emailConfigured];
    }
}
