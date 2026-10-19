<?php
/**
 * Sprints on the project Backlog: managers create, start and complete them,
 * members move tasks, clients stay out. Plus the project Board and story points.
 */

use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskStage;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

/** A workspace with an owner, a manager, a member and a client, and one project the member works on. */
function sprWorkspace(): array
{
    $plan = Plan::where('is_default', true)->first();
    $owner = User::factory()->create(['type' => 'company', 'plan_id' => $plan?->id, 'plan_is_active' => 1]);
    $workspace = Workspace::create(['name' => 'Agile WS', 'slug' => 'agile-' . uniqid(), 'owner_id' => $owner->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    $owner->update(['current_workspace_id' => $workspace->id]);
    $owner->assignRole('company');

    $users = ['owner' => $owner];
    foreach (['manager', 'member', 'client'] as $role) {
        $user = User::factory()->create(['type' => $role, 'current_workspace_id' => $workspace->id]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active']);
        $user->assignRole($role);
        $users[$role] = $user;
    }

    $stages = [
        'todo' => TaskStage::create(['workspace_id' => $workspace->id, 'name' => 'To Do', 'order' => 1, 'is_default' => true, 'is_completed' => false]),
        'doing' => TaskStage::create(['workspace_id' => $workspace->id, 'name' => 'In Progress', 'order' => 2, 'is_completed' => false]),
        'done' => TaskStage::create(['workspace_id' => $workspace->id, 'name' => 'Done', 'order' => 3, 'is_completed' => true]),
    ];

    $project = Project::create([
        'workspace_id' => $workspace->id, 'title' => 'Checkout', 'status' => 'active', 'priority' => 'medium',
        'progress' => 0, 'created_by' => $users['manager']->id, 'visibility' => 'private',
    ]);
    ProjectMember::create(['project_id' => $project->id, 'user_id' => $users['manager']->id, 'role' => 'manager', 'assigned_by' => $owner->id, 'assigned_at' => now()]);
    ProjectMember::create(['project_id' => $project->id, 'user_id' => $users['member']->id, 'role' => 'member', 'assigned_by' => $owner->id, 'assigned_at' => now()]);

    return [$workspace, $users, $project, $stages];
}

function sprTask(Project $project, TaskStage $stage, User $creator, string $title, ?int $points = null): Task
{
    return Task::create([
        'project_id' => $project->id, 'task_stage_id' => $stage->id, 'title' => $title, 'priority' => 'medium',
        'story_points' => $points, 'created_by' => $creator->id, 'progress' => $stage->is_completed ? 100 : 0,
    ]);
}

function sprSprint(Workspace $workspace, Project $project, string $name, string $status = 'planning'): Sprint
{
    return Sprint::withoutGlobalScope('workspace')->create([
        'project_id' => $project->id, 'workspace_id' => $workspace->id, 'name' => $name, 'status' => $status, 'created_by' => $project->created_by,
        'start_date' => $status === 'active' ? now()->subDays(3)->toDateString() : null,
        'end_date' => $status === 'active' ? now()->addDays(4)->toDateString() : null,
    ]);
}

// ── Creating and planning ─────────────────────────────────────────────────────

test('a manager creates a sprint, plans a backlog task into it, and each page shows its own work', function () {
    [$workspace, $users, $project, $stages] = sprWorkspace();
    $login = sprTask($project, $stages['todo'], $users['manager'], 'Login page', 3);
    sprTask($project, $stages['todo'], $users['manager'], 'Signup page', 5);

    $this->actingAs($users['manager'])
        ->post(route('sprints.store', $project), ['name' => 'Sprint 1', 'goal' => 'Ship auth', 'start_date' => '2026-10-19', 'end_date' => '2026-11-01'])
        ->assertSessionHas('success');

    $sprint = Sprint::withoutGlobalScope('workspace')->where('project_id', $project->id)->firstOrFail();
    expect($sprint->status)->toBe('planning')->and($sprint->goal)->toBe('Ship auth');

    $this->actingAs($users['manager'])->post(route('sprints.add-task', $sprint), ['task_id' => $login->id])->assertSessionHas('success');

    $this->actingAs($users['manager'])->get(route('sprints.index', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('sprints/Index')
            ->where('project.title', 'Checkout')
            ->has('sprints', 1)
            ->where('sprints.0.name', 'Sprint 1')
            ->where('sprints.0.task_count', 1)
            ->where('sprints.0.points', 3)
            ->where('sprints.0.tasks.0.title', 'Login page')
            ->missing('backlog')
            ->where('backlogSummary', ['tasks' => 1, 'points' => 5])
            ->where('nextSprintName', 'Sprint 2'));

    // The Backlog holds only unplanned work, beside each open sprint and what is planned into it.
    $this->actingAs($users['manager'])->get(route('projects.backlog', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('projects/Backlog')
            ->has('backlog', 1)
            ->where('backlog.0.title', 'Signup page')
            ->has('sprints', 1)
            ->where('sprints.0.name', 'Sprint 1')
            ->has('sprints.0.tasks', 1)
            ->where('sprints.0.tasks.0.title', 'Login page'));
});

test('a sprint cannot end before it starts', function () {
    [, $users, $project] = sprWorkspace();

    $this->actingAs($users['manager'])
        ->post(route('sprints.store', $project), ['name' => 'Sprint 1', 'start_date' => '2026-10-19', 'end_date' => '2026-10-10'])
        ->assertSessionHasErrors('end_date');
});

test('a task sits in one open sprint at a time', function () {
    [$workspace, $users, $project, $stages] = sprWorkspace();
    $task = sprTask($project, $stages['todo'], $users['manager'], 'Cart');
    $first = sprSprint($workspace, $project, 'Sprint 1');
    $second = sprSprint($workspace, $project, 'Sprint 2');

    $this->actingAs($users['manager'])->post(route('sprints.add-task', $first), ['task_id' => $task->id]);
    $this->actingAs($users['manager'])->post(route('sprints.add-task', $second), ['task_id' => $task->id]);

    expect(DB::table('sprint_tasks')->where('task_id', $task->id)->pluck('sprint_id')->all())->toBe([$second->id]);
});

// ── Starting and completing ───────────────────────────────────────────────────

test('only one sprint per project can be active', function () {
    [$workspace, $users, $project] = sprWorkspace();
    sprSprint($workspace, $project, 'Sprint 1', 'active');
    $next = sprSprint($workspace, $project, 'Sprint 2');

    $this->actingAs($users['manager'])->post(route('sprints.start', $next))->assertSessionHas('error');
    expect($next->fresh()->status)->toBe('planning');
});

test('starting a sprint confirms its dates, defaulting to two weeks', function () {
    [$workspace, $users, $project] = sprWorkspace();
    $sprint = sprSprint($workspace, $project, 'Sprint 1');

    $this->actingAs($users['manager'])->post(route('sprints.start', $sprint))->assertSessionHas('success');

    $sprint->refresh();
    expect($sprint->status)->toBe('active')
        ->and($sprint->start_date->toDateString())->toBe(now()->toDateString())
        ->and($sprint->end_date->toDateString())->toBe(now()->addWeeks(2)->toDateString());
});

test('completing a sprint sends unfinished tasks back to the backlog and keeps finished ones', function () {
    [$workspace, $users, $project, $stages] = sprWorkspace();
    $sprint = sprSprint($workspace, $project, 'Sprint 1', 'active');
    $done = sprTask($project, $stages['done'], $users['manager'], 'Done work', 3);
    $open = sprTask($project, $stages['doing'], $users['manager'], 'Open work', 5);
    $sprint->tasks()->attach([$done->id, $open->id]);

    $this->actingAs($users['manager'])->post(route('sprints.complete', $sprint), ['move_to' => 'backlog'])->assertSessionHas('success');

    expect($sprint->fresh()->status)->toBe('completed')
        ->and($sprint->tasks()->pluck('tasks.id')->all())->toBe([$done->id])
        ->and($sprint->fresh()->only('committed_tasks', 'committed_points', 'completed_tasks', 'completed_points'))
        ->toBe(['committed_tasks' => 2, 'committed_points' => 8, 'completed_tasks' => 1, 'completed_points' => 3]);

    // The report keeps what was planned even though the open task moved on.
    $this->actingAs($users['manager'])->get(route('sprints.show', $sprint))
        ->assertInertia(fn (Assert $page) => $page->component('sprints/Show')
            ->where('sprint.task_count', 2)
            ->where('sprint.done_count', 1)
            ->where('sprint.carried_over', 1));

    $this->actingAs($users['manager'])->get(route('projects.backlog', $project))
        ->assertInertia(fn (Assert $page) => $page->has('backlog', 1)->where('backlog.0.title', 'Open work'));

    $this->actingAs($users['manager'])->get(route('sprints.index', $project))
        ->assertInertia(fn (Assert $page) => $page
            ->where('completedSprints.0.done_points', 3)
            ->where('velocity', 3));
});

test('completing a sprint can move unfinished tasks to a planned sprint or a new one', function () {
    [$workspace, $users, $project, $stages] = sprWorkspace();
    $active = sprSprint($workspace, $project, 'Sprint 1', 'active');
    $planned = sprSprint($workspace, $project, 'Sprint 2');
    $open = sprTask($project, $stages['todo'], $users['manager'], 'Carry me');
    $active->tasks()->attach($open->id);

    $this->actingAs($users['manager'])->post(route('sprints.complete', $active), ['move_to' => (string) $planned->id]);
    expect($planned->tasks()->pluck('tasks.id')->all())->toBe([$open->id]);

    $this->actingAs($users['manager'])->post(route('sprints.start', $planned));
    $this->actingAs($users['manager'])->post(route('sprints.complete', $planned), ['move_to' => 'new']);

    $new = Sprint::withoutGlobalScope('workspace')->where('project_id', $project->id)->where('status', 'planning')->firstOrFail();
    expect($new->name)->toBe('Sprint 3')
        ->and($new->tasks()->pluck('tasks.id')->all())->toBe([$open->id]);
});

// ── Roles ─────────────────────────────────────────────────────────────────────

test('a member plans tasks into sprints but cannot create, start, complete or delete them', function () {
    [$workspace, $users, $project, $stages] = sprWorkspace();
    $sprint = sprSprint($workspace, $project, 'Sprint 1');
    $task = sprTask($project, $stages['todo'], $users['manager'], 'Shared task');
    $member = $users['member'];

    $this->actingAs($member)->get(route('sprints.index', $project))->assertOk();
    $this->actingAs($member)->get(route('projects.backlog', $project))->assertOk();
    $this->actingAs($member)->post(route('sprints.add-task', $sprint), ['task_id' => $task->id])->assertSessionHas('success');
    $this->actingAs($member)->post(route('sprints.store', $project), ['name' => 'Mine'])->assertForbidden();
    $this->actingAs($member)->post(route('sprints.start', $sprint))->assertForbidden();
    $this->actingAs($member)->post(route('sprints.complete', $sprint))->assertForbidden();
    $this->actingAs($member)->delete(route('sprints.destroy', $sprint))->assertForbidden();

    expect($sprint->fresh()->status)->toBe('planning');
});

test('a client has no backlog, sprints or board', function () {
    [, $users, $project] = sprWorkspace();

    $this->actingAs($users['client'])->get(route('projects.backlog', $project))->assertForbidden();
    $this->actingAs($users['client'])->get(route('sprints.index', $project))->assertForbidden();
    $this->actingAs($users['client'])->get(route('projects.board', $project))->assertForbidden();
});

// ── Board ─────────────────────────────────────────────────────────────────────

test('the board shows the active sprint by default and every task on request', function () {
    [$workspace, $users, $project, $stages] = sprWorkspace();
    $sprint = sprSprint($workspace, $project, 'Sprint 1', 'active');
    $inSprint = sprTask($project, $stages['todo'], $users['manager'], 'In sprint', 2);
    sprTask($project, $stages['todo'], $users['manager'], 'Not in sprint');
    $sprint->tasks()->attach($inSprint->id);

    $this->actingAs($users['member'])->get(route('projects.board', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('projects/Board')
            ->where('filters.scope', 'sprint')
            ->where('activeSprint.name', 'Sprint 1')
            ->where('activeSprint.days_left', 4)
            ->has('stages', TaskStage::where('workspace_id', $workspace->id)->count())
            ->has('tasks', 1)
            ->where('tasks.0.title', 'In sprint'));

    $this->actingAs($users['member'])->get(route('projects.board', [$project, 'scope' => 'all']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.scope', 'all')->has('tasks', 2));
});

// ── Story points ──────────────────────────────────────────────────────────────

test('story points are saved on tasks and must be a small whole number', function () {
    [, $users, $project] = sprWorkspace();

    $this->actingAs($users['manager'])->post(route('tasks.store'), [
        'project_id' => $project->id, 'title' => 'Estimate me', 'priority' => 'medium', 'story_points' => 8,
    ])->assertSessionHas('success');
    $task = Task::where('title', 'Estimate me')->firstOrFail();
    expect($task->story_points)->toBe(8);

    $this->actingAs($users['manager'])->put(route('tasks.update', $task), [
        'title' => 'Estimate me', 'priority' => 'medium', 'story_points' => 101,
    ])->assertSessionHasErrors('story_points');

    $this->actingAs($users['manager'])->put(route('tasks.update', $task), [
        'title' => 'Estimate me', 'priority' => 'medium', 'story_points' => 13,
    ])->assertSessionHas('success');
    expect($task->fresh()->story_points)->toBe(13);
});

// ── Role dashboards ───────────────────────────────────────────────────────────

test('dashboards show running sprints and what needs attention, per role', function () {
    [$workspace, $users, $project, $stages] = sprWorkspace();
    $sprint = sprSprint($workspace, $project, 'Sprint 1', 'active');
    $task = sprTask($project, $stages['todo'], $users['manager'], 'Late task');
    $task->update(['assigned_to' => $users['member']->id, 'end_date' => now()->subDay()->toDateString()]);
    $sprint->tasks()->attach($task->id);

    $this->actingAs($users['manager'])->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('userWorkspaceRole', 'manager')
            ->where('dashboardData.activeSprints.0.name', 'Sprint 1')
            ->where('dashboardData.attention.overdue_tasks', 1)
            ->has('dashboardData.attention.timesheet_approvals'));

    $this->actingAs($users['member'])->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboardData.activeSprints.0.my_open', 1)
            ->where('dashboardData.attention.overdue_tasks', 1)
            ->missing('dashboardData.attention.timesheet_approvals')
            ->where('dashboardData.weekHours', 0));

    $this->actingAs($users['client'])->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboardData.activeSprints', [])
            ->where('dashboardData.attention', []));
});
