<?php

/*
 * The per-role dashboard insights (DashboardInsights + DashboardController):
 * each role gets its own figures, and every figure only counts what that
 * person may see.
 */

use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectClient;
use App\Models\ProjectMember;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\TaskStage;
use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
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

/** [$owner, $workspace, $manager, $member, $client, $otherClient] */
function diWorkspace(): array
{
    $plan = Plan::where('is_default', true)->first();
    $owner = User::factory()->create(['type' => 'company', 'plan_id' => $plan?->id, 'plan_is_active' => 1]);
    $workspace = Workspace::create(['name' => 'DI WS', 'slug' => 'di-' . uniqid(), 'owner_id' => $owner->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    $owner->update(['current_workspace_id' => $workspace->id]);
    $owner->assignRole('company');

    $users = [];
    foreach (['manager', 'member', 'client', 'client'] as $role) {
        $user = User::factory()->create(['type' => 'company', 'current_workspace_id' => $workspace->id]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active']);
        $user->assignRole($role);
        $users[] = $user;
    }

    return [$owner, $workspace, ...$users];
}

/** A private project with the given members and clients attached. */
function diProject(Workspace $workspace, User $creator, array $members = [], array $clients = []): Project
{
    $project = Project::create([
        'workspace_id' => $workspace->id, 'title' => 'P' . uniqid(), 'status' => 'active',
        'priority' => 'medium', 'progress' => 0, 'created_by' => $creator->id, 'visibility' => 'private',
    ]);
    foreach ($members as $user) {
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $user->id, 'role' => 'member', 'assigned_by' => $creator->id]);
    }
    foreach ($clients as $user) {
        ProjectClient::create(['project_id' => $project->id, 'user_id' => $user->id, 'assigned_by' => $creator->id]);
    }

    return $project;
}

/** The workspace's first open stage (new workspaces come with default stages). */
function diStage(Workspace $workspace): TaskStage
{
    return TaskStage::forWorkspace($workspace->id)->ordered()->where('is_completed', false)->first()
        ?? TaskStage::create(['workspace_id' => $workspace->id, 'name' => 'To Do', 'color' => '#000', 'order' => 1, 'is_default' => true]);
}

function diTask(Project $project, User $creator, array $attrs = []): Task
{
    return Task::create(array_merge([
        'project_id' => $project->id, 'task_stage_id' => diStage($project->workspace)->id, 'title' => 'T' . uniqid(),
        'priority' => 'medium', 'progress' => 0, 'created_by' => $creator->id,
        'end_date' => now()->addDays(3)->toDateString(),
    ], $attrs));
}

function diHours(Workspace $workspace, Project $project, User $user, float $hours): void
{
    $timesheet = Timesheet::create(['user_id' => $user->id, 'workspace_id' => $workspace->id, 'start_date' => now()->startOfWeek(),
        'end_date' => now()->endOfWeek(), 'status' => 'draft', 'total_hours' => $hours, 'billable_hours' => 0]);
    TimesheetEntry::create(['timesheet_id' => $timesheet->id, 'project_id' => $project->id, 'user_id' => $user->id,
        'date' => today()->toDateString(), 'hours' => $hours, 'is_billable' => false]);
}

function diContract(Workspace $workspace, User $creator, User $client, string $subject): Contract
{
    $type = ContractType::firstOrCreate(['workspace_id' => $workspace->id, 'name' => 'Std'], ['is_active' => true, 'created_by' => $creator->id]);

    return Contract::create([
        'contract_id' => 'C-' . uniqid(), 'subject' => $subject, 'contract_type_id' => $type->id, 'contract_value' => 1000,
        'start_date' => today()->toDateString(), 'end_date' => today()->addMonth()->toDateString(), 'status' => 'sent',
        'client_id' => $client->id, 'workspace_id' => $workspace->id, 'created_by' => $creator->id, 'sent_at' => now(),
    ]);
}

// ── Roles ─────────────────────────────────────────────────────────────────────

test('the owner gets revenue by month, open work per stage and team workload', function () {
    [$owner, $ws, , $member] = diWorkspace();
    $project = diProject($ws, $owner, [$member]);
    diTask($project, $owner, ['assigned_to' => $member->id, 'end_date' => now()->subDays(2)->toDateString()]);
    diTask($project, $owner, ['assigned_to' => $member->id]);
    Invoice::create(['project_id' => $project->id, 'workspace_id' => $ws->id, 'created_by' => $owner->id, 'title' => 'Inv',
        'invoice_date' => today()->toDateString(), 'due_date' => today()->addDays(10)->toDateString(),
        'total_amount' => 500, 'subtotal' => 500, 'paid_amount' => 500, 'status' => 'paid', 'tax_rate' => []]);
    $stage = diStage($ws);

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('dashboardData.insights.revenueByMonth', 6)
            ->where('dashboardData.insights.revenueByMonth.5.value', fn ($value) => (float) $value === 500.0)
            ->where('dashboardData.insights.tasksByStage.stages', fn ($stages) => $stages->firstWhere('id', $stage->id)['count'] === 2)
            ->where('dashboardData.insights.teamWorkload.0.id', $member->id)
            ->where('dashboardData.insights.teamWorkload.0.open', 2)
            ->where('dashboardData.insights.teamWorkload.0.overdue', 1)
            ->has('dashboardData.insights.budgetBurn'));
});

test('a manager\'s work in progress and team workload only count the projects they are on', function () {
    [$owner, $ws, $manager, $member] = diWorkspace();
    $mine = diProject($ws, $owner, [$manager, $member]);
    $other = diProject($ws, $owner, [$member]);
    diTask($mine, $owner, ['assigned_to' => $member->id]);
    diTask($other, $owner, ['assigned_to' => $member->id]);
    diTask($other, $owner, ['assigned_to' => $owner->id]);
    $stage = diStage($ws);

    $this->actingAs($manager)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboardData.insights.tasksByStage.stages', fn ($stages) => $stages->firstWhere('id', $stage->id)['count'] === 1)
            ->has('dashboardData.insights.teamWorkload', 1)
            ->where('dashboardData.insights.teamWorkload.0.id', $member->id)
            ->where('dashboardData.insights.teamWorkload.0.open', 1)
            ->missing('dashboardData.insights.revenueByMonth')
            ->missing('dashboardData.insights.budgetBurn'));
});

test('a member gets their own week of logged hours and no team figures', function () {
    [$owner, $ws, , $member] = diWorkspace();
    $project = diProject($ws, $owner, [$member]);
    diHours($ws, $project, $member, 3.5);
    diHours($ws, $project, $owner, 6);

    $this->actingAs($member)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('dashboardData.insights.myWeek.days', 7)
            ->where('dashboardData.insights.myWeek.total', fn ($total) => (float) $total === 3.5)
            ->where('dashboardData.insights.myWeek.days', fn ($days) => (float) $days->firstWhere('today', true)['hours'] === 3.5)
            ->where('dashboardData.insights.completedThisWeek', 0)
            ->missing('dashboardData.insights.teamWorkload')
            ->missing('dashboardData.insights.tasksByStage')
            ->missing('dashboardData.insights.revenueByMonth'));
});

test('a client only sees milestones and contracts of their own projects', function () {
    [$owner, $ws, , , $client, $otherClient] = diWorkspace();
    $theirs = diProject($ws, $owner, [], [$client]);
    $notTheirs = diProject($ws, $owner, [], [$otherClient]);
    ProjectMilestone::create(['project_id' => $theirs->id, 'title' => 'Sign-off', 'due_date' => today()->addDays(5), 'status' => 'pending', 'progress' => 0, 'created_by' => $owner->id]);
    ProjectMilestone::create(['project_id' => $notTheirs->id, 'title' => 'Hidden', 'due_date' => today()->addDays(2), 'status' => 'pending', 'progress' => 0, 'created_by' => $owner->id]);
    diContract($ws, $owner, $client, 'Their contract');
    diContract($ws, $owner, $otherClient, 'Someone else\'s contract');

    $this->actingAs($client)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('dashboardData.insights.milestones', 1)
            ->where('dashboardData.insights.milestones.0.title', 'Sign-off')
            ->has('dashboardData.insights.contractsToSign', 1)
            ->where('dashboardData.insights.contractsToSign.0.subject', 'Their contract')
            ->missing('dashboardData.insights.teamWorkload')
            ->missing('dashboardData.insights.revenueByMonth')
            ->missing('dashboardData.insights.myWeek'));
});

// ── Platform admin ────────────────────────────────────────────────────────────

test('the platform admin\'s sign-ups and plan mix count companies, not their team members', function () {
    config(['app.is_saas' => true]);
    $plan = Plan::where('is_default', true)->first();
    diWorkspace(); // one company with a manager, a member and two clients
    $admin = User::factory()->create(['type' => 'superadmin']);
    $admin->assignRole('superadmin');

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('dashboardData.insights.signupsByMonth', 6)
            ->where('dashboardData.insights.signupsByMonth.5.value', fn ($value) => (int) $value === 1)
            ->where('dashboardData.insights.planMix', fn ($mix) => $mix->sum('count') === 1 && $mix->first()['name'] === $plan->name)
            ->has('dashboardData.insights.revenueByMonth', 6)
            ->has('dashboardData.insights.expiringSoon'));
});
