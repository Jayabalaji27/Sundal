<?php
/**
 * Regression tests for the 2026-10-18 business validation report
 * (Bugs/sundal-business-validation-report.md).
 */

use App\Models\ExpenseAttachment;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\TaskStage;
use App\Models\Timesheet;
use App\Models\TimesheetApproval;
use App\Models\TimesheetEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\ProjectHealthService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
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

/** Owner + workspace, owner on the default plan. */
function bvOwner(): array
{
    $plan = Plan::where('is_default', true)->first();
    $owner = User::factory()->create(['type' => 'company', 'plan_id' => $plan?->id, 'plan_is_active' => 1]);
    $workspace = Workspace::create(['name' => 'BV WS', 'slug' => 'bv-' . uniqid(), 'owner_id' => $owner->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    $owner->update(['current_workspace_id' => $workspace->id]);
    $owner->assignRole('company');

    return [$owner, $workspace];
}

function bvMember(Workspace $workspace, string $role): User
{
    $user = User::factory()->create(['type' => 'company', 'current_workspace_id' => $workspace->id]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active']);
    $user->assignRole($role);

    return $user;
}

function bvProject(Workspace $workspace, User $creator, array $attrs = []): Project
{
    return Project::create(array_merge([
        'workspace_id' => $workspace->id, 'title' => 'P' . uniqid(), 'status' => 'active',
        'priority' => 'medium', 'progress' => 0, 'created_by' => $creator->id, 'visibility' => 'private',
    ], $attrs));
}

function bvJoin(Project $project, User $user, string $role = 'member'): void
{
    ProjectMember::create(['project_id' => $project->id, 'user_id' => $user->id, 'role' => $role, 'assigned_by' => $project->created_by]);
}

/** A submitted week for $user on $project, waiting in the approval queue. */
function bvSubmittedTimesheet(Workspace $workspace, Project $project, User $user, User $owner): TimesheetApproval
{
    $timesheet = Timesheet::create(['user_id' => $user->id, 'workspace_id' => $workspace->id, 'start_date' => now()->startOfWeek(),
        'end_date' => now()->endOfWeek(), 'status' => 'submitted', 'total_hours' => 8, 'billable_hours' => 8]);
    TimesheetEntry::create(['timesheet_id' => $timesheet->id, 'project_id' => $project->id, 'user_id' => $user->id,
        'date' => now()->startOfWeek()->toDateString(), 'hours' => 8, 'is_billable' => true, 'hourly_rate' => 100]);

    return TimesheetApproval::create(['timesheet_id' => $timesheet->id, 'approver_id' => $owner->id, 'status' => 'pending']);
}

// ═══ B1 Approvals: the dashboard count is what the list shows and the reviewer can act on ═══

describe('B1 approvals queue', function () {
    test('a manager is only counted, shown and allowed the timesheets of their own projects', function () {
        [$owner, $ws] = bvOwner();
        $manager = bvMember($ws, 'manager');
        $mine = bvProject($ws, $owner);
        bvJoin($mine, $manager, 'manager');
        $other = bvProject($ws, $owner);
        $alice = bvMember($ws, 'member');
        $bob = bvMember($ws, 'member');
        $onMine = bvSubmittedTimesheet($ws, $mine, $alice, $owner);
        $onOther = bvSubmittedTimesheet($ws, $other, $bob, $owner);


        $this->actingAs($manager)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('dashboardData.attention.timesheet_approvals', 1));

        $this->actingAs($manager)->get(route('timesheet-approvals.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'pending')
                ->has('approvals.data', 1)
                ->where('approvals.data.0.id', $onMine->id)
                ->where('approvalCounts.timesheets', 1));

        $this->actingAs($manager)->post(route('timesheet-approvals.approve', $onOther))->assertForbidden();
        expect($onOther->fresh()->status)->toBe('pending');

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('dashboardData.attention.timesheet_approvals', 2));
    });

    test('the inbox opens on pending and keeps decided timesheets under history', function () {
        [$owner, $ws] = bvOwner();
        $project = bvProject($ws, $owner);
        $member = bvMember($ws, 'member');
        $pending = bvSubmittedTimesheet($ws, $project, $member, $owner);
        $decided = bvSubmittedTimesheet($ws, $project, bvMember($ws, 'member'), $owner);
        $decided->update(['status' => 'approved']);

        $this->actingAs($owner)->get(route('timesheet-approvals.index'))
            ->assertInertia(fn (Assert $page) => $page->has('approvals.data', 1)->where('approvals.data.0.id', $pending->id));
        $this->actingAs($owner)->get(route('timesheet-approvals.index', ['status' => 'history']))
            ->assertInertia(fn (Assert $page) => $page->has('approvals.data', 1)->where('approvals.data.0.id', $decided->id));
    });

    test('the Approvals menu entry opens the tab with work waiting', function () {
        [$owner, $ws] = bvOwner();
        $project = bvProject($ws, $owner);
        ProjectExpense::create(['project_id' => $project->id, 'submitted_by' => bvMember($ws, 'member')->id, 'amount' => 10,
            'expense_date' => now()->toDateString(), 'title' => 'Taxi', 'status' => 'pending']);

        $this->actingAs($owner)->get(route('approvals.index'))->assertRedirect(route('expense-approvals.index'));
    });

    test('a manager cannot decide an expense outside their projects', function () {
        [$owner, $ws] = bvOwner();
        $manager = bvMember($ws, 'manager');
        $other = bvProject($ws, $owner);
        $expense = ProjectExpense::create(['project_id' => $other->id, 'submitted_by' => bvMember($ws, 'member')->id, 'amount' => 10,
            'expense_date' => now()->toDateString(), 'title' => 'Taxi', 'status' => 'pending']);

        $this->actingAs($manager)->post(route('expense-approvals.approve', $expense), ['notes' => ''])->assertForbidden();
        expect($expense->fresh()->status)->toBe('pending');
    });
});

// ═══ B2 Timesheet report respects project privacy ═══

test('B2 the timesheet report leaves out private projects the manager is not on', function () {
    [$owner, $ws] = bvOwner();
    $manager = bvMember($ws, 'manager');
    $member = bvMember($ws, 'member');
    $mine = bvProject($ws, $owner, ['title' => 'Mine']);
    bvJoin($mine, $manager, 'manager');
    $private = bvProject($ws, $owner, ['title' => 'Private']);
    foreach ([$mine, $private] as $project) {
        bvSubmittedTimesheet($ws, $project, $member, $owner);
    }

    $response = $this->actingAs($manager)->postJson(route('timesheet-reports.generate'), [
        'start_date' => now()->subWeek()->toDateString(), 'end_date' => now()->addWeek()->toDateString(),
    ])->assertOk();
    expect(collect($response->json('projects'))->pluck('project_name')->all())->toBe(['Mine']);

    $this->actingAs($manager)->get(route('timesheet-reports.index'))
        ->assertInertia(fn (Assert $page) => $page->where('projects', fn ($projects) => collect($projects)->pluck('title')->all() === ['Mine']));
});

// ═══ B3 Clients don't receive internal project data ═══

test('B3 a client gets no budget figure, activity log or hour totals on the project page', function () {
    [$owner, $ws] = bvOwner();
    $client = bvMember($ws, 'client');
    $project = bvProject($ws, $owner, ['budget' => 48000]);
    \App\Models\ProjectClient::create(['project_id' => $project->id, 'user_id' => $client->id, 'assigned_by' => $owner->id]);
    $project->logActivity('updated', 'Internal change');

    $this->actingAs($client)->get(route('projects.show', $project))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('project.budget')
            ->missing('project.total_project_hours')
            ->missing('project.avg_hours_per_member')
            ->where('project.activities.total', 0));

    $this->actingAs($owner)->get(route('projects.show', $project))
        ->assertInertia(fn (Assert $page) => $page->has('project.budget')->where('project.activities.total', 1));
});

// ═══ B5 Health counts scope creep ═══

test('B5 a project whose scope doubled is not reported healthy', function () {
    [$owner, $ws] = bvOwner();
    $stage = TaskStage::create(['workspace_id' => $ws->id, 'name' => 'To Do', 'color' => '#000', 'order' => 1]);
    $project = bvProject($ws, $owner, ['deadline' => now()->addDays(60)->toDateString()]);
    $project->forceFill(['created_at' => now()->subDays(30)])->save();
    foreach (range(1, 4) as $i) {
        $task = Task::create(['project_id' => $project->id, 'task_stage_id' => $stage->id, 'title' => "T$i", 'priority' => 'medium', 'progress' => 0, 'created_by' => $owner->id]);
        if ($i <= 2) {
            $task->forceFill(['created_at' => now()->subDays(29)])->save();
        }
    }

    $health = (new ProjectHealthService())->calculate($project->fresh());

    expect((int) $health['metrics']['scope_creep_rate'])->toBe(100)
        ->and($health['score'])->toBeLessThan(100)
        ->and(collect($health['factors'])->pluck('message')->implode(' '))->toContain('Scope grew');
});

// ═══ B6 Managers create invoices; the owner deletes them ═══

test('B6 managers no longer hold invoice_delete', function () {
    [, $ws] = bvOwner();
    $manager = bvMember($ws, 'manager');

    expect($manager->hasPermissionTo('invoice_create'))->toBeTrue()
        ->and($manager->hasPermissionTo('invoice_delete'))->toBeFalse();
});

// ═══ B7 / B8 Defaults for new workspaces ═══

test('B7 B8 new workspaces default to INR and the app name', function () {
    $defaults = defaultSettings();

    expect($defaults['defaultCurrency'])->toBe('INR')
        ->and($defaults['titleText'])->toBe(config('app.name'));
});

// ═══ B9 Receipts go in with the claim ═══

test('B9 an expense is created with its receipt attached', function () {
    Storage::fake('public');
    [$owner, $ws] = bvOwner();
    $member = bvMember($ws, 'member');
    $project = bvProject($ws, $owner);
    bvJoin($project, $member);

    $this->actingAs($member)->post(route('expenses.store'), [
        'project_id' => $project->id, 'amount' => 25, 'expense_date' => now()->toDateString(), 'title' => 'Train',
        'receipts' => [UploadedFile::fake()->image('receipt.jpg')],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $expense = ProjectExpense::where('title', 'Train')->firstOrFail();
    expect(ExpenseAttachment::where('project_expense_id', $expense->id)->where('attachment_type', 'receipt')->count())->toBe(1);
});

// ═══ B10 A contract needs a client ═══

test('B10 a contract cannot be created without a client', function () {
    Storage::fake('public');
    [$owner, $ws] = bvOwner();
    $type = \App\Models\ContractType::create(['name' => 'Service', 'workspace_id' => $ws->id, 'created_by' => $owner->id, 'is_active' => true]);

    $this->actingAs($owner)->post(route('contracts.store'), [
        'subject' => 'MSA', 'contract_type_id' => $type->id, 'start_date' => now()->toDateString(),
        'end_date' => now()->addYear()->toDateString(), 'file' => UploadedFile::fake()->create('msa.pdf', 10, 'application/pdf'),
    ])->assertSessionHasErrors('client_id');
});

// ═══ Minimal forms ═══

describe('Minimal create forms', function () {
    test('a project needs only a title', function () {
        [$owner] = bvOwner();

        $this->actingAs($owner)->post(route('projects.store'), ['title' => 'Just a name'])->assertSessionHasNoErrors();

        $project = Project::where('title', 'Just a name')->firstOrFail();
        expect($project->status)->toBe('planning')->and($project->priority)->toBe('medium');
    });

    test('a task needs no dates and may start and be due the same day', function () {
        [$owner, $ws] = bvOwner();
        TaskStage::create(['workspace_id' => $ws->id, 'name' => 'To Do', 'color' => '#000', 'order' => 1, 'is_default' => true]);
        $project = bvProject($ws, $owner);

        $this->actingAs($owner)->post(route('tasks.store'), ['project_id' => $project->id, 'title' => 'No dates', 'priority' => 'medium'])
            ->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('tasks.store'), ['project_id' => $project->id, 'title' => 'Due only', 'priority' => 'medium',
            'end_date' => now()->addDay()->toDateString()])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('tasks.store'), ['project_id' => $project->id, 'title' => 'Same day', 'priority' => 'medium',
            'start_date' => now()->toDateString(), 'end_date' => now()->toDateString()])->assertSessionHasNoErrors();

        expect(Task::whereIn('title', ['No dates', 'Due only', 'Same day'])->count())->toBe(3);
    });
});

// ═══ One timesheet page ═══

test('the weekly timesheet carries the week status so it can be submitted there', function () {
    [$owner, $ws] = bvOwner();
    $member = bvMember($ws, 'member');
    $project = bvProject($ws, $owner);
    $timesheet = Timesheet::create(['user_id' => $member->id, 'workspace_id' => $ws->id, 'start_date' => now()->startOfWeek(),
        'end_date' => now()->endOfWeek(), 'status' => 'draft', 'total_hours' => 0, 'billable_hours' => 0]);

    $this->actingAs($member)->get(route('timesheets.weekly-view', ['week_start' => now()->startOfWeek()->toDateString()]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('timesheetId', $timesheet->id)
            ->where('timesheetStatus', 'draft')
            ->where('rejectionReason', null));
});

// ═══ Owner home ═══

test('the owner dashboard shows outstanding money and projects at risk', function () {
    [$owner, $ws] = bvOwner();
    $late = bvProject($ws, $owner, ['title' => 'Late', 'start_date' => now()->subDays(60)->toDateString(), 'deadline' => now()->subDay()->toDateString()]);
    \App\Models\Invoice::create(['project_id' => $late->id, 'workspace_id' => $ws->id, 'created_by' => $owner->id, 'title' => 'Inv',
        'invoice_date' => now()->subDays(40)->toDateString(), 'due_date' => now()->subDays(30)->toDateString(),
        'total_amount' => 300, 'subtotal' => 300, 'paid_amount' => 100, 'status' => 'sent', 'tax_rate' => []]);

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboardData.invoices.outstanding_amount', fn ($value) => (float) $value === 200.0)
            ->where('dashboardData.invoices.overdue_amount', fn ($value) => (float) $value === 200.0)
            ->where('dashboardData.projectsAtRisk.0.title', 'Late'));
});
