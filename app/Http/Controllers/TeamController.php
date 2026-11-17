<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\PlanLimitService;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Projects in this workspace, for the Team page's project filter and
        // Projects column. Owner and Manager both see every project here, the
        // same way they already see every member of the workspace.
        // Scoped to the workspace in the URL (access checked above) rather than
        // the user's current workspace, so it always matches the members list.
        $projects = Project::withoutGlobalScope('workspace')
            ->where('workspace_id', $workspace->id)
            ->orderBy('title')
            ->get(['id', 'title']);
        $projectIds = $projects->pluck('id');

        // Members are linked to projects via project_members, clients via project_clients.
        $projectIdsByUser = DB::table('project_members')->whereIn('project_id', $projectIds)->get(['project_id', 'user_id'])
            ->concat(DB::table('project_clients')->whereIn('project_id', $projectIds)->get(['project_id', 'user_id']))
            ->groupBy('user_id')
            ->map(fn ($links) => $links->pluck('project_id')->unique()->values());

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
                'project_ids' => $projectIdsByUser->get($m->user_id, collect())->all(),
            ]);

        $pendingInvitations = $workspace->pendingInvitations()
            ->with('invitedBy:id,name')
            ->latest()
            ->get(['id', 'email', 'role', 'expires_at', 'invited_by', 'created_at'])
            // Expired invitations can't be accepted and don't count towards plan
            // limits - flag them so the page doesn't present them as pending.
            ->each(fn ($invitation) => $invitation->setAttribute('is_expired', $invitation->isExpired()));

        return Inertia::render('team/Index', [
            'workspace' => $workspace->only('id', 'name'),
            'members' => $members,
            'projects' => $projects,
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

        // An inactive member is checked again when reactivated.
        if ($member->role !== $validated['role'] && $member->status === 'active') {
            $limitCheck = $this->planLimitService->canAssignRole($workspace, $validated['role']);
            if (!$limitCheck['allowed']) {
                return back()->with('error', $limitCheck['message']);
            }
        }

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

    /**
     * Owner gives a teammate a temporary password (for when the reset email
     * doesn't arrive). The teammate must choose their own at next sign-in, and
     * every session they have open is signed out.
     */
    public function setPassword(Request $request, Workspace $workspace, User $user)
    {
        $this->authorizePermission('team_reset_password');
        $owner = auth()->user();
        abort_if(!$workspace->isOwner($owner), 403);

        $member = $workspace->members()->where('user_id', $user->id)->first();
        abort_if(!$member, 404);
        abort_if($member->role === 'owner' || $user->id === $owner->id || $user->isSuperAdmin(), 403,
            __('You can only set passwords for your team members.'));

        // A login is shared across companies: if this person also works in a
        // workspace this owner doesn't own, setting their password would let
        // this owner sign in to someone else's company.
        $inOtherCompany = WorkspaceMember::where('user_id', $user->id)
            ->whereNotIn('workspace_id', Workspace::where('owner_id', $owner->id)->select('id'))
            ->exists()
            || Workspace::where('owner_id', $user->id)->exists();
        if ($inOtherCompany) {
            return back()->with('error', __(':name also belongs to another company\'s workspace, so only they can change their password. Ask them to use "Forgot password" on the sign-in page.', ['name' => $user->name]));
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => true,
            'remember_token' => \Illuminate\Support\Str::random(60),
        ])->save();

        // Sign them out everywhere (database sessions; other drivers expire on their own).
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        \Illuminate\Support\Facades\Log::info('Team member password set by workspace owner', [
            'workspace_id' => $workspace->id,
            'owner_id' => $owner->id,
            'user_id' => $user->id,
        ]);

        return back()->with('success', __('Temporary password set for :name. They will be asked to choose a new one when they sign in.', ['name' => $user->name]));
    }
}
