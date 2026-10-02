<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\PlanLimitService;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeamController extends Controller
{
    use HasPermissionChecks;

    public function __construct(private PlanLimitService $planLimitService)
    {
    }

    public function index(Workspace $workspace)
    {
        $this->authorizePermission('team_view');
        abort_if(!auth()->user()->canAccessWorkspace($workspace), 404);

        $members = $workspace->members()
            ->with('user:id,name,email,avatar,deactivated_at')
            ->orderByRaw("FIELD(role,'owner','manager','member','client')")
            ->get()
            ->map(fn (WorkspaceMember $m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'name' => $m->user->name,
                'email' => $m->user->email,
                'avatar' => $m->user->avatar,
                'role' => $m->role,
                'status' => $m->status,
                'is_deactivated' => $m->user->deactivated_at !== null,
                'joined_at' => $m->joined_at,
            ]);

        $pendingInvitations = $workspace->pendingInvitations()
            ->with('invitedBy:id,name')
            ->latest()
            ->get(['id', 'email', 'role', 'expires_at', 'invited_by', 'created_at']);

        return Inertia::render('team/Index', [
            'workspace' => $workspace->only('id', 'name'),
            'members' => $members,
            'pendingInvitations' => $pendingInvitations,
            'isOwner' => $workspace->isOwner(auth()->user()),
        ]);
    }

    public function updateRole(Request $request, Workspace $workspace, User $user)
    {
        $this->authorizePermission('team_change_role');
        abort_if(!$workspace->isOwner(auth()->user()), 403);

        $member = $workspace->members()->where('user_id', $user->id)->first();
        abort_if(!$member, 404);
        abort_if($member->role === 'owner', 403, __('The workspace owner\'s role cannot be changed.'));

        $validated = $request->validate([
            // Owner is deliberately excluded — role changes can never create a second owner.
            'role' => 'required|in:manager,member,client',
        ]);

        $member->update(['role' => $validated['role']]);

        return back()->with('success', __('Role updated.'));
    }

    public function deactivate(Workspace $workspace, User $user)
    {
        $this->authorizePermission('team_deactivate');
        abort_if(!$workspace->isOwner(auth()->user()), 403);

        $member = $workspace->members()->where('user_id', $user->id)->first();
        abort_if(!$member, 404);
        abort_if($member->role === 'owner', 403, __('The workspace owner cannot be deactivated.'));

        $user->update(['deactivated_at' => now()]);
        $member->update(['status' => 'inactive']);

        // If this was their active workspace, they'll be sent to workspace
        // selection on next login rather than silently landing here again.
        if ($user->current_workspace_id === $workspace->id) {
            $user->update(['current_workspace_id' => null]);
        }

        return back()->with('success', __('Member deactivated. Their seat is now free and their data is preserved.'));
    }

    public function reactivate(Workspace $workspace, User $user)
    {
        $this->authorizePermission('team_deactivate');
        abort_if(!$workspace->isOwner(auth()->user()), 403);

        $member = $workspace->members()->where('user_id', $user->id)->first();
        abort_if(!$member, 404);

        $limitCheck = $this->planLimitService->canAddUserToWorkspace($workspace, $member->role);
        if (!$limitCheck['allowed']) {
            return back()->with('error', $limitCheck['message']);
        }

        $user->update(['deactivated_at' => null]);
        $member->update(['status' => 'active']);

        return back()->with('success', __('Member reactivated.'));
    }
}
