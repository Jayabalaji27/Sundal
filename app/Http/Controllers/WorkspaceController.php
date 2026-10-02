<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\WorkspaceService;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkspaceController extends Controller
{
    use HasPermissionChecks;
    
    public function __construct(private WorkspaceService $workspaceService)
    {
    }

    public function index()
    {
        $this->authorizePermission('workspace_view_any');
        
        $user = auth()->user();
        
        $ownedWorkspaces = $user->ownedWorkspaces()->withCount('members')->get();
        $memberWorkspaces = $user->workspaces()->wherePivot('status', 'active')->wherePivot('role', '!=', 'owner')->withCount('members')->get();
        return Inertia::render('Workspaces/Index', [
            'ownedWorkspaces' => $ownedWorkspaces,
            'memberWorkspaces' => $memberWorkspaces,
            'currentWorkspace' => $user->currentWorkspace
        ])->with('breadcrumbs', [
            ['title' => 'Workspaces', 'href' => null]
        ]);
    }

    public function create()
    {
        $this->authorizePermission('workspace_create');
        
        return Inertia::render('Workspaces/Create')->with('breadcrumbs', [
            ['title' => 'Workspaces', 'href' => route('workspaces.index')],
            ['title' => 'Create', 'href' => null]
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('workspace_create');
        
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000'
        ]);

        try {
            $workspace = $this->workspaceService->createWorkspace(
                auth()->user(),
                $request->only(['name', 'description'])
            );

            // Handle AJAX requests
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => __('Workspace created successfully'),
                    'workspace' => $workspace,
                    'redirect' => route('workspaces.show', $workspace)
                ]);
            }

            return redirect()->route('workspaces.show', $workspace)
                ->with('success', __('Workspace created successfully'));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show(Workspace $workspace)
    {
        $this->authorizePermission('workspace_view');
        
        if (!auth()->user()->canAccessWorkspace($workspace)) {
            abort(403);
        }
        
        $workspace->load(['members.user', 'pendingInvitations']);
        
        // Add pending_invitations as an alias for the frontend
        $workspace->pending_invitations = $workspace->pendingInvitations;

        // Append avatar URL to each member's user
        $workspace->members->each(function ($member) {
            if ($member->user) {
                $member->user->avatar = check_file($member->user->avatar)
                    ? get_file($member->user->avatar)
                    : get_file('avatars/avatar.png');
            }
        });

        return Inertia::render('Workspaces/Show', [
            'workspace' => $workspace,
            'availableRoles' => (array) ($workspace->getAvailableInvitationRoles() ?? []),
            'isSaasMode' => isSaasMode()
        ])->with('breadcrumbs', [
            ['title' => 'Workspaces', 'href' => route('workspaces.index')],
            ['title' => $workspace->name, 'href' => null]
        ]);
    }

    public function update(Request $request, Workspace $workspace)
    {
        $this->authorizePermission('workspace_update');
        
        if (!auth()->user()->canAccessWorkspace($workspace) || !$workspace->isOwner(auth()->user())) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000'
        ]);

        $data = $request->only(['name', 'description']);

        $workspace->update($data);

        return back()->with('success', __('Workspace updated successfully'));
    }

    public function destroy(Workspace $workspace)
    {
        $this->authorizePermission('workspace_delete');
        
        if (!$workspace->isOwner(auth()->user())) {
            abort(403);
        }

        $user = auth()->user();
        $wasCurrentWorkspace = $user->current_workspace_id === $workspace->id;

        $workspace->delete();

        // If deleted workspace was current, switch to another workspace
        if ($wasCurrentWorkspace) {
            $nextWorkspace = $user->ownedWorkspaces()->latest()->first() 
                ?? $user->workspaces()->wherePivot('status', 'active')->latest()->first();
            
            if ($nextWorkspace) {
                $user->update(['current_workspace_id' => $nextWorkspace->id]);
            }
        }

        return redirect()->route('workspaces.index')->with('success', __('Workspace deleted successfully'));
    }

    public function switch(Workspace $workspace)
    {
        $this->authorizePermission('workspace_switch');
        
        $user = auth()->user();
        
        if (!$user->canAccessWorkspace($workspace)) {
            abort(403, __('You do not have access to this workspace.'));
        }
        
        if (isSaasMode()) {
            $owner = $workspace->owner;
            if ($owner && $owner->needsPlanSubscription()) {
                return back()->with('error', __('This workspace has an expired or no active plan.'));
            }
        }

        $switched = $user->switchWorkspace($workspace);
        
        if (!$switched) {
            return back()->with('error', __('Failed to switch workspace.'));
        }
        
        return back()->with('success', __('Workspace switched successfully.'));
    }

    public function removeMember(Workspace $workspace, \App\Models\User $user)
    {
        $this->authorizePermission('workspace_manage_members');
        
        // Only owner can remove members
        if (!$workspace->isOwner(auth()->user())) {
            abort(403, __('Only workspace owner can remove members.'));
        }
        
        // Cannot remove owner
        if ($workspace->isOwner($user)) {
            abort(403, __('Cannot remove workspace owner.'));
        }
        
        // Check user's workspace count before removal
        $userWorkspaceCount = $user->workspaces()->wherePivot('status', 'active')->count() + $user->ownedWorkspaces()->count();
        
        $this->removeUserFromWorkspace($workspace, $user);
        
        // If user had only 1 workspace, they are now deleted by removeUserFromWorkspace
        $message = $userWorkspaceCount <= 1 
            ? __('Member removed and user account deleted (no workspaces remaining).')
            : __('Member removed successfully.');
        
        return back()->with('success', $message);
    }
    
    public function leaveWorkspace(Workspace $workspace)
    {
        $this->authorizePermission('workspace_leave');
        
        $user = auth()->user();
        
        // Cannot leave if user is the owner
        if ($workspace->isOwner($user)) {
            abort(403, __('Workspace owner cannot leave the workspace.'));
        }
        
        // Check if user has access to this workspace
        if (!$user->canAccessWorkspace($workspace)) {
            abort(403, __('You do not have access to this workspace.'));
        }
        
        // Check if user has other workspaces
        $userWorkspaceCount = $user->workspaces()->wherePivot('status', 'active')->count() + $user->ownedWorkspaces()->count();
        
        if ($userWorkspaceCount <= 1) {
            abort(403, __('You cannot leave your only workspace.'));
        }
        
        $this->removeUserFromWorkspace($workspace, $user);
        
        // Switch to another workspace if this was the current one
        if ($user->current_workspace_id === $workspace->id) {
            $nextWorkspace = $user->workspaces()->wherePivot('status', 'active')->first() ?? $user->ownedWorkspaces()->first();
            if ($nextWorkspace) {
                $user->switchWorkspace($nextWorkspace);
            }
        }
        
        return redirect()->route('dashboard')->with('success', __('You have left the workspace successfully.'));
    }
    
    private function removeUserFromWorkspace(Workspace $workspace, \App\Models\User $user)
    {
        // Remove user from workspace
        $workspace->members()->where('user_id', $user->id)->delete();
        
        // Check if user should be permanently deleted
        // This happens when:
        // 1. User has no owned workspaces
        // 2. User has no other workspace memberships
        // 3. User has no pending invitations
        $ownedWorkspaces = $user->ownedWorkspaces()->count();
        $memberWorkspaces = $user->workspaces()->wherePivot('status', 'active')->count();
        $pendingInvitations = \App\Models\WorkspaceInvitation::where('email', $user->email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->count();
        
        if ($ownedWorkspaces === 0 && $memberWorkspaces === 0 && $pendingInvitations === 0) {
            // User has no workspaces left, delete the user permanently
            $user->delete();
        }
    }
    
    /**
     * Check workspace creation limits
     */
    public function checkLimits()
    {
        $planLimitService = app(\App\Services\PlanLimitService::class);
        $status = $planLimitService->getWorkspaceCreationStatus(auth()->user());
        
        return response()->json($status);
    }
    
    /**
     * Get user's workspace count for leave validation
     */
    public function getUserWorkspaceCount()
    {
        $user = auth()->user();
        $ownedCount = $user->ownedWorkspaces()->count();
        $memberCount = $user->workspaces()->wherePivot('status', 'active')->count();
        
        return response()->json([
            'total_count' => $ownedCount + $memberCount,
            'owned_count' => $ownedCount,
            'member_count' => $memberCount
        ]);
    }
    
    /**
     * Get error type from exception message
     */
    private function getErrorType(string $message): string
    {
        if (str_contains($message, 'limit reached')) {
            return 'workspace_limit_reached';
        }
        
        if (str_contains($message, 'expired')) {
            return 'plan_expired';
        }
        
        if (str_contains($message, 'No active plan')) {
            return 'no_plan';
        }
        
        return 'general_error';
    }
}