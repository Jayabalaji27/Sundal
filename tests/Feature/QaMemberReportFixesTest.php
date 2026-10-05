<?php
/**
 * Regression tests for the 2026-10-05 member-role QA report.
 */

use App\Events\TaskCreated;
use App\Models\Bug;
use App\Models\BugStatus;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectExpense;
use App\Models\ProjectMember;
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
use Illuminate\Support\Facades\Event;
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
        \App\Http\Middleware\EnsureEmailIsVerified::class,
    ]);
    Cache::flush();
});

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Owner + workspace + manager + member, owner on the default plan. */
function mbrWorkspace(): array
{
    $plan = Plan::where('is_default', true)->first();
    $owner = User::factory()->create(['type' => 'company', 'plan_id' => $plan?->id, 'plan_is_active' => 1]);
    $workspace = Workspace::create(['name' => 'QA WS', 'slug' => 'qa-' . uniqid(), 'owner_id' => $owner->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    $owner->update(['current_workspace_id' => $workspace->id]);
    $owner->assignRole('company');

    $users = [];
    foreach (['manager', 'member'] as $role) {
        $user = User::factory()->create(['type' => 'company', 'current_workspace_id' => $workspace->id]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active']);
        $user->assignRole($role);
        $users[] = $user;
    }

    return [$owner, $workspace, ...$users];
}

/** A private project, with the given users on it as members. */
function mbrProject(Workspace $workspace, User $creator, array $members = []): Project
{
    $project = Project::create([
        'workspace_id' => $workspace->id, 'title' => 'P' . uniqid(), 'status' => 'active',
        'priority' => 'medium', 'progress' => 0, 'created_by' => $creator->id, 'visibility' => 'private',
    ]);
    foreach ($members as $user) {
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $user->id, 'role' => 'member', 'assigned_by' => $creator->id]);
    }

    return $project;
}

function mbrExpense(Project $project, User $submitter, string $status = 'pending'): ProjectExpense
{
    return ProjectExpense::create(['project_id' => $project->id, 'submitted_by' => $submitter->id, 'amount' => 50,
        'expense_date' => now()->toDateString(), 'title' => 'E' . uniqid(), 'status' => $status]);
}

function mbrBudget(Workspace $workspace, Project $project, User $creator): ProjectBudget
{
    return ProjectBudget::create(['project_id' => $project->id, 'workspace_id' => $workspace->id, 'total_budget' => 1000,
        'period_type' => 'monthly', 'start_date' => now()->startOfMonth(), 'end_date' => now()->endOfMonth(), 'created_by' => $creator->id]);
}

function mbrTimesheet(Workspace $workspace, Project $project, User $user, string $status = 'draft'): Timesheet
{
    $timesheet = Timesheet::create(['user_id' => $user->id, 'workspace_id' => $workspace->id, 'start_date' => now()->startOfWeek(),
        'end_date' => now()->endOfWeek(), 'status' => $status, 'total_hours' => 8, 'billable_hours' => 8]);
    TimesheetEntry::create(['timesheet_id' => $timesheet->id, 'project_id' => $project->id, 'user_id' => $user->id,
        'date' => now()->startOfWeek()->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        'hours' => 8, 'is_billable' => true, 'hourly_rate' => 0]);

    return $timesheet;
}

// ═══ BUG-2: show endpoints use the same scope as their list ═══

describe('BUG-2 expenses by direct URL', function () {
    test('member cannot open, edit or duplicate someone else\'s expense, but can open their own', function () {
        [$owner, $ws, $manager, $member] = mbrWorkspace();
        $project = mbrProject($ws, $owner, [$manager, $member]);
        $managers = mbrExpense($project, $manager);
        $own = mbrExpense($project, $member);

        $this->actingAs($member)->get(route('expenses.show', $managers))->assertForbidden();
        $this->actingAs($member)->get(route('expenses.edit', $managers))->assertForbidden();
        $this->actingAs($member)->post(route('expenses.duplicate', $managers))->assertForbidden();
        $this->actingAs($member)->postJson(route('expense-receipts.upload', $managers))->assertForbidden();
        expect(ProjectExpense::count())->toBe(2);

        $this->actingAs($member)->get(route('expenses.show', $own))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('expenses/Show')->where('expense.id', $own->id));
    });

    test('manager still opens expenses on projects they belong to, not on other projects', function () {
        [$owner, $ws, $manager, $member] = mbrWorkspace();
        $theirs = mbrProject($ws, $owner, [$manager, $member]);
        $other = mbrProject($ws, $owner);

        $this->actingAs($manager)->get(route('expenses.show', mbrExpense($theirs, $member)))->assertOk();
        $this->actingAs($manager)->get(route('expenses.show', mbrExpense($other, $owner)))->assertForbidden();
        $this->actingAs($owner)->get(route('expenses.show', mbrExpense($other, $owner)))->assertOk();
    });

    test('member cannot list the tasks of a project they are not on', function () {
        [$owner, $ws, $manager, $member] = mbrWorkspace();
        $this->actingAs($member)->getJson(route('api.projects.tasks', mbrProject($ws, $owner)))->assertForbidden();
        $this->actingAs($member)->getJson(route('api.projects.tasks', mbrProject($ws, $owner, [$member])))->assertOk();
    });
});

describe('BUG-2 budgets by direct URL', function () {
    test('member can open the budget of their project only', function () {
        [$owner, $ws, $manager, $member] = mbrWorkspace();
        $notOn = mbrBudget($ws, mbrProject($ws, $owner), $owner);
        $on = mbrBudget($ws, mbrProject($ws, $owner, [$member]), $owner);

        $this->actingAs($member)->get(route('budgets.show', $notOn))->assertForbidden();
        $this->actingAs($member)->get(route('budgets.show', $on))->assertOk();
        $this->actingAs($member)->get(route('budgets.index'))
            ->assertInertia(fn (Assert $page) => $page->has('budgets.data', 1)->where('budgets.data.0.id', $on->id));
    });
});

describe('BUG-2 timesheets by direct URL', function () {
    test('member cannot open, edit or delete another user\'s timesheet', function () {
        [$owner, $ws, $manager, $member] = mbrWorkspace();
        $project = mbrProject($ws, $owner, [$member]);
        $owners = mbrTimesheet($ws, $project, $owner);

        $this->actingAs($member)->get(route('timesheets.show', $owners))->assertForbidden();
        $this->actingAs($member)->put(route('timesheets.update', $owners), ['start_date' => now()->toDateString(), 'notes' => 'x'])
            ->assertForbidden();
        $this->actingAs($member)->delete(route('timesheets.destroy', $owners))->assertForbidden();
        expect($owners->fresh())->not->toBeNull()
            ->and($owners->fresh()->notes)->toBeNull();
    });

    test('member opens their own timesheet; owner opens anyone\'s', function () {
        [$owner, $ws, $manager, $member] = mbrWorkspace();
        $project = mbrProject($ws, $owner, [$member]);
        $own = mbrTimesheet($ws, $project, $member);

        $this->actingAs($member)->get(route('timesheets.show', $own))->assertOk();
        $this->actingAs($owner)->get(route('timesheets.show', $own))->assertOk();
    });
});

describe('BUG-2 project reports by direct URL', function () {
    test('member can open reports only for projects they are on', function () {
        [$owner, $ws, $manager, $member] = mbrWorkspace();
        $notOn = mbrProject($ws, $owner);
        $on = mbrProject($ws, $owner, [$member]);

        $this->actingAs($member)->get(route('project-reports.show', $notOn))->assertForbidden();
        $this->actingAs($member)->postJson(route('project-reports.tasks', $notOn))->assertForbidden();
        $this->actingAs($member)->get(route('project-reports.show', $on))->assertOk();
    });
});

// ═══ BUG-3: task / bug create without assigned_to ═══

describe('BUG-3 create without assigned_to', function () {
    test('a task posted without assigned_to is created once, unassigned', function () {
        [$owner, $ws, $manager, $member] = mbrWorkspace();
        $project = mbrProject($ws, $owner, [$member]);
        TaskStage::create(['workspace_id' => $ws->id, 'name' => 'To Do', 'color' => '#999', 'order' => 1]);

        $this->actingAs($member)->post(route('tasks.store'), [
            'project_id' => $project->id, 'title' => 'QA-x', 'priority' => 'low',
            'start_date' => '2026-10-05', 'end_date' => '2026-10-10', 'due_date' => '2026-10-10',
        ])->assertRedirect()->assertSessionHas('success');

        $tasks = Task::where('project_id', $project->id)->get();
        expect($tasks)->toHaveCount(1)
            ->and($tasks->first()->assigned_to)->toBeNull();
    });

    test('a failure after the task is saved rolls the task back', function () {
        [$owner, $ws, $manager, $member] = mbrWorkspace();
        $project = mbrProject($ws, $owner, [$member]);
        TaskStage::create(['workspace_id' => $ws->id, 'name' => 'To Do', 'color' => '#999', 'order' => 1]);
        config(['app.is_demo' => false]);
        Event::listen(TaskCreated::class, fn () => throw new RuntimeException('notification failed'));

        $this->withoutExceptionHandling();
        expect(fn () => $this->actingAs($member)->post(route('tasks.store'), [
            'project_id' => $project->id, 'title' => 'QA-rollback', 'priority' => 'low',
        ]))->toThrow(RuntimeException::class);

        expect(Task::where('title', 'QA-rollback')->exists())->toBeFalse();
    });

    test('a bug posted without assigned_to is created once and reports success', function () {
        [$owner, $ws, $manager, $member] = mbrWorkspace();
        $project = mbrProject($ws, $owner, [$member]);
        BugStatus::create(['workspace_id' => $ws->id, 'name' => 'Open', 'color' => '#f00', 'is_default' => true]);

        $this->actingAs($member)->post(route('bugs.store'), [
            'project_id' => $project->id, 'title' => 'QA-bug', 'priority' => 'low', 'severity' => 'minor',
        ])->assertSessionHas('success');

        expect(Bug::where('title', 'QA-bug')->count())->toBe(1)
            ->and(Bug::where('title', 'QA-bug')->first()->assigned_to)->toBeNull();
    });
});

// ═══ BUG-4: Add Manager is owner-only, and a refused submit isn't a success ═══

describe('BUG-4 assign managers', function () {
    test('a refused Inertia form submit comes back as an error, not a rendered page', function () {
        [$owner, $ws, $manager, $member] = mbrWorkspace();
        $project = mbrProject($ws, $manager);
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $manager->id, 'role' => 'manager', 'assigned_by' => $owner->id]);
        $other = User::factory()->create(['type' => 'company', 'current_workspace_id' => $ws->id]);

        $this->actingAs($manager)
            ->from(route('projects.show', $project))
            ->post(route('projects.assign-managers', $project), ['manager_ids' => [$other->id]], ['X-Inertia' => 'true'])
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHasErrors(['error' => __('Only the workspace owner can assign project managers.')]);

        expect(ProjectMember::where('project_id', $project->id)->where('user_id', $other->id)->exists())->toBeFalse();
    });

    test('the project page only offers Add Manager to the workspace owner', function () {
        [$owner, $ws, $manager, $member] = mbrWorkspace();
        $project = mbrProject($ws, $manager, [$manager]);

        $this->actingAs($manager)->get(route('projects.show', $project))
            ->assertInertia(fn (Assert $page) => $page->where('canAssignManagers', false));
        $this->actingAs($owner)->get(route('projects.show', $project))
            ->assertInertia(fn (Assert $page) => $page->where('canAssignManagers', true));
    });
});

// ═══ BUG-5: member dashboard expense stats ═══

describe('BUG-5 dashboard expenses', function () {
    test('member expense stats count only their own expenses', function () {
        [$owner, $ws, $manager, $member] = mbrWorkspace();
        $project = mbrProject($ws, $owner, [$manager, $member]);
        mbrExpense($project, $manager);

        $this->actingAs($member)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboardData.expenses.total', 0)
                ->where('dashboardData.expenses.pending', 0));

        mbrExpense($project, $member);
        $this->actingAs($member)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboardData.expenses.total', 1)
                ->where('dashboardData.expenses.pending', 1));
    });
});

// ═══ BUG-7: Docs lands on Notes when the Pro Add-on is locked ═══

describe('BUG-7 locked Pro modules', function () {
    test('Docs goes to Notes instead of the locked Knowledge Base', function () {
        config(['app.is_saas' => true]);
        [$owner, $ws, $manager, $member] = mbrWorkspace();
        $owner->update(['plan_id' => null, 'addon_plan_id' => null, 'addon_is_active' => 0]);

        $this->actingAs($member)->get(route('docs.index'))->assertRedirect(route('notes.index'));
    });
});

// ═══ BUG-8: email notification + webhook settings are owner-only ═══

describe('BUG-8 settings endpoints', function () {
    test('member and manager cannot read or change email notification settings or webhooks', function () {
        [$owner, $ws, $manager, $member] = mbrWorkspace();

        foreach ([$member, $manager] as $user) {
            $this->actingAs($user)->getJson(route('settings.email-notifications.get'))->assertForbidden();
            $this->actingAs($user)->getJson(route('settings.email-notifications.available'))->assertForbidden();
            $this->actingAs($user)->postJson(route('settings.email-notifications.update'), [])->assertForbidden();
            $this->actingAs($user)->postJson(route('settings.webhooks.store'), [
                'module' => 'New Task', 'method' => 'POST', 'url' => 'https://example.com/hook',
            ])->assertForbidden();
        }
        expect(\App\Models\Webhook::count())->toBe(0);
    });

    test('owner still reads email notification settings and creates webhooks', function () {
        [$owner] = mbrWorkspace();

        $this->actingAs($owner)->getJson(route('settings.email-notifications.get'))->assertOk();
        $this->actingAs($owner)->postJson(route('settings.webhooks.store'), [
            'module' => 'New Task', 'method' => 'POST', 'url' => 'https://example.com/hook',
        ])->assertOk();
    });
});
