<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        $company = User::where('email', 'company@example.com')->first();

        if (!$company) {
            return;
        }

        // Use the company's existing default workspace
        $ownWorkspace = $company->ownedWorkspaces()->first();
        
        // Update workspace description and settings
        $ownWorkspace->update([
            'description' => 'Main workspace for WorkDo Solutions with full team access',
            'timesheet_enabled' => true,
            'timesheet_approval_required' => 'manager',
            'default_work_start' => '09:00:00',
            'default_work_end' => '17:00:00'
        ]);



        // Create 18 workspace members (15-20 range)
        $memberNames = [
            'Sarah Johnson',
            'Michael Chen',
            'Emily Rodriguez',
            'David Kim',
            'Jessica Brown',
            'Alex Thompson',
            'Maria Garcia',
            'James Wilson',
            'Lisa Anderson',
            'Robert Taylor',
            'Amanda Martinez',
            'Kevin Lee',
            'Rachel Davis',
            'Daniel Miller',
            'Nicole White',
            'Christopher Moore',
            'Ashley Jackson',
            'Matthew Harris'
        ];

        foreach ($memberNames as $index => $name) {
            $email = strtolower(str_replace(' ', '.', $name)) . '@techcorp.com';
            $member = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => bcrypt('password'),
                    'type' => 'company',
                    'is_enable_login' => 1,
                    'created_by' => $company->id,
                    'email_verified_at' => now()
                ]
            );

            // Assign company role to user
            $member->assignRole('company');

            // Assign roles: mostly members, some managers
            $role = ($index < 3) ? 'manager' : 'member';

            WorkspaceMember::updateOrCreate(
                [
                    'workspace_id' => $ownWorkspace->id,
                    'user_id' => $member->id
                ],
                [
                    'role' => $role,
                    'status' => 'active',
                    'joined_at' => now()->subDays(rand(1, 90)),
                    'invited_by' => $company->id
                ]
            );
        }

        // Create 7 clients (5-10 range)
        $clientCompanies = [
            'GlobalTech Industries',
            'Innovate Solutions',
            'Digital Dynamics',
            'Future Systems',
            'Smart Enterprises',
            'NextGen Corp',
            'Alpha Technologies'
        ];

        foreach ($clientCompanies as $index => $clientCompany) {
            $email = strtolower(str_replace(' ', '', $clientCompany)) . '@client.com';
            $client = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $clientCompany,
                    'password' => bcrypt('password'),
                    'type' => 'company',
                    'is_enable_login' => 1,
                    'created_by' => $company->id,
                    'email_verified_at' => now()
                ]
            );

            // Assign company role to client
            $client->assignRole('company');

            WorkspaceMember::updateOrCreate(
                [
                    'workspace_id' => $ownWorkspace->id,
                    'user_id' => $client->id
                ],
                [
                    'role' => 'client',
                    'status' => 'active',
                    'joined_at' => now()->subDays(rand(1, 60)),
                    'invited_by' => $company->id
                ]
            );
        }

        // Create shared workspace
        $sharedWorkspace = Workspace::updateOrCreate([
            'name' => 'Collaborative Projects Hub',
            'slug' => 'collaborative-projects-hub',
            'owner_id' => $company->id,
            'description' => 'Shared workspace for external collaborations and partnerships',
            'timesheet_enabled' => true,
            'timesheet_approval_required' => 'none',
            'default_work_start' => '09:00:00',
            'default_work_end' => '18:00:00'
        ]);

        // Add owner as member of shared workspace
        WorkspaceMember::updateOrCreate(
            [
                'workspace_id' => $sharedWorkspace->id,
                'user_id' => $company->id
            ],
            [
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now()
            ]
        );

        // Add some existing members to shared workspace
        $existingMembers = User::where('created_by', $company->id)
            ->where('type', 'company')
            ->take(5)
            ->get();

        foreach ($existingMembers as $member) {
            WorkspaceMember::updateOrCreate(
                [
                    'workspace_id' => $sharedWorkspace->id,
                    'user_id' => $member->id
                ],
                [
                    'role' => 'member',
                    'status' => 'active',
                    'joined_at' => now()->subDays(rand(1, 30)),
                    'invited_by' => $company->id
                ]
            );
        }

        // Set main workspace as current for company
        $company->update(['current_workspace_id' => $ownWorkspace->id]);

        // Update all workspace members to have current_workspace_id
        $workspaceUserIds = WorkspaceMember::where('workspace_id', $ownWorkspace->id)
            ->where('status', 'active')
            ->pluck('user_id');

        User::whereIn('id', $workspaceUserIds)
            ->update(['current_workspace_id' => $ownWorkspace->id]);
    }
}
