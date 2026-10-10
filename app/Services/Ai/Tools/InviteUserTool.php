<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;
use App\Actions\ActionException;
use App\Actions\Workspace\InviteToWorkspace;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PlanLimitService;

class InviteUserTool extends AiTool implements HasForm
{
    public function __construct(private readonly InviteToWorkspace $invite) {}

    public function name(): string
    {
        return 'invite_user';
    }

    public function description(): string
    {
        return 'Invite someone by email to this workspace as a manager, member or client (managers may only invite members). Shows a confirmation card; the invitation is sent only after the user confirms.';
    }

    public function permissions(): array
    {
        return ['team_invite'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'email' => ['type' => 'string', 'required' => true, 'description' => 'Email address to invite.'],
            'role' => ['type' => 'enum', 'options' => ['manager', 'member', 'client'], 'required' => true, 'description' => 'Workspace role.'],
        ];
    }

    public function formTitle(): string
    {
        return __('Invite someone');
    }

    public function formFields(User $user): array
    {
        $workspace = Workspace::find($user->current_workspace_id);
        $roles = $workspace ? InviteToWorkspace::rolesActorMayInvite($user, $workspace) : [];

        return [
            new FormField('email', __('Email'), 'text', required: true, question: __('Which email address should get the invitation?')),
            new FormField('role', __('Role'), 'enum', required: true, options: FormField::labels($roles), question: __('Which role should they have?')),
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $email = mb_strtolower(trim((string) ($args['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ToolInputException(__('That is not a valid email address. Ask the user for the full address.'));
        }

        $role = (string) ($args['role'] ?? '');
        $workspace = Workspace::find($user->current_workspace_id);
        if (!$workspace || !in_array($role, InviteToWorkspace::rolesActorMayInvite($user, $workspace), true)) {
            throw new ToolInputException(__('This user may not invite someone as :role.', ['role' => $role ?: '?']));
        }

        $existing = User::where('email', $email)->first();
        if ($existing && ($workspace->hasMember($existing) || $workspace->owner_id === $existing->id)) {
            throw new ToolInputException(__(':email is already a member of this workspace.', ['email' => $email]));
        }

        // Same plan check WorkspaceService runs on confirm, so no card is shown
        // for an invitation the plan will refuse anyway.
        if (isSaasMode()) {
            $limit = app(PlanLimitService::class)->canAddUserToWorkspace($workspace, $role);
            if (!$limit['allowed']) {
                throw new ToolInputException($limit['message']);
            }
        }

        return new PreparedAction(
            summary: __('Invite :email as :role', ['email' => $email, 'role' => $role]),
            details: [__('Email') => $email, __('Role') => ucfirst($role), __('Workspace') => $workspace->name],
            payload: ['email' => $email, 'role' => $role],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        try {
            $result = $this->invite->handle($user, $payload['email'], $payload['role']);
        } catch (ActionException $e) {
            throw new ToolInputException($e->getMessage());
        }

        $message = $result['resent']
            ? __('Updated and resent the pending invitation to :email.', ['email' => $payload['email']])
            : __('Invited :email as :role.', ['email' => $payload['email'], 'role' => $payload['role']]);

        if (!$result['email_configured']) {
            $message .= ' ' . __('Email is not configured, so share this link with them: :link', [
                'link' => route('invitations.show', $result['invitation']->token),
            ]);
        }

        return new ToolOutcome($message, $result['invitation'], route('team.index', $user->current_workspace_id, false));
    }
}
