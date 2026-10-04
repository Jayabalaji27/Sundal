<?php
/**
 * Regression tests for the 2026-10-01 role-based QA reports
 * (superadmin / company owner / manager / member / client).
 */

use App\Models\Bug;
use App\Models\BugStatus;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\LoginHistory;
use App\Models\Note;
use App\Models\Plan;
use App\Models\PlanRequest;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectExpense;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TaskStage;
use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Notifications\NewChatMessageNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(PlanSeeder::class);
    // Plan/installation/verification gates are covered elsewhere; keep route model
    // binding and the Spatie route permissions so these run like real requests.
    $this->withoutMiddleware([
        \App\Http\Middleware\CheckInstallation::class,
        \App\Http\Middleware\ShareGlobalSettings::class,
        \App\Http\Middleware\DemoModeMiddleware::class,
        \App\Http\Middleware\CheckPlanAccess::class,
        \App\Http\Middleware\CheckPlanLimits::class,
        \App\Http\Middleware\CheckModuleAccess::class,
        \App\Http\Middleware\EnsureEmailIsVerified::class,
    ]);
    Cache::flush();
});

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Owner + workspace, owner on the default plan. */
function qaOwner(): array
{
    $plan = Plan::where('is_default', true)->first();
    $owner = User::factory()->create(['type' => 'company', 'plan_id' => $plan?->id, 'plan_is_active' => 1]);
    $workspace = Workspace::create(['name' => 'QA WS', 'slug' => 'qa-' . uniqid(), 'owner_id' => $owner->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    $owner->update(['current_workspace_id' => $workspace->id]);
    $owner->assignRole('company');

    return [$owner, $workspace];
}

/**
 * A manager/member/client in the workspace. type = 'company' on purpose: that's what
 * self-registered (invited) accounts get in SaaS mode, which is what the QA saw.
 */
function qaMember(Workspace $workspace, string $role): User
{
    $user = User::factory()->create(['type' => 'company', 'current_workspace_id' => $workspace->id]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active']);
    $user->assignRole($role);

    return $user;
}

function qaProject(Workspace $workspace, User $creator, array $attrs = []): Project
{
    return Project::create(array_merge([
        'workspace_id' => $workspace->id, 'title' => 'P' . uniqid(), 'status' => 'active',
        'priority' => 'medium', 'progress' => 0, 'created_by' => $creator->id,
    ], $attrs));
}

function qaStages(Workspace $workspace): array
{
    return [
        TaskStage::create(['workspace_id' => $workspace->id, 'name' => 'To Do', 'color' => '#000', 'order' => 1, 'is_default' => true]),
        TaskStage::create(['workspace_id' => $workspace->id, 'name' => 'Done', 'color' => '#0f0', 'order' => 2]),
    ];
}

function qaTask(Project $project, TaskStage $stage, User $creator, array $attrs = []): Task
{
    return Task::create(array_merge([
        'project_id' => $project->id, 'task_stage_id' => $stage->id, 'title' => 'T' . uniqid(),
        'priority' => 'medium', 'progress' => 0, 'created_by' => $creator->id,
    ], $attrs));
}

function qaInvoice(Project $project, User $creator, array $attrs = []): Invoice
{
    return Invoice::create(array_merge([
        'project_id' => $project->id, 'workspace_id' => $project->workspace_id, 'created_by' => $creator->id,
        'title' => 'Inv', 'invoice_date' => now()->subDays(40)->toDateString(),
        'due_date' => now()->subDays(30)->toDateString(), 'total_amount' => 100, 'subtotal' => 100, 'status' => 'sent',
        'tax_rate' => [],
    ], $attrs));
}

function qaAddClientToProject(Project $project, User $client): void
{
    \App\Models\ProjectClient::create(['project_id' => $project->id, 'user_id' => $client->id, 'assigned_by' => $project->created_by]);
}

// ═══ Root cause: permission checks follow the workspace role, not users.type ═══

describe('Workspace-role permission checks', function () {
    test('an invited client with type=company does not inherit owner permissions', function () {
        [$owner, $ws] = qaOwner();
        $client = qaMember($ws, 'client');

        expect($client->hasWorkspacePermission('project_delete'))->toBeFalse()
            ->and($client->hasWorkspacePermission('project_view'))->toBeTrue()
            ->and($owner->hasWorkspacePermission('project_delete'))->toBeTrue();
    });

    test('a member does not get approve/budget flags', function () {
        [, $ws] = qaOwner();
        $member = qaMember($ws, 'member');

        expect($member->hasWorkspacePermission('timesheet_approve'))->toBeFalse()
            ->and($member->hasWorkspacePermission('budget_update'))->toBeFalse()
            ->and($member->hasWorkspacePermission('contract_delete'))->toBeFalse();
    });
});

// ═══ Client report ═══

describe('Client', function () {
    test('project page sends no edit flags, timesheets or assign lists to a client', function () {
        [$owner, $ws] = qaOwner();
        $client = qaMember($ws, 'client');
        $project = qaProject($ws, $owner);
        qaAddClientToProject($project, $client);

        $this->actingAs($client)->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canDeleteProject', false)
                ->where('canManageBudget', false)
                ->where('canManageMembers', false)
                ->where('canManageSharedSettings', false)
                ->where('projectTimesheets', [])
                ->where('members', [])
                ->where('budget', null));
    });

    test('project health is not available to clients', function () {
        [$owner, $ws] = qaOwner();
        $client = qaMember($ws, 'client');
        $project = qaProject($ws, $owner);
        qaAddClientToProject($project, $client);

        $this->actingAs($client)->getJson(route('projects.health', $project))->assertForbidden();
    });

    test('projects list only exposes public user fields for members', function () {
        [$owner, $ws] = qaOwner();
        $member = qaMember($ws, 'member');
        $project = qaProject($ws, $owner);
        \App\Models\ProjectMember::create(['project_id' => $project->id, 'user_id' => $member->id, 'role' => 'member', 'assigned_by' => $owner->id]);

        $this->actingAs($owner)->get(route('projects.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects.data.0.members.0.user', fn (Assert $user) => $user
                    ->hasAll(['id', 'name', 'email', 'avatar'])
                    ->missing('plan_id')
                    ->missing('referral_code')
                    ->etc()));
    });

    test('calendar hides task titles from clients unless the project shares tasks', function () {
        [$owner, $ws] = qaOwner();
        $client = qaMember($ws, 'client');
        [$todo] = qaStages($ws);
        $hidden = qaProject($ws, $owner);
        $shared = qaProject($ws, $owner, ['shared_settings' => ['task' => true]]);
        qaAddClientToProject($hidden, $client);
        qaAddClientToProject($shared, $client);
        qaTask($hidden, $todo, $owner, ['title' => 'Internal', 'end_date' => now()->addDay()->toDateString()]);
        qaTask($shared, $todo, $owner, ['title' => 'Shared', 'end_date' => now()->addDay()->toDateString()]);

        $this->actingAs($client)->get(route('task-calendar.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('events', 1)
                ->where('events.0.title', 'Shared'));
    });

    test('dashboard invoice widget uses the same scope as the client invoice list', function () {
        [$owner, $ws] = qaOwner();
        $client = qaMember($ws, 'client');
        $project = qaProject($ws, $owner);
        qaAddClientToProject($project, $client);
        qaInvoice($project, $owner, ['client_id' => $client->id, 'status' => 'paid', 'paid_amount' => 100]);
        qaInvoice($project, $owner, ['client_id' => null]);             // not addressed to this client
        qaInvoice($project, $owner, ['client_id' => $client->id, 'status' => 'draft']); // never sent

        $this->actingAs($client)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboardData.invoices.total', 1)
                ->where('dashboardData.invoices.paid', 1)
                ->where('dashboardData.invoices.overdue', 0));
    });

    test('chat notifications are not sent to participants without chat access', function () {
        [$owner, $ws] = qaOwner();
        $manager = qaMember($ws, 'manager');
        $client = qaMember($ws, 'client');
        $conversation = ChatConversation::create(['workspace_id' => $ws->id, 'type' => 'group', 'name' => 'G', 'created_by' => $manager->id]);
        $conversation->participants()->attach([$manager->id, $client->id, $owner->id]);

        $this->actingAs($manager)->postJson(route('chat.send', $conversation), ['message' => 'hi'])->assertOk();

        expect($client->notifications()->count())->toBe(0)
            ->and($owner->notifications()->count())->toBe(1);
    });

    test('existing chat notifications are hidden from users without chat access', function () {
        [$owner, $ws] = qaOwner();
        $client = qaMember($ws, 'client');
        $conversation = ChatConversation::create(['workspace_id' => $ws->id, 'type' => 'direct', 'created_by' => $owner->id]);
        $message = ChatMessage::create(['conversation_id' => $conversation->id, 'user_id' => $owner->id, 'message' => 'hello']);
        $client->notify(new NewChatMessageNotification($message));

        expect($client->notifications()->count())->toBe(1)
            ->and($client->workspaceNotifications()->count())->toBe(0);
    });

    test('profile page is available to clients', function () {
        [, $ws] = qaOwner();
        $client = qaMember($ws, 'client');

        $this->actingAs($client)->get(route('profile'))->assertOk();
    });

    test('client can only accept or decline their own pending contract, once', function () {
        [$owner, $ws] = qaOwner();
        $client = qaMember($ws, 'client');
        $type = ContractType::create(['name' => 'Std', 'workspace_id' => $ws->id, 'is_active' => true, 'created_by' => $owner->id]);
        $contract = Contract::create([
            'subject' => 'Back', 'contract_type_id' => $type->id, 'contract_value' => 10, 'start_date' => now(),
            'end_date' => now()->addMonth(), 'status' => 'accept', 'client_id' => $client->id,
            'workspace_id' => $ws->id, 'created_by' => $owner->id,
        ]);

        $this->actingAs($client)->put(route('contracts.change-status', $contract), ['status' => 'accept'])
            ->assertSessionHas('warning', 'Contract is already accepted.');

        $this->actingAs($client)->put(route('contracts.change-status', $contract), ['status' => 'decline'])
            ->assertSessionHas('error');
        expect($contract->fresh()->status)->toBe('accept');

        $contract->update(['status' => 'sent']);
        $this->actingAs($client)->put(route('contracts.change-status', $contract), ['status' => 'expired'])
            ->assertSessionHas('error');
        $this->actingAs($client)->put(route('contracts.change-status', $contract), ['status' => 'decline'])
            ->assertSessionHas('success');
        expect($contract->fresh()->status)->toBe('decline');
    });
});

// ═══ Company owner report ═══

describe('Company owner', function () {
    test('budget spent only counts approved expenses inside the budget period', function () {
        [$owner, $ws] = qaOwner();
        $project = qaProject($ws, $owner);
        $budget = ProjectBudget::create([
            'project_id' => $project->id, 'workspace_id' => $ws->id, 'total_budget' => 90, 'period_type' => 'monthly',
            'start_date' => '2026-08-20', 'end_date' => '2026-08-28', 'created_by' => $owner->id,
        ]);
        foreach ([['2026-08-19', 1], ['2026-08-22', 9000], ['2026-08-22', 99], ['2026-09-25', 200]] as [$date, $amount]) {
            ProjectExpense::create(['project_id' => $project->id, 'submitted_by' => $owner->id, 'amount' => $amount,
                'expense_date' => $date, 'title' => 'E', 'status' => 'approved']);
        }

        expect((float) $budget->total_spent)->toBe(9099.0)
            ->and(round($budget->utilization_percentage, 2))->toBe(10110.0);
    });

    test('draft invoices are never overdue and status counts add up', function () {
        [$owner, $ws] = qaOwner();
        $project = qaProject($ws, $owner);
        $draft = qaInvoice($project, $owner, ['status' => 'draft']);
        qaInvoice($project, $owner, ['status' => 'draft']);
        qaInvoice($project, $owner, ['status' => 'paid', 'paid_amount' => 100]);
        qaInvoice($project, $owner, ['status' => 'sent']);                 // overdue
        qaInvoice($project, $owner, ['status' => 'sent', 'due_date' => now()->addWeek()->toDateString()]);

        expect($draft->is_overdue)->toBeFalse();

        $this->actingAs($owner)->get(route('invoices.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('statusCounts.all', 5)
                ->where('statusCounts.draft', 2)
                ->where('statusCounts.paid', 1)
                ->where('statusCounts.sent', 1)
                ->where('statusCounts.partial_paid', 0)
                ->where('statusCounts.overdue', 1));
    });

    test('moving a task to a completed stage completes it, so it is never overdue', function () {
        [$owner, $ws] = qaOwner();
        [$todo, $done] = qaStages($ws);
        expect($done->is_completed)->toBeTrue()->and($todo->is_completed)->toBeFalse();

        $task = qaTask(qaProject($ws, $owner), $todo, $owner, ['end_date' => now()->subDays(10)->toDateString()]);
        expect($task->isOverdue())->toBeTrue();

        $task->update(['task_stage_id' => $done->id]);
        $task->refresh();
        expect($task->progress)->toBe(100)->and($task->isOverdue())->toBeFalse();

        $task->update(['task_stage_id' => $todo->id]);
        expect($task->fresh()->progress)->toBe(0);
    });

    test('owner cannot submit someone else\'s timesheet', function () {
        [$owner, $ws] = qaOwner();
        $member = qaMember($ws, 'member');
        $project = qaProject($ws, $owner);
        $timesheet = Timesheet::create(['user_id' => $member->id, 'workspace_id' => $ws->id, 'start_date' => now()->startOfWeek(),
            'end_date' => now()->endOfWeek(), 'status' => 'draft', 'total_hours' => 8, 'billable_hours' => 8]);
        TimesheetEntry::create(['timesheet_id' => $timesheet->id, 'project_id' => $project->id, 'user_id' => $member->id,
            'date' => now()->toDateString(), 'hours' => 8, 'is_billable' => true, 'hourly_rate' => 0]);

        $this->actingAs($owner)->post(route('timesheets.submit', $timesheet))->assertForbidden();
        expect($timesheet->fresh()->status)->toBe('draft');
    });

    test('timesheet entries need a positive duration and at most 24h per day', function () {
        [$owner, $ws] = qaOwner();
        $project = qaProject($ws, $owner);
        $date = now()->startOfWeek()->toDateString();

        $this->actingAs($owner)->post(route('timesheets.store'), [
            'start_date' => $date,
            'entries' => [['project_id' => $project->id, 'task_id' => null, 'start_time' => '09:00', 'end_time' => '09:00', 'description' => '']],
        ])->assertSessionHasErrors('entries.0.end_time');

        $this->actingAs($owner)->post(route('timesheet-entries.store'), ['project_id' => $project->id, 'date' => $date, 'hours' => 20])
            ->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('timesheet-entries.store'), ['project_id' => $project->id, 'date' => $date, 'hours' => 20])
            ->assertSessionHasErrors('hours');
        $this->actingAs($owner)->post(route('timesheet-entries.store'), ['project_id' => $project->id, 'date' => $date, 'hours' => 30])
            ->assertSessionHasErrors('hours');

        expect(TimesheetEntry::where('user_id', $owner->id)->sum('hours'))->toEqual(20);
    });

    test('a double-submitted note is only created once', function () {
        [$owner] = qaOwner();
        $payload = ['title' => 'why so', 'text' => 'why not', 'color' => '#000', 'type' => 'personal'];

        $this->actingAs($owner)->post(route('notes.store'), $payload);
        $this->actingAs($owner)->post(route('notes.store'), $payload)->assertSessionHas('warning');

        expect(Note::where('title', 'why so')->count())->toBe(1);
    });

    test('invoice item amounts must be greater than zero on create and update', function () {
        [$owner, $ws] = qaOwner();
        $project = qaProject($ws, $owner);
        [$todo] = qaStages($ws);
        $task = qaTask($project, $todo, $owner);
        $base = ['project_id' => $project->id, 'title' => 'Zero', 'invoice_date' => now()->toDateString(), 'due_date' => now()->addWeek()->toDateString()];

        $this->actingAs($owner)->post(route('invoices.store'), $base + ['items' => [['type' => 'task', 'amount' => 0, 'task_id' => $task->id]]])
            ->assertSessionHasErrors('items.0.amount');

        $invoice = qaInvoice($project, $owner, ['status' => 'draft']);
        $this->actingAs($owner)->put(route('invoices.update', $invoice), $base + ['items' => [
            ['type' => 'task', 'description' => 'x', 'rate' => 0, 'amount' => -50, 'task_id' => $task->id],
        ]])->assertSessionHasErrors('items.0.amount');
    });

    test('inviting an existing member says so instead of reporting a plan limit', function () {
        [$owner, $ws] = qaOwner();
        $member = qaMember($ws, 'member');

        $this->actingAs($owner)->post(route('workspace.invitations.store', $ws), ['email' => $member->email, 'role' => 'member'])
            ->assertSessionHas('error');
        expect(session('error'))->toContain('already a member')->not->toContain('limit');
    });

    test('project deletion is blocked while invoices exist and otherwise removes linked bugs', function () {
        [$owner, $ws] = qaOwner();
        $project = qaProject($ws, $owner);
        $status = BugStatus::create(['workspace_id' => $ws->id, 'name' => 'Open', 'color' => '#f00', 'is_default' => true]);
        $bug = Bug::create(['project_id' => $project->id, 'bug_status_id' => $status->id, 'title' => 'B', 'priority' => 'low', 'severity' => 'minor', 'reported_by' => $owner->id]);
        $invoice = qaInvoice($project, $owner);

        $this->actingAs($owner)->getJson(route('projects.deletion-summary', $project))
            ->assertOk()->assertJsonPath('blocked', true)->assertJsonPath('counts.bugs', 1)->assertJsonPath('counts.invoices', 1);

        $this->actingAs($owner)->delete(route('projects.destroy', $project))->assertSessionHas('error');
        expect(Project::find($project->id))->not->toBeNull();

        $invoice->delete();
        $this->actingAs($owner)->delete(route('projects.destroy', $project))->assertRedirect(route('projects.index'));
        expect(Project::find($project->id))->toBeNull()->and(Bug::find($bug->id))->toBeNull();
    });

    test('settings roles hide Super Admin and mark the current role', function () {
        [$owner] = qaOwner();

        $this->actingAs($owner)->get(route('settings'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('roles', fn ($roles) => collect($roles)->pluck('name')->doesntContain('superadmin')
                    && collect($roles)->firstWhere('name', 'company')['is_current'] === true));
    });

    test('timesheet report lists the tasks each member logged time on', function () {
        [$owner, $ws] = qaOwner();
        $manager = qaMember($ws, 'manager');
        [$todo] = qaStages($ws);
        $project = qaProject($ws, $owner);
        $logged = qaTask($project, $todo, $owner, ['title' => 'QA Test Task']);
        qaTask($project, $todo, $owner, ['title' => 'Assigned but not logged', 'assigned_to' => $manager->id]);
        $timesheet = Timesheet::create(['user_id' => $manager->id, 'workspace_id' => $ws->id, 'start_date' => now()->startOfWeek(),
            'end_date' => now()->endOfWeek(), 'status' => 'approved', 'total_hours' => 8, 'billable_hours' => 8]);
        TimesheetEntry::create(['timesheet_id' => $timesheet->id, 'project_id' => $project->id, 'task_id' => $logged->id,
            'user_id' => $manager->id, 'date' => now()->toDateString(), 'hours' => 8, 'is_billable' => true, 'hourly_rate' => 0]);

        $controller = app(\App\Http\Controllers\TimesheetReportController::class);
        $method = new ReflectionMethod($controller, 'generateMemberReport');
        $report = $method->invoke($controller, TimesheetEntry::with(['project', 'task.taskStage', 'user'])->get());

        expect(collect($report[0]['projects'][0]['tasks'])->pluck('title')->all())->toBe(['QA Test Task']);
    });
});

// ═══ Manager report ═══

describe('Manager', function () {
    test('"My Timesheets" only lists the manager\'s own timesheets', function () {
        [$owner, $ws] = qaOwner();
        $manager = qaMember($ws, 'manager');
        $project = qaProject($ws, $owner);
        \App\Models\ProjectMember::create(['project_id' => $project->id, 'user_id' => $manager->id, 'role' => 'manager', 'assigned_by' => $owner->id]);
        foreach ([$owner, $manager] as $user) {
            $ts = Timesheet::create(['user_id' => $user->id, 'workspace_id' => $ws->id, 'start_date' => now()->startOfWeek(),
                'end_date' => now()->endOfWeek(), 'status' => 'draft', 'total_hours' => 8, 'billable_hours' => 8]);
            TimesheetEntry::create(['timesheet_id' => $ts->id, 'project_id' => $project->id, 'user_id' => $user->id,
                'date' => now()->toDateString(), 'hours' => 8, 'is_billable' => true, 'hourly_rate' => 0]);
        }

        $this->actingAs($manager)->get(route('timesheets.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('timesheets.data', 1)
                ->where('timesheets.data.0.user_id', $manager->id)
                ->where('permissions.approve', true));
    });

    test('manager cannot cancel the owner\'s client invitation but can cancel member invitations', function () {
        [$owner, $ws] = qaOwner();
        $manager = qaMember($ws, 'manager');
        $clientInvite = WorkspaceInvitation::create(['workspace_id' => $ws->id, 'email' => 'c@example.com', 'role' => 'client', 'invited_by' => $owner->id]);
        $memberInvite = WorkspaceInvitation::create(['workspace_id' => $ws->id, 'email' => 'm@example.com', 'role' => 'member', 'invited_by' => $manager->id]);

        $this->actingAs($manager)->delete(route('invitations.destroy', $clientInvite))->assertForbidden();
        $this->actingAs($manager)->delete(route('invitations.destroy', $memberInvite))->assertRedirect();

        expect(WorkspaceInvitation::find($clientInvite->id))->not->toBeNull()
            ->and(WorkspaceInvitation::find($memberInvite->id))->toBeNull();
    });

    test('managers no longer hold the integration credential permissions', function () {
        [, $ws] = qaOwner();
        $manager = qaMember($ws, 'manager');

        expect($manager->hasWorkspacePermission('settings_zoom'))->toBeFalse()
            ->and($manager->hasWorkspacePermission('settings_google_meet'))->toBeFalse()
            ->and($manager->hasWorkspacePermission('zoom_meeting_view_any'))->toBeTrue();
    });

    test('contract delete flag is false for managers', function () {
        [, $ws] = qaOwner();
        $manager = qaMember($ws, 'manager');

        $this->actingAs($manager)->get(route('contracts.index'))
            ->assertInertia(fn (Assert $page) => $page->where('permissions.delete', false)->where('permissions.update', true));
    });
});

// ═══ Member report ═══

describe('Member', function () {
    test('member cannot edit, delete or move a task created by someone else', function () {
        [$owner, $ws] = qaOwner();
        $member = qaMember($ws, 'member');
        [$todo, $done] = qaStages($ws);
        $project = qaProject($ws, $owner);
        \App\Models\ProjectMember::create(['project_id' => $project->id, 'user_id' => $member->id, 'role' => 'member', 'assigned_by' => $owner->id]);
        $others = qaTask($project, $todo, $owner, ['title' => 'Not mine']);
        $mine = qaTask($project, $todo, $owner, ['title' => 'Mine', 'assigned_to' => $member->id]);

        $payload = ['title' => 'Changed', 'priority' => 'medium'];
        $this->actingAs($member)->put(route('tasks.update', $others), $payload)->assertForbidden();
        $this->actingAs($member)->delete(route('tasks.destroy', $others))->assertForbidden();
        $this->actingAs($member)->put(route('tasks.change-stage', $others), ['task_stage_id' => $done->id])->assertSessionHas('error');
        $this->actingAs($member)->getJson(route('tasks.show', $others))
            ->assertOk()->assertJsonPath('permissions.update', false)->assertJsonPath('permissions.assign_users', false);

        $this->actingAs($member)->put(route('tasks.update', $mine), $payload + ['assigned_to' => $member->id])->assertSessionHasNoErrors();
        expect($mine->fresh()->title)->toBe('Changed')->and($others->fresh()->title)->toBe('Not mine');
    });

    test('member cannot reassign their own task without task_assign_users', function () {
        [$owner, $ws] = qaOwner();
        $member = qaMember($ws, 'member');
        [$todo] = qaStages($ws);
        $task = qaTask(qaProject($ws, $owner), $todo, $member, ['assigned_to' => $member->id]);

        $this->actingAs($member)->put(route('tasks.update', $task), ['title' => 'x', 'priority' => 'low', 'assigned_to' => $owner->id])
            ->assertForbidden();
    });

    test('budget page flags are read-only for members', function () {
        [, $ws] = qaOwner();
        $member = qaMember($ws, 'member');

        $this->actingAs($member)->get(route('budgets.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('permissions.create', false)->where('permissions.update', false)->where('permissions.delete', false));
    });

    test('member cannot delete an approved expense', function () {
        [$owner, $ws] = qaOwner();
        $member = qaMember($ws, 'member');
        $project = qaProject($ws, $owner);
        $approved = ProjectExpense::create(['project_id' => $project->id, 'submitted_by' => $member->id, 'amount' => 10,
            'expense_date' => now()->toDateString(), 'title' => 'E', 'status' => 'approved']);
        $pending = ProjectExpense::create(['project_id' => $project->id, 'submitted_by' => $member->id, 'amount' => 10,
            'expense_date' => now()->toDateString(), 'title' => 'P', 'status' => 'pending']);

        $this->actingAs($member)->delete(route('expenses.destroy', $approved))->assertForbidden();
        $this->actingAs($member)->delete(route('expenses.destroy', $pending))->assertRedirect();

        expect(ProjectExpense::find($approved->id))->not->toBeNull()->and(ProjectExpense::find($pending->id))->toBeNull();
    });

    test('dashboard My Tasks leaves out completed tasks', function () {
        [$owner, $ws] = qaOwner();
        $member = qaMember($ws, 'member');
        [$todo, $done] = qaStages($ws);
        $project = qaProject($ws, $owner);
        \App\Models\ProjectMember::create(['project_id' => $project->id, 'user_id' => $member->id, 'role' => 'member', 'assigned_by' => $owner->id]);
        qaTask($project, $done, $owner, ['title' => 'Finished', 'assigned_to' => $member->id]);
        qaTask($project, $todo, $owner, ['title' => 'Open', 'assigned_to' => $member->id]);

        $this->actingAs($member)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboardData.myTasks', 1)
                ->where('dashboardData.myTasks.0.title', 'Open'));
    });
});

// ═══ Superadmin report ═══

describe('Superadmin', function () {
    function qaSuperadmin(): User
    {
        $user = User::factory()->create(['type' => 'superadmin']);
        $user->assignRole('superadmin');
        return $user;
    }

    test('platform toggles saved by a second superadmin apply to guests', function () {
        qaSuperadmin();                 // canonical (oldest) platform account
        $second = qaSuperadmin();

        updateSetting('landingPageEnabled', '0', $second->id);
        updateSetting('registrationEnabled', '0', $second->id);

        Cache::flush();
        expect(isLandingPageEnabled())->toBeFalse()->and(isRegistrationEnabled())->toBeFalse();

        $this->get('/')->assertRedirect(route('login'));
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'x', 'email' => 'x@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertNotFound();
    });

    test('saving a setting clears its cached value', function () {
        $admin = qaSuperadmin();
        updateSetting('landingPageEnabled', '1', $admin->id);
        expect(isLandingPageEnabled())->toBeTrue();     // warms the cache

        updateSetting('landingPageEnabled', '0', $admin->id);
        expect(isLandingPageEnabled())->toBeFalse();
    });

    test('expired coupons are flagged and never discount a payment', function () {
        $admin = qaSuperadmin();
        $coupon = Coupon::create(['name' => 'Old', 'type' => 'percentage', 'discount_amount' => 50, 'code' => 'OLD50',
            'code_type' => 'manual', 'status' => true, 'expiry_date' => now()->subDays(10)->toDateString(), 'created_by' => $admin->id]);
        $plan = Plan::where('is_default', true)->first();

        expect($coupon->is_expired)->toBeTrue()
            ->and(calculatePlanPricing($plan, 'OLD50')['coupon_id'])->toBeNull()
            ->and(Coupon::usable()->count())->toBe(0);
    });

    test('coupon codes with spaces are rejected', function () {
        $admin = qaSuperadmin();

        $this->actingAs($admin)->post(route('coupons.store'), ['name' => 'C', 'type' => 'flat', 'discount_amount' => 5,
            'code_type' => 'manual', 'code' => 'WEDR3C45GV E'])->assertSessionHasErrors('code');
    });

    test('company status reflects an expired plan', function () {
        $admin = qaSuperadmin();
        [$owner] = qaOwner();
        $owner->update(['plan_expire_date' => now()->subDays(13), 'status' => 'active']);

        $this->actingAs($admin)->get(route('companies.index', ['status' => 'expired']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies.data', 1)
                ->where('companies.data.0.plan_expired', true));
    });

    test('add company rejects emails without a dotted domain and honours the chosen plan', function () {
        $admin = qaSuperadmin();
        $plan = Plan::where('is_default', false)->first() ?? Plan::first();

        $this->actingAs($admin)->post(route('companies.store'), ['name' => 'QA', 'email' => 'test@test', 'status' => 'active'])
            ->assertSessionHasErrors('email');

        $this->actingAs($admin)->post(route('companies.store'), ['name' => 'QA', 'email' => 'qa-co@example.com', 'status' => 'active',
            'password' => 'password123', 'plan_id' => $plan->id, 'billing_cycle' => 'yearly']);
        $company = User::where('email', 'qa-co@example.com')->first();
        expect($company->plan_id)->toBe($plan->id)
            ->and($company->plan_expire_date->isAfter(now()->addMonths(11)))->toBeTrue();
    });

    test('yearly savings come from real prices and storage 0 reads Unlimited', function () {
        $plan = new Plan(['price' => 1000, 'yearly_price' => 10000, 'storage_limit' => 0]);
        $noSaving = new Plan(['price' => 0, 'yearly_price' => 100]);

        expect($plan->yearlySavingsPercent())->toBe(17)
            ->and($noSaving->yearlySavingsPercent())->toBe(0)
            ->and($plan->formattedStorage())->toBe('Unlimited');
    });

    test('plan yearly price above 12x monthly is rejected', function () {
        $admin = qaSuperadmin();

        $this->actingAs($admin)->post(route('plans.store'), [
            'name' => 'QA-Edge Plan', 'price' => 0, 'yearly_price' => 100, 'duration' => 'monthly',
            'max_users_per_workspace' => 0, 'max_clients_per_workspace' => 0, 'max_managers_per_workspace' => 1,
            'max_projects_per_workspace' => 1, 'workspace_limit' => 1, 'storage_limit' => 0,
        ])->assertSessionHasErrors('yearly_price');
    });

    test('a plan with subscribers cannot be deleted', function () {
        $admin = qaSuperadmin();
        $plan = Plan::create(['name' => 'QA subscribed plan', 'price' => 10, 'yearly_price' => 100, 'duration' => 'monthly', 'is_default' => false,
            'max_users_per_workspace' => 5, 'max_clients_per_workspace' => 5, 'max_managers_per_workspace' => 1,
            'max_projects_per_workspace' => 3, 'workspace_limit' => 1, 'storage_limit' => 1]);
        User::factory()->create(['type' => 'company', 'plan_id' => $plan->id]);

        $this->actingAs($admin)->delete(route('plans.destroy', $plan))->assertSessionHas('error');
        expect(Plan::find($plan->id))->not->toBeNull();
    });

    test('login history labels users by workspace role', function () {
        $admin = qaSuperadmin();
        [, $ws] = qaOwner();
        $client = qaMember($ws, 'client');
        LoginHistory::create(['user_id' => $client->id, 'ip' => '127.0.0.1', 'date' => now(), 'details' => [], 'created_by' => $admin->id, 'type' => 'login']);

        $this->actingAs($admin)->get(route('users.all-logs'))
            ->assertInertia(fn (Assert $page) => $page->where('loginHistories.data.0.user.role_label', 'Client'));
    });

    test('plan requests show the plan price as subtotal and total', function () {
        $plan = Plan::first();
        $request = PlanRequest::create(['user_id' => User::factory()->create()->id, 'plan_id' => $plan->id, 'duration' => 'monthly', 'status' => 'pending']);

        expect($request->fresh()->subtotal)->toBe((float) $plan->price)
            ->and($request->fresh()->total)->toBe((float) $plan->price);
    });
});

// ═══ Company owner production-readiness report (taskly-qa-fix-report.md) ═══

describe('Company owner production pass', function () {
    test('editing an invoice with a tax saves the new totals', function () {
        [$owner, $ws] = qaOwner();
        $project = qaProject($ws, $owner);
        [$todo] = qaStages($ws);
        $task = qaTask($project, $todo, $owner);
        $tax = \App\Models\Tax::create(['workspace_id' => $ws->id, 'name' => 'GST', 'rate' => 12]);
        $payload = ['project_id' => $project->id, 'title' => 'Inv', 'invoice_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(), 'selected_taxes' => [$tax->id]];

        $this->actingAs($owner)->post(route('invoices.store'), $payload + ['items' => [['type' => 'task', 'amount' => 1000, 'task_id' => $task->id]]])
            ->assertSessionHasNoErrors();
        $invoice = Invoice::latest('id')->first();

        // tax_rate is a JSON column; MySQL re-formats it, so the stored text never
        // matches a fresh json_encode() and Eloquent's dirty check casts it.
        $this->actingAs($owner)->put(route('invoices.update', $invoice), $payload + ['items' => [
            ['type' => 'task', 'description' => 'x', 'rate' => 1000, 'amount' => 2000, 'task_id' => $task->id],
        ]])->assertRedirect(route('invoices.show', $invoice))->assertSessionHasNoErrors();

        $invoice->refresh();
        expect($invoice->subtotal)->toBe('2000.00')
            ->and($invoice->tax_amount)->toBe('240.00')
            ->and($invoice->total_amount)->toBe('2240.00')
            ->and($invoice->selected_taxes)->toBe([$tax->id]);
    });

    test('a non-numeric invoice amount is a validation error, not a 500', function () {
        [$owner, $ws] = qaOwner();
        $project = qaProject($ws, $owner);
        [$todo] = qaStages($ws);
        $task = qaTask($project, $todo, $owner);
        $invoice = qaInvoice($project, $owner, ['status' => 'draft']);

        $this->actingAs($owner)->put(route('invoices.update', $invoice), [
            'title' => 'Inv', 'invoice_date' => now()->toDateString(), 'due_date' => now()->addWeek()->toDateString(),
            'items' => [['type' => 'task', 'description' => 'x', 'amount' => '1,000.00', 'task_id' => $task->id]],
        ])->assertSessionHasErrors('items.0.amount');
    });
});

describe('Production error handling', function () {
    test('an unexpected exception shows the generic error page without details', function () {
        config(['app.debug' => false]);
        app()->detectEnvironment(fn () => 'production');
        \Illuminate\Support\Facades\Route::middleware('web')->get('/__qa-boom', fn () => throw new RuntimeException('qa-secret-detail'));
        [$owner] = qaOwner();

        $response = $this->actingAs($owner)->get('/__qa-boom');

        $response->assertStatus(500)
            ->assertInertia(fn (Assert $page) => $page->component('errors/error')->where('status', 500));
        expect($response->getContent())->not->toContain('qa-secret-detail')->not->toContain(base_path());
    });
});

describe('Plan limits (company owner pass)', function () {
    test('a timer status poll does not swallow the flash from a blocked action', function () {
        [$owner, $ws] = qaOwner();
        qaMember($ws, 'manager'); // Free plan: 1 manager

        $this->actingAs($owner)->post(route('workspace.invitations.store', $ws), ['email' => 'mgr2@example.com', 'role' => 'manager'])
            ->assertSessionHas('error');
        // The poll lands between the POST and the redirected page load.
        $this->getJson(route('timer.status'))->assertOk();

        $this->get(route('team.index', $ws))
            ->assertInertia(fn (Assert $page) => $page->where('flash.error', fn ($error) => str_contains($error, 'Manager limit')));
    });

    test('creating a workspace over the plan limit returns an error', function () {
        [$owner] = qaOwner(); // Free plan: 1 workspace

        $this->actingAs($owner)->post(route('workspaces.store'), ['name' => 'Second'])
            ->assertSessionHasErrors('error');
        expect(Workspace::where('owner_id', $owner->id)->count())->toBe(1);
    });

    test('clients count toward the overall user limit', function () {
        [$owner, $ws] = qaOwner(); // Free plan: 2 users per workspace
        qaMember($ws, 'manager');
        qaMember($ws, 'member');

        $this->actingAs($owner)->post(route('workspace.invitations.store', $ws), ['email' => 'client@example.com', 'role' => 'client'])
            ->assertSessionHas('error');
        expect(session('error'))->toContain('User limit')
            ->and(WorkspaceInvitation::where('email', 'client@example.com')->exists())->toBeFalse();
    });

    test('changing a role respects the role cap', function () {
        [$owner, $ws] = qaOwner(); // Free plan: 1 manager
        qaMember($ws, 'manager');
        $member = qaMember($ws, 'member');

        $this->actingAs($owner)->patch(route('team.update-role', [$ws, $member]), ['role' => 'manager'])
            ->assertSessionHas('error');
        expect(WorkspaceMember::where('workspace_id', $ws->id)->where('user_id', $member->id)->value('role'))->toBe('member');
    });
});

describe('Timesheets, calendar and taxes (company owner pass)', function () {
    test('a rejected timesheet shows the reason and can be resubmitted and approved', function () {
        [$owner, $ws] = qaOwner();
        $member = qaMember($ws, 'member');
        $project = qaProject($ws, $owner);
        $timesheet = Timesheet::create(['user_id' => $member->id, 'workspace_id' => $ws->id, 'start_date' => '2026-10-05',
            'end_date' => '2026-10-11', 'status' => 'draft', 'total_hours' => 8, 'billable_hours' => 8]);
        TimesheetEntry::create(['timesheet_id' => $timesheet->id, 'project_id' => $project->id, 'user_id' => $member->id,
            'date' => '2026-10-06', 'hours' => 8, 'is_billable' => true, 'hourly_rate' => 0]);

        $this->actingAs($member)->post(route('timesheets.submit', $timesheet))->assertSessionHasNoErrors();
        $approval = \App\Models\TimesheetApproval::where('timesheet_id', $timesheet->id)->latest('id')->first();
        $this->actingAs($owner)->post(route('timesheet-approvals.reject', $approval), ['comments' => 'Split the hours by task'])
            ->assertSessionHas('success');
        expect($timesheet->fresh()->status)->toBe('rejected');

        $this->actingAs($member)->get(route('timesheets.index'))
            ->assertInertia(fn (Assert $page) => $page->where('timesheets.data.0.latest_approval.comments', 'Split the hours by task'));

        $this->actingAs($member)->post(route('timesheets.submit', $timesheet))->assertSessionHasNoErrors();
        expect($timesheet->fresh()->status)->toBe('submitted');

        $approval = \App\Models\TimesheetApproval::where('timesheet_id', $timesheet->id)->latest('id')->first();
        expect($approval->status)->toBe('pending');
        $this->actingAs($owner)->post(route('timesheet-approvals.approve', $approval))->assertSessionHas('success');
        expect($timesheet->fresh()->status)->toBe('approved');
    });

    test('calendar sends tasks as all-day date-only events with an exclusive end', function () {
        [$owner, $ws] = qaOwner();
        $project = qaProject($ws, $owner);
        [$todo] = qaStages($ws);
        qaTask($project, $todo, $owner, ['start_date' => '2026-10-06', 'end_date' => '2026-10-09']);

        $this->actingAs($owner)->get(route('task-calendar.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('events.0.start', '2026-10-06')
                ->where('events.0.end', '2026-10-10')
                ->where('events.0.allDay', true));
    });

    test('the weekly view always starts on the Monday of the requested week', function () {
        [$owner] = qaOwner();

        $this->actingAs($owner)->get(route('timesheets.weekly-view', ['week_start' => '2026-10-06']))
            ->assertInertia(fn (Assert $page) => $page->where('weekStart', '2026-10-05')->where('weekEnd', '2026-10-11'));
    });

    test('twenty tax updates in a row all succeed', function () {
        [$owner, $ws] = qaOwner();
        $tax = \App\Models\Tax::create(['workspace_id' => $ws->id, 'name' => 'GST', 'rate' => 10]);

        foreach (range(1, 20) as $i) {
            $this->actingAs($owner)->put(route('taxes.update', $tax), ['name' => 'GST', 'rate' => 10 + $i])
                ->assertRedirect()->assertSessionHas('success');
        }
        expect($tax->fresh()->rate)->toBe(30.0);
    });
});

describe('Medium and polish fixes (company owner pass)', function () {
    test('AI Generate is gated by the add-on like the other AI modules', function () {
        [$owner] = qaOwner(); // Free plan, no add-on
        foreach (['ai.projects.parse', 'ai.projects.create'] as $name) {
            expect(\Illuminate\Support\Facades\Route::getRoutes()->getByName($name)->gatherMiddleware())->toContain('module.access');
        }

        $this->actingAs($owner)->get(route('projects.index'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.modulesLocked', true));
    });

    test('project hours and the timesheet report leave out rejected and draft time', function () {
        [$owner, $ws] = qaOwner();
        $project = qaProject($ws, $owner);
        foreach (['approved' => 8, 'rejected' => 9, 'draft' => 3] as $status => $hours) {
            $ts = Timesheet::create(['user_id' => $owner->id, 'workspace_id' => $ws->id, 'start_date' => now()->startOfWeek(),
                'end_date' => now()->endOfWeek(), 'status' => $status, 'total_hours' => $hours, 'billable_hours' => $hours]);
            // No task: time logged straight on the project counts too.
            TimesheetEntry::create(['timesheet_id' => $ts->id, 'project_id' => $project->id, 'user_id' => $owner->id,
                'date' => now()->toDateString(), 'hours' => $hours, 'is_billable' => true, 'hourly_rate' => 0]);
        }

        $this->actingAs($owner)->get(route('projects.show', $project))
            ->assertInertia(fn (Assert $page) => $page
                ->where('project.total_project_hours', fn ($h) => (float) $h === 8.0)
                ->where('project.total_team_members', 1)
                ->where('project.team_size', 1)
                ->where('project.avg_hours_per_member', fn ($h) => (float) $h === 8.0));

        $range = ['start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString()];
        expect((float) $this->postJson(route('timesheet-reports.generate'), $range)->json('summary.total_hours'))->toBe(8.0)
            ->and((float) $this->postJson(route('timesheet-reports.generate'), $range + ['status' => 'all'])->json('summary.total_hours'))->toBe(20.0);
    });

    test('notes cannot be shared with deactivated members', function () {
        [$owner, $ws] = qaOwner();
        $active = qaMember($ws, 'member');
        $inactive = qaMember($ws, 'manager');
        WorkspaceMember::where('user_id', $inactive->id)->update(['status' => 'inactive']);

        $this->actingAs($owner)->get(route('notes.index'))
            ->assertInertia(fn (Assert $page) => $page->where('users', fn ($users) => collect($users)->pluck('id')->all() === [$active->id]));
    });

    test('rejecting an expense needs a reason', function () {
        [$owner, $ws] = qaOwner();
        $project = qaProject($ws, $owner);
        $expense = ProjectExpense::create(['project_id' => $project->id, 'submitted_by' => $owner->id, 'amount' => 10,
            'expense_date' => now()->toDateString(), 'title' => 'E', 'status' => 'pending']);

        $this->actingAs($owner)->post(route('expense-approvals.reject', $expense), ['notes' => ''])->assertSessionHasErrors('notes');
        expect($expense->fresh()->status)->toBe('pending');

        $this->actingAs($owner)->post(route('expense-approvals.reject', $expense), ['notes' => 'Missing receipt'])->assertSessionHas('success');
        expect($expense->fresh()->status)->toBe('rejected');
    });

    test('small navigation and feedback fixes', function () {
        [$owner, $ws] = qaOwner();
        [$todo] = qaStages($ws);
        $todo->update(['color' => '#ef4444']);

        $this->actingAs($owner)->get(route('expenses.create'))->assertRedirect(route('expenses.index', ['create' => 1]));

        $this->actingAs($owner)->get(route('tasks.index', ['view' => 'list']))
            ->assertInertia(fn (Assert $page) => $page->where('tasks.per_page', 20));

        $this->actingAs($owner)->post(route('projects.store'), ['title' => 'Toast me', 'status' => 'active', 'priority' => 'medium',
            'start_date' => now()->toDateString(), 'deadline' => now()->addMonth()->toDateString()])
            ->assertSessionHas('success');

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('dashboardData.taskStages.0.color', '#ef4444'));
    });
});

describe('Decisions from the company owner pass', function () {
    test('a manager cannot review their own timesheet, the owner can', function () {
        [$owner, $ws] = qaOwner();
        $manager = qaMember($ws, 'manager');
        $project = qaProject($ws, $owner);
        $approvals = [];
        foreach ([$manager, $owner] as $user) {
            $ts = Timesheet::create(['user_id' => $user->id, 'workspace_id' => $ws->id, 'start_date' => now()->startOfWeek(),
                'end_date' => now()->endOfWeek(), 'status' => 'submitted', 'total_hours' => 8, 'billable_hours' => 8]);
            TimesheetEntry::create(['timesheet_id' => $ts->id, 'project_id' => $project->id, 'user_id' => $user->id,
                'date' => now()->toDateString(), 'hours' => 8, 'is_billable' => true, 'hourly_rate' => 0]);
            $approvals[] = \App\Models\TimesheetApproval::create(['timesheet_id' => $ts->id, 'approver_id' => $owner->id, 'status' => 'pending']);
        }
        [$managersOwn, $ownersOwn] = $approvals;

        $this->actingAs($manager)->post(route('timesheet-approvals.approve', $managersOwn), ['comments' => ''])->assertSessionHas('error');
        $this->actingAs($manager)->post(route('timesheet-approvals.bulk-approve'), ['approval_ids' => [$managersOwn->id]])->assertSessionHas('error');
        expect($managersOwn->fresh()->status)->toBe('pending');

        $this->actingAs($owner)->post(route('timesheet-approvals.approve', $ownersOwn), ['comments' => ''])->assertSessionHas('success');
        expect($ownersOwn->fresh()->status)->toBe('approved');
    });

    test('a manager cannot review their own expense, and workflow steps belong to their approver', function () {
        [$owner, $ws] = qaOwner();
        $manager = qaMember($ws, 'manager');
        $project = qaProject($ws, $owner);
        $expense = ProjectExpense::create(['project_id' => $project->id, 'submitted_by' => $manager->id, 'amount' => 10,
            'expense_date' => now()->toDateString(), 'title' => 'E', 'status' => 'pending']);

        $this->actingAs($manager)->post(route('expense-approvals.approve', $expense), ['notes' => ''])->assertSessionHas('error');
        expect($expense->fresh()->status)->toBe('pending');
        $this->actingAs($owner)->post(route('expense-approvals.approve', $expense), ['notes' => ''])->assertSessionHas('success');

        $other = ProjectExpense::create(['project_id' => $project->id, 'submitted_by' => $owner->id, 'amount' => 5,
            'expense_date' => now()->toDateString(), 'title' => 'W', 'status' => 'pending']);
        $step = \App\Models\ExpenseWorkflow::create(['project_expense_id' => $other->id, 'step' => 1, 'approver_id' => $owner->id, 'status' => 'pending']);
        $this->actingAs($manager)->post(route('expense-workflows.process', $step), ['action' => 'approve'])->assertForbidden();
        expect($other->fresh()->status)->toBe('pending');
    });

    test('paid plans and the add-on are seeded', function () {
        expect(Plan::where('name', 'Starter')->value('plan_type'))->toBe('base')
            ->and(Plan::where('name', 'Pro')->value('plan_type'))->toBe('base')
            ->and(Plan::where('name', 'Pro Add-on')->value('plan_type'))->toBe('addon')
            ->and(Plan::where('is_default', true)->pluck('name')->all())->toBe(['Free']);
    });
});
