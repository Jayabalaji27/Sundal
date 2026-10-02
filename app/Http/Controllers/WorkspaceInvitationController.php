<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\WorkspaceService;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkspaceInvitationController extends Controller
{
    use HasPermissionChecks;

    public function __construct(private WorkspaceService $workspaceService)
    {
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->authorizePermission('team_invite');

        $inviter = auth()->user();
        if (!$inviter->canAccessWorkspace($workspace)) {
            abort(403);
        }

        $request->validate([
            'email' => 'required|email',
            // 'owner' is a syntactically valid role here so the escalation
            // check below can reject it with a clear 403 rather than a plain
            // validation error — there is never a legitimate reason to invite
            // a second owner.
            'role' => 'required|in:owner,manager,member,client'
        ]);

        // Role-escalation guard: validate the TARGET role against the INVITER's
        // own role server-side. A Manager editing the request payload to say
        // role=manager (or role=owner) must still be rejected here — the
        // client-side form only hiding the option is not enforcement.
        $inviterRole = $workspace->isOwner($inviter) ? 'owner' : $workspace->getMemberRole($inviter);
        $allowedRolesToInvite = match ($inviterRole) {
            'owner' => ['manager', 'member', 'client'],
            'manager' => ['member'],
            default => [],
        };
        if (!in_array($request->role, $allowedRolesToInvite, true)) {
            abort(403, __('You are not allowed to invite a member with that role.'));
        }

        // Whether email is properly configured for this workspace. The
        // invitation is still created either way — an unconfigured mailer
        // isn't a reason to block the invite, since it works fine via the
        // shareable link (see the `warning` + `invitation_link` flash below,
        // consumed by FlashMessages.tsx's "Copy Invitation Link" toast) —
        // same fallback pattern as CompanyController/UserController welcome
        // emails.
        $userId = $workspace->owner_id ?? auth()->id();
        $emailConfigured = \App\Services\MailConfigService::isEmailConfigured($userId, $workspace->id);
        $emailWarning = __('The invitation was created, but no email could be sent because email settings are not configured. Share the link below with the invitee instead.');

        // Check if invitation already exists
        $existingInvitation = WorkspaceInvitation::where('workspace_id', $workspace->id)
            ->where('email', $request->email)
            ->where('accepted_at', null)
            ->first();
        if ($existingInvitation) {
            // Update existing invitation role if different
            if ($existingInvitation->role !== $request->role) {
                $existingInvitation->update(['role' => $request->role]);
            }
            // Resend existing invitation using event
            $existingInvitation->load(['workspace', 'invitedBy']);
            if ($emailConfigured && !config('app.is_demo', true)) {
                event(new \App\Events\WorkspaceInvited($existingInvitation));
            }

            if (!$emailConfigured) {
                return back()->with('warning', $emailWarning)
                    ->with('invitation_link', route('invitations.show', $existingInvitation->token));
            }

            return back()->with('success', __('Invitation resent successfully'));
        } else {
            // Create new invitation
            try {
                $invitation = $this->workspaceService->inviteUser(
                    $workspace,
                    $request->email,
                    $request->role,
                    auth()->user()
                );

                if (!$emailConfigured) {
                    return back()->with('warning', $emailWarning)
                        ->with('invitation_link', route('invitations.show', $invitation->token));
                }

                return back()->with('success', __('Invitation sent successfully'));
            } catch (\Exception $e) {
                return back()->with('error', $e->getMessage());
            }
        }
    }

    public function show(string $token)
    {
        $invitation = WorkspaceInvitation::where('token', $token)
            ->with(['workspace', 'invitedBy'])
            ->firstOrFail();

        if ($invitation->isExpired()) {
            return Inertia::render('Invitations/Expired');
        }

        if ($invitation->isAccepted()) {
            return redirect()->route('login');
        }

        $existingUser = auth()->check() || User::where('email', $invitation->email)->exists();

        return Inertia::render('Invitations/Accept', [
            'invitation' => $invitation,
            'existingUser' => $existingUser
        ]);
    }

    public function accept(Request $request, string $token)
    {
        try {
            $invitation = WorkspaceInvitation::where('token', $token)->firstOrFail();

            if ($invitation->isExpired() || $invitation->isAccepted()) {
                abort(404);
            }

            $existingUser = auth()->check();
            $userExists = User::where('email', $invitation->email)->exists();

            // If user exists in DB but not authenticated, password is not required
            if (!$existingUser && !$userExists) {
                $request->validate([
                    'password' => 'required|min:8|confirmed'
                ]);
            }

            $result = $this->workspaceService->acceptInvitation(
                $token,
                $request->password
            );

            // Log in the user if not already authenticated
            if (!$existingUser) {
                auth()->login($result['user']);
            }

            // Redirect to workspace
            return redirect()->route('dashboard', ['workspace' => $result['workspace']->id])
                ->with('success', __('Welcome to :workspace!', ['workspace' => $result['workspace']->name]));
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function resend(WorkspaceInvitation $invitation)
    {
        // 'workspace_invite_members' is a legacy permission name Manager was
        // never granted after the team_* permission rewrite (they only got
        // team_view/team_invite) - matches store()'s check so anyone who can
        // send an invite can also resend it, instead of 403ing for Manager.
        $this->authorizePermission('team_invite');

        if (!auth()->user()->canAccessWorkspace($invitation->workspace)) {
            abort(403);
        }

        // Check if email is properly configured before resending
        $userId = $invitation->workspace->owner_id ?? auth()->id();
        if (!\App\Services\MailConfigService::isEmailConfigured($userId, $invitation->workspace_id)) {
            return back()->with('error', __('Email configuration is incorrect or missing. Please configure your email settings properly to send workspace invitations via email.'));
        }

        $invitation->load(['workspace', 'invitedBy']);
        if (!config('app.is_demo', true)) {
            event(new \App\Events\WorkspaceInvited($invitation));
        }


        return back()->with('success', __('Invitation resent successfully'));
    }

    public function destroy(WorkspaceInvitation $invitation)
    {
        // Same legacy-permission gap as resend() above - Manager has
        // team_invite, not workspace_manage_members, and canceling a pending
        // invitation is part of the invite capability, not member management.
        $this->authorizePermission('team_invite');

        if (!auth()->user()->canAccessWorkspace($invitation->workspace)) {
            abort(403);
        }

        $invitation->delete();
        return back()->with('success', __('Invitation deleted successfully'));
    }
}