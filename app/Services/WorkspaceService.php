<?php

namespace App\Services;

use App\Events\WorkspaceInvited;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Services\PlanLimitService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\WorkspaceInvitation as WorkspaceInvitationMail;

class WorkspaceService
{
    public function __construct(private PlanLimitService $planLimitService)
    {
    }

    public function createWorkspace(User $owner, array $data): Workspace
    {
        // Check plan limits before creating workspace (skip in non-SaaS mode)
        if (isSaasMode()) {
            $limitCheck = $this->planLimitService->canCreateWorkspace($owner);
            if (!$limitCheck['allowed']) {
                throw new \Exception($limitCheck['message']);
            }
        }

        return DB::transaction(function () use ($owner, $data) {
            $workspace = Workspace::create([
                'name' => $data['name'],
                'slug' => $this->generateUniqueSlug($data['name']),
                'description' => $data['description'] ?? null,
                'owner_id' => $owner->id,
                'settings' => $data['settings'] ?? []
            ]);

            WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $owner->id,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now()
            ]);

            // Set as current workspace
            $owner->update(['current_workspace_id' => $workspace->id]);

            // Enable Workspace Invitation email template for this workspace
            enableWorkspaceInvitationForUser($owner->id, $workspace->id);

            // Seed a default tax rate so the Invoice creation form always has at
            // least one selectable tax instead of an empty "No taxes configured"
            // dropdown until someone visits Settings > Taxes to add one manually.
            \App\Models\Tax::create([
                'name' => 'Standard Tax',
                'rate' => 0,
                'workspace_id' => $workspace->id,
            ]);

            return $workspace;
        });
    }

    public function inviteUser(Workspace $workspace, string $email, string $role, User $invitedBy): WorkspaceInvitation
    {
        // Normalize role mapping (frontend might send 'member' but we store as 'member')
        $normalizedRole = $this->normalizeRole($role);

        // "Already a member" must be checked before the plan limit - inviting an
        // existing member should say so, not report a misleading plan limit.
        $existingUser = User::where('email', $email)->first();

        if ($existingUser && ($workspace->hasMember($existingUser) || $workspace->owner_id === $existingUser->id)) {
            throw new \Exception(__('This user is already a member of this workspace.'));
        }

        if ($existingUser && $existingUser->type=='superadmin') {
            throw new \Exception('This email is already registered.');
        }

        // Check plan limits before inviting user (skip in non-SaaS mode)
        if (isSaasMode()) {
            $limitCheck = $this->planLimitService->canAddUserToWorkspace($workspace, $normalizedRole);
            if (!$limitCheck['allowed']) {
                throw new \Exception($limitCheck['message']);
            }
        }

        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => $email,
            'role' => $normalizedRole,
            'invited_by' => $invitedBy->id
        ]);

        $invitation->load(['workspace', 'invitedBy']);

        // Fire the WorkspaceInvited event to send email via listener
        WorkspaceInvited::dispatch($invitation);

        return $invitation;
    }

    public function acceptInvitation(string $token, ?string $password = null): array
    {
        return DB::transaction(function () use ($token, $password) {
            $invitation = WorkspaceInvitation::where('token', $token)
                ->where('accepted_at', null)
                ->firstOrFail();

            if ($invitation->isExpired()) {
                throw new \Exception('Invitation has expired');
            }

            // Get workspace's defaultLanguage setting
            $workspace = $invitation->workspace;
            $workspaceOwnerId = $workspace->owner_id;
            $defaultLanguage = \App\Models\Setting::where('user_id', $workspaceOwnerId)
                ->where('workspace_id', $invitation->workspace_id)
                ->where('key', 'defaultLanguage')
                ->value('value') ?? 'en';

            $rtlLanguages = ['ar', 'he'];
            $layoutDirection = in_array($defaultLanguage, $rtlLanguages) ? 'right' : 'left';

            $user = User::where('email', $invitation->email)->first();
            
            if (!$user) {
                if (!$password) {
                    throw new \Exception('Password required for new user');
                }
                
                $user = $this->createUserFromInvitation($invitation, $password, $defaultLanguage);
            } else {
                // Update existing user's lang from workspace setting
                $user->update(['lang' => $defaultLanguage]);
            }

            WorkspaceMember::create([
                'workspace_id' => $invitation->workspace_id,
                'user_id' => $user->id,
                'role' => $invitation->role,
                'status' => 'active',
                'invited_by' => $invitation->invited_by,
                'invited_at' => $invitation->created_at,
                'joined_at' => now()
            ]);

            $invitation->update(['accepted_at' => now()]);

            if (!$user->current_workspace_id) {
                $user->update(['current_workspace_id' => $invitation->workspace_id]);
            }

            // Set layoutDirection setting for the user in this workspace
            \App\Models\Setting::updateOrCreate(
                ['user_id' => $user->id, 'workspace_id' => $invitation->workspace_id, 'key' => 'layoutDirection'],
                ['value' => $layoutDirection]
            );

            return ['user' => $user, 'workspace' => $invitation->workspace];
        });
    }

    private function createUserFromInvitation(WorkspaceInvitation $invitation, string $password, string $lang = 'en'): User
    {
        $name = explode('@', $invitation->email)[0];
        
        $user = User::create([
            'name' => ucfirst($name),
            'email' => $invitation->email,
            'password' => Hash::make($password),
            'type' => $invitation->role,
            'is_enable_login' => 1,
            'email_verified_at' => now(),
            'created_by' => $invitation->invited_by,
            'current_workspace_id' => $invitation->workspace_id,
            'lang' => $lang,
        ]);
        
        // Assign the role they were invited with
        $user->assignRole($invitation->role);
        
        return $user;
    }

    private function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (Workspace::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
    
    /**
     * Normalize role names to ensure consistency
     */
    private function normalizeRole(string $role): string
    {
        $roleMap = [
            'admin' => 'manager',
            'user' => 'member',
            'employee' => 'member'
        ];
        
        return $roleMap[$role] ?? $role;
    }
}