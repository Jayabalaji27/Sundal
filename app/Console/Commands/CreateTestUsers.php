<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Console\Command;

/**
 * Creates one test account per workspace role (superadmin/owner/manager/
 * member/client), all sharing one workspace, for manual QA. Idempotent —
 * safe to run more than once.
 */
class CreateTestUsers extends Command
{
    protected $signature = 'users:create-test {--password=password123} {--force : Skip the production confirmation prompt}';

    protected $description = 'Create one test user per role (superadmin, owner, manager, member, client) for manual QA';

    public function handle(): int
    {
        if (app()->environment('production') && !$this->option('force')) {
            $this->error('Refusing to run in production without --force. This creates real login accounts with a known password.');
            return 1;
        }

        $password = $this->option('password');

        $plan = Plan::first();
        if (!$plan) {
            $this->warn('No Plan found — creating one so the workspace owner has an active subscription.');
            $plan = Plan::create([
                'name' => 'QA Test Plan',
                'price' => 0,
                'duration' => 'monthly',
                'description' => 'Created by users:create-test',
            ]);
        }

        // 1. Super Admin — platform-level, not tied to a workspace.
        $superadmin = User::firstOrCreate(
            ['email' => 'superadmin@test.com'],
            [
                'name' => 'Test Superadmin', 'type' => 'superadmin',
                'email_verified_at' => now(), 'is_enable_login' => 1, 'created_by' => 0,
                'password' => $password,
            ]
        );
        if (!$superadmin->hasRole('superadmin')) {
            $superadmin->assignRole('superadmin');
        }

        // 2. Company / Owner — creates and owns the shared test workspace.
        $owner = User::firstOrCreate(
            ['email' => 'company@test.com'],
            [
                'name' => 'Test Company Owner', 'type' => 'company',
                'plan_id' => $plan->id, 'plan_is_active' => 1, 'plan_expire_date' => now()->addYear(),
                'email_verified_at' => now(), 'is_enable_login' => 1, 'created_by' => 0,
                'password' => $password,
            ]
        );
        if (!$owner->hasRole('company')) {
            $owner->assignRole('company');
        }

        $workspace = Workspace::firstOrCreate(
            ['owner_id' => $owner->id, 'name' => 'Test Workspace'],
            ['slug' => 'test-workspace-' . uniqid(), 'is_active' => 1]
        );
        WorkspaceMember::firstOrCreate(
            ['workspace_id' => $workspace->id, 'user_id' => $owner->id],
            ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]
        );
        $owner->update(['current_workspace_id' => $workspace->id]);

        // 3-5. Manager, Member, Client — all in the same workspace.
        $roles = [
            'manager' => ['email' => 'manager@test.com', 'name' => 'Test Manager'],
            'member'  => ['email' => 'member@test.com', 'name' => 'Test Member'],
            'client'  => ['email' => 'client@test.com', 'name' => 'Test Client'],
        ];

        $created = [
            'superadmin' => $superadmin,
            'owner' => $owner,
        ];

        foreach ($roles as $role => $attrs) {
            $user = User::firstOrCreate(
                ['email' => $attrs['email']],
                [
                    'name' => $attrs['name'], 'type' => $role,
                    'email_verified_at' => now(), 'is_enable_login' => 1, 'created_by' => $owner->id,
                    'current_workspace_id' => $workspace->id,
                    'password' => $password,
                ]
            );
            if (!$user->hasRole($role)) {
                $user->assignRole($role);
            }
            WorkspaceMember::firstOrCreate(
                ['workspace_id' => $workspace->id, 'user_id' => $user->id],
                ['role' => $role, 'status' => 'active', 'joined_at' => now()]
            );
            $created[$role] = $user;
        }

        $this->info("Workspace #{$workspace->id} ({$workspace->name})");
        $this->table(['Role', 'Email', 'Password', 'User ID'], collect($created)->map(fn ($u, $role) => [
            $role, $u->email, $password, $u->id,
        ])->values());

        return 0;
    }
}
