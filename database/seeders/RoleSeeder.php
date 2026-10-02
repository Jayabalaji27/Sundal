<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (isSaasMode()) {
            // Create super admin role (SaaS platform admin)
            $superAdminRole = Role::updateOrCreate(
                ['name' => 'superadmin', 'guard_name' => 'web'],
                [
                    'label' => 'Super Admin',
                    'description' => 'Super Admin has full access to all SaaS platform features',
                ]
            );
            // Super Admin is the SaaS tool's own owner — grant every permission that
            // exists, full stop. Don't curate a module subset here; if a new
            // permission is added anywhere, Super Admin gets it automatically.
            $superAdminRole->syncPermissions(Permission::all());


            // Company role gets full access to specific modules (excluding role/permission management)
            $companyModulePermissions = Permission::whereIn('module', [
                'dashboards',
                'workspace',
                'projects',
                'tasks',
                'bugs',
                'timesheet',
                'budget',
                'expense',
                'expense_approval',
                'invoice',
                'media',
                'report',
                'notification_template',
                'webhook',
                'zoom_meeting',
                'google_meeting',
                'tax',
                'notes',
                'contract_types',
                'contracts',
                'contract_notes',
                'contract_comments',
                'contract_attachments',
                'contracts_signature',
                'task_calendar',
                'project_report',
                'project_permissions',
                'todos',
                'knowledge_base',
                'chat',
                'agent',
                'api_key',
                'zapier',
                'form',
                'sprint',
            ])->get();

            $companyLimitedPermissions = Permission::whereIn('name', [
                'plan_view_any',
                'plan_request',
                'plan_trial',
                'plan_subscribe',
                'plan_view_my_requests',
                'plan_view_my_orders',
                'settings_view',
                'settings_update',
                'settings_brand',
                'settings_currency',
                'settings_email',
                'settings_email_notification',
                'settings_payment',
                'settings_slack',
                'settings_telegram',
                'settings_zoom',
                'settings_tax',
                'settings_invoice',
                'settings_google_calendar',
                'settings_google_meet',
                'user_view_logs',
                'settings_webhook',
                'language_view',
                'language_update',
                'language_manage',
                'referral_view_any',
                'referral_view',
                'referral_create',
                'referral_manage',
                'referral_payout',
                'calendar_view',
                'calendar_view_local',
                'calendar_view_google',
                'calendar_sync_google',
            ])->get();

            $companyPermissions = $companyModulePermissions->merge($companyLimitedPermissions);
        } else {
            $companyPermissions = Permission::whereIn('module', [
                'dashboards',
                'workspace',
                'currency',
                'projects',
                'tasks',
                'bugs',
                'timesheet',
                'budget',
                'expense',
                'expense_approval',
                'invoice',
                'media',
                'user',
                'language',
                'landing_page',
                'custom_page',
                'newsletter',
                'contact',
                'settings',
                'report',
                'email_template',
                'notification_template',
                'user_view_logs',
                'webhook',
                'zoom_meeting',
                'tax',
                'notes',
                'contract_types',
                'contracts',
                'contract_notes',
                'contract_comments',
                'contract_attachments',
                'contracts_signature',
                'calendar',
                'task_calendar',
                'project_report',
                'settings_google_meet',
                'google_meeting',
                'project_permissions',
                'todos',
                // Self-hosted mode was missing this entirely, so Company (there is no
                // separate SaaS-only "owner" role in this mode) had zero Knowledge Base
                // permissions - not even kb_view_any - unlike SaaS mode's company list.
                'knowledge_base',
            ])->get();
        }


        // Create company role (SaaS tenant/customer)
        $companyRole = Role::updateOrCreate(
            ['name' => 'company', 'guard_name' => 'web'],
            [
                'label' => 'Company',
                'description' => 'Company has access to manage their business workspace',
            ]
        );
        $companyRole->syncPermissions($companyPermissions);

        // Create manager role (company child)
        $managerRole = Role::updateOrCreate(
            ['name' => 'manager', 'guard_name' => 'web'],
            ['label' => 'Manager', 'description' => 'Manager with full workspace management']
        );

        $managerPermissions = Permission::whereIn('module', ['dashboards', 'tasks', 'bugs', 'timesheet', 'budget', 'expense', 'expense_approval', 'invoice', 'media', 'report', 'notes', 'calendar', 'task_calendar', 'project_report', 'knowledge_base', 'chat', 'agent', 'sprint', 'form'])
            ->orWhereIn('name', [
                'workspace_switch',
                'workspace_leave',
                'team_view',
                'team_invite', // controller enforces Manager may only invite the 'member' role
                // user_view_logs removed: managers do not need access to login history
                'settings_view',
                'settings_zoom',
                'settings_google_meet',
                'zoom_meeting_view_any',
                'zoom_meeting_view',
                'zoom_meeting_join',
                'google_meeting_view_any',
                'google_meeting_view',
                'google_meeting_create',
                'google_meeting_join',
                'project_view_any',
                'project_view',
                'project_create',
                'project_update',
                'project_delete',
                'project_assign_members',
                'project_assign_clients',
                'project_assign',
                'project_manage_budget',
                'project_manage_milestones',
                'project_manage_attachments',
                'project_generate_reports',
                'project_track_progress',
                'project_manage_notes',
                'project_view_activity',
                'project_view_gantt',
                'project_permission_update',
                'todo_view_any',
                'todo_view',
                'todo_status_update',
                'todo_update',
                'todo_attachment_download',
                // Contracts: view + create + edit (not delete)
                'contract_view_any',
                'contract_view',
                'contract_preview',
                'contract_create',
                'contract_update',
                'contract_comment_create',
                'contract_type_view_any',
                'contract_type_view',
                // Contract types: create + edit (not delete), mirroring the contract
                // permissions above - Manager could create contracts but not the
                // contract types they're assigned to.
                'contract_type_create',
                'contract_type_update',
                // Contract attachments: view + upload + download (not delete), mirroring
                // the contract permissions above - was missing entirely, so downloading
                // any contract attachment 403'd for Manager.
                'contract_attachment_view_any',
                'contract_attachment_view',
                'contract_attachment_create',
                'contract_attachment_download',
                // Note: tax_* permissions intentionally NOT granted to Manager - the Tax
                // settings page (taxes.index) is owner/company-only. InvoiceController's
                // create/edit already load Tax::orderBy(...) directly with no permission
                // check, so the Invoice tax dropdown works for Manager without this grant.
            ])
            ->get();
        $managerRole->syncPermissions($managerPermissions);

        // Create member role (company child)
        $memberRole = Role::updateOrCreate(
            ['name' => 'member', 'guard_name' => 'web'],
            ['label' => 'Member', 'description' => 'Member with limited workspace access']
        );

        $memberPermissions = Permission::whereIn('name', [
            'dashboard_view',
            'workspace_switch',
            'workspace_leave',
            'team_view',
            'project_view_any',
            'project_view',
            // project_create removed: members can be assigned to projects but not create new ones
            'task_view_any',
            'task_create',
            'task_update',
            'task_view',
            'task_add_comments',
            'task_change_status',
            // task_manage_stages removed: members view stages, not manage them
            'task_delete',
            'task_add_attachments',
            'task_manage_checklists',
            'bug_view_any',
            'bug_create',
            'bug_update',
            'bug_delete',
            'bug_view',
            'bug_add_comments',
            'bug_change_status',
            // bug_manage_statuses removed: members view statuses, not manage them
            'bug_add_attachments',
            'timesheet_view_any',
            'timesheet_view',
            'timesheet_create',
            'timesheet_update',
            'timesheet_delete',
            'timesheet_assign',
            'timesheet_submit',
            'timesheet_use_timer',
            'timesheet_bulk_operations',
            // Expenses: members get own, full CRUD (Budget/Invoice stay disabled for members)
            'expense_view_any',
            'expense_create',
            'expense_view',
            'expense_update',
            'expense_delete',
            'expense_add_attachments',
            'zoom_meeting_view_any',
            'zoom_meeting_view',
            'zoom_meeting_join',
            'google_meeting_view_any',
            'google_meeting_view',
            'google_meeting_join',
            // user_view_logs removed: members must not see other users' login history
            'note_view_any',
            'note_view',
            'note_create',
            'note_update',
            'note_delete',
            'calendar_view',
            'calendar_view_local',
            'calendar_view_google',
            'calendar_sync_google',
            'task_calendar_view',
            'task_calendar_view_tasks',
            'task_calendar_view_meetings',
            // Reports: members get their own scope
            'project_report_view_any',
            'project_report_view',
            'todo_view_any',
            'todo_view',
            'todo_status_update',
            'todo_update',
            'todo_attachment_download',
            'media_view_any',
            'media_upload',
            'media_download',
            'media_delete',
            // Knowledge Base: view only for members, but they can still attach
            // supporting files to an article without being able to edit its
            // title/content (kb_update stays company/manager-only)
            'kb_view_any',
            'kb_view',
            'kb_manage_attachments',
            // Chat: members can use chat
            'chat_view',
            'chat_create',
            // Agents: members can use (query) agents
            'agent_view_any',
            'agent_view',
            'agent_use',
            // Sprints: members can view and manage tasks in sprints
            'sprint_view_any',
            'sprint_view',
            'sprint_manage_tasks',
            // Forms: members can see and fill out forms, not build/manage them
            'form_view_any',
            // Contracts: members get view-only (no sign/comment/create/edit - that's Client's job)
            'contract_view_any',
            'contract_view',
            'contract_preview',
            'contract_type_view_any',
            'contract_type_view',
            // Contract attachments: view + download only, matching the view-only contract
            // access above - was missing entirely, so downloading any attachment 403'd.
            'contract_attachment_view_any',
            'contract_attachment_view',
            'contract_attachment_download',
            // Budgets: members get view-only, scoped to their own projects
            // (ProjectBudgetController restricts manager/client/member roles to their own projects)
            'budget_view_any',
            'budget_view',
        ])->get();
        $memberRole->syncPermissions($memberPermissions);

        // Create client role (company child)
        $clientRole = Role::updateOrCreate(
            ['name' => 'client', 'guard_name' => 'web'],
            ['label' => 'Client', 'description' => 'Client with read-only access']
        );

        $clientPermissions = Permission::whereIn('name', [
            'dashboard_view',
            'workspace_switch',
            'workspace_leave',
            'project_view_any',
            'project_view',
            // task_view_any/task_view removed: clients should not see the standalone Tasks page/list.
            // task_change_status stays: clients can still change status on tasks surfaced via the
            // calendar (task_calendar_view_tasks below) - this is one of Client's two intentional
            // "limited engagement" capabilities (see role-capabilities-plan.md section 4).
            'task_change_status',
            // task_manage_stages removed per instructions
            // timesheet removed per instructions
            'invoice_view_any',
            'invoice_view',
            // budget removed entirely per instructions
            'zoom_meeting_view_any',
            'zoom_meeting_view',
            'zoom_meeting_join',
            'google_meeting_view_any',
            'google_meeting_view',
            'google_meeting_join',
            // user_view_logs removed per instructions
            // notes removed per instructions
            // Contracts: clients can view, preview, sign, and comment
            'contract_view_any',
            'contract_view',
            'contract_preview',
            'contract_change_status',
            'contract_signature',
            'contract_comment_create',
            'contract_type_view_any',
            'contract_type_view',
            // Contract attachments: view + download only - was missing entirely, so
            // downloading any contract attachment 403'd for Client.
            'contract_attachment_view_any',
            'contract_attachment_view',
            'contract_attachment_download',
            'calendar_view',
            'calendar_view_local',
            'calendar_view_google',
            'task_calendar_view',
            'task_calendar_view_tasks',
            'task_calendar_view_meetings',
            'project_report_view_any',
            'project_report_view',
            // KB, Chat, Agents, BYOA, Bug Statuses removed per instructions
            // Media: view + upload only, so Client can pick/change their own profile avatar
            // via the media library picker (MediaLibraryModal 403'd on media_view_any before this)
            'media_view_any',
            'media_upload',
        ])->get();

        $clientRole->syncPermissions($clientPermissions);
    }
}