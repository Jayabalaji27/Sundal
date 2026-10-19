<?php
/**
 * Bugs are tasks of type "bug": one Board, Backlog, Sprints and Tasks list
 * (business validation follow-up, 2026-10-19).
 */

use App\Models\Plan;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStage;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\ProjectHealthService;
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

/** Owner + workspace (with the default stages To Do … Done) + a project. */
function batSetup(): array
{
    $plan = Plan::where('is_default', true)->first();
    $owner = User::factory()->create(['type' => 'company', 'plan_id' => $plan?->id, 'plan_is_active' => 1]);
    $workspace = Workspace::create(['name' => 'Bugs WS', 'slug' => 'bat-' . uniqid(), 'owner_id' => $owner->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    $owner->update(['current_workspace_id' => $workspace->id]);
    $owner->assignRole('company');

    $project = Project::create([
        'workspace_id' => $workspace->id, 'title' => 'Shop', 'status' => 'active',
        'priority' => 'medium', 'progress' => 0, 'created_by' => $owner->id,
    ]);

    return [$owner, $workspace, $project];
}

function batStage(Workspace $workspace, string $name): TaskStage
{
    return TaskStage::where('workspace_id', $workspace->id)->where('name', $name)->firstOrFail();
}

function batItem(Project $project, User $creator, array $attrs = []): Task
{
    return Task::create(array_merge([
        'project_id' => $project->id, 'type' => 'task', 'title' => 'Item ' . uniqid(), 'priority' => 'medium', 'progress' => 0,
        'task_stage_id' => TaskStage::where('workspace_id', $project->workspace_id)->orderBy('order')->value('id'),
        'created_by' => $creator->id,
    ], $attrs));
}

describe('the task form makes bugs', function () {
    test('a bug gets its details and a Major severity unless one is given', function () {
        [$owner, , $project] = batSetup();

        $this->actingAs($owner)->post(route('tasks.store'), [
            'project_id' => $project->id, 'type' => 'bug', 'title' => 'Checkout crashes', 'priority' => 'high',
            'steps_to_reproduce' => "1. Add to cart\n2. Pay", 'environment' => 'Safari 18',
        ])->assertSessionHasNoErrors();

        $bug = Task::where('title', 'Checkout crashes')->first();
        expect($bug->type)->toBe('bug')
            ->and($bug->isBug())->toBeTrue()
            ->and($bug->severity)->toBe('major')
            ->and($bug->steps_to_reproduce)->toBe("1. Add to cart\n2. Pay")
            ->and($bug->environment)->toBe('Safari 18')
            ->and($bug->taskStage->name)->toBe('To Do');
    });

    test('a plain task carries no bug details, and turning a bug into a task clears them', function () {
        [$owner, , $project] = batSetup();

        $this->actingAs($owner)->post(route('tasks.store'), [
            'project_id' => $project->id, 'title' => 'Write docs', 'priority' => 'low', 'severity' => 'blocker', 'environment' => 'x',
        ])->assertSessionHasNoErrors();
        $task = Task::where('title', 'Write docs')->first();
        expect($task->type)->toBe('task')->and($task->severity)->toBeNull()->and($task->environment)->toBeNull();

        $bug = batItem($project, $owner, ['type' => 'bug', 'severity' => 'critical', 'actual_behavior' => 'Blank page']);
        $this->actingAs($owner)->put(route('tasks.update', $bug), ['title' => $bug->title, 'priority' => 'medium', 'type' => 'task'])
            ->assertSessionHasNoErrors();
        expect($bug->fresh()->type)->toBe('task')->and($bug->fresh()->severity)->toBeNull()->and($bug->fresh()->actual_behavior)->toBeNull();
    });

    test('editing a bug without sending its type keeps it a bug with its severity', function () {
        [$owner, , $project] = batSetup();
        $bug = batItem($project, $owner, ['type' => 'bug', 'severity' => 'blocker', 'steps_to_reproduce' => 'Open it']);

        $this->actingAs($owner)->put(route('tasks.update', $bug), ['title' => $bug->title, 'priority' => 'critical'])->assertSessionHasNoErrors();

        expect($bug->fresh()->type)->toBe('bug')->and($bug->fresh()->severity)->toBe('blocker')->and($bug->fresh()->steps_to_reproduce)->toBe('Open it');
    });

    test('an unknown type or severity is refused', function () {
        [$owner, , $project] = batSetup();

        $this->actingAs($owner)->post(route('tasks.store'), ['project_id' => $project->id, 'title' => 'X', 'priority' => 'low', 'type' => 'epic'])
            ->assertSessionHasErrors('type');
        $this->actingAs($owner)->post(route('tasks.store'), ['project_id' => $project->id, 'title' => 'X', 'priority' => 'low', 'type' => 'bug', 'severity' => 'huge'])
            ->assertSessionHasErrors('severity');
    });
});

describe('one list, one board', function () {
    test('the Tasks list shows tasks and bugs together, or only bugs, by severity too', function () {
        [$owner, , $project] = batSetup();
        batItem($project, $owner, ['title' => 'A task']);
        batItem($project, $owner, ['title' => 'Minor bug', 'type' => 'bug', 'severity' => 'minor']);
        batItem($project, $owner, ['title' => 'Blocker bug', 'type' => 'bug', 'severity' => 'blocker']);

        $this->actingAs($owner)->get(route('tasks.index'))
            ->assertInertia(fn (Assert $page) => $page->has('tasks.data', 3));
        $this->actingAs($owner)->get(route('tasks.index', ['type' => 'bug']))
            ->assertInertia(fn (Assert $page) => $page->has('tasks.data', 2)->where('filters.type', 'bug'));
        $this->actingAs($owner)->get(route('tasks.index', ['type' => 'bug', 'severity' => 'blocker']))
            ->assertInertia(fn (Assert $page) => $page->has('tasks.data', 1)->where('tasks.data.0.title', 'Blocker bug'));
    });

    test('the Board holds both, each card saying what it is; Bugs narrows it', function () {
        [$owner, , $project] = batSetup();
        batItem($project, $owner, ['title' => 'A task']);
        batItem($project, $owner, ['title' => 'A bug', 'type' => 'bug', 'severity' => 'critical']);

        $this->actingAs($owner)->get(route('projects.board', $project))
            ->assertInertia(fn (Assert $page) => $page->component('projects/Board')->has('tasks', 2));

        $this->actingAs($owner)->get(route('projects.board', [$project, 'type' => 'bug']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('tasks', 1)
                ->where('tasks.0.title', 'A bug')
                ->where('tasks.0.type', 'bug')
                ->where('tasks.0.severity', 'critical')
                ->where('filters.type', 'bug'));
    });
});

describe('old bug links', function () {
    test('the Bugs page is the Tasks list showing bugs; an old bug opens the task it became', function () {
        [$owner, , $project] = batSetup();
        $bug = batItem($project, $owner, ['type' => 'bug', 'severity' => 'major']);
        $bug->forceFill(['legacy_bug_id' => 41])->save(); // set by the merge migration only

        $this->actingAs($owner)->get(route('bugs.index', ['project_id' => $project->id, 'action' => 'create']))
            ->assertRedirect(route('tasks.index', ['type' => 'bug', 'project_id' => $project->id, 'action' => 'create']));

        $this->actingAs($owner)->get(route('bugs.show', 41))->assertRedirect(route('tasks.show', $bug));
        $this->actingAs($owner)->get(route('bugs.show', 999))->assertNotFound();
    });

    test('a task link visited as a page opens it on the Tasks list; the modal still gets JSON', function () {
        [$owner, , $project] = batSetup();
        $bug = batItem($project, $owner, ['type' => 'bug', 'severity' => 'minor']);

        $this->actingAs($owner)->get(route('tasks.show', $bug), ['Accept' => 'text/html'])
            ->assertRedirect(route('tasks.index', ['task_id' => $bug->id, 'type' => 'bug']));
        // The modal's fetch() asks for */*.
        $this->actingAs($owner)->get(route('tasks.show', $bug), ['Accept' => '*/*'])
            ->assertOk()->assertJsonPath('task.type', 'bug')->assertJsonPath('task.severity', 'minor');
    });
});

describe('copies', function () {
    test('duplicating a migrated bug makes a new bug, without the old bug link', function () {
        [$owner, , $project] = batSetup();
        $bug = batItem($project, $owner, ['type' => 'bug', 'severity' => 'critical', 'steps_to_reproduce' => 'Tap pay']);
        $bug->forceFill(['legacy_bug_id' => 7])->save();

        $this->actingAs($owner)->post(route('tasks.duplicate', $bug))->assertSessionHas('success');

        $copy = Task::where('title', $bug->title . ' (Copy)')->first();
        expect($copy->type)->toBe('bug')
            ->and($copy->severity)->toBe('critical')
            ->and($copy->steps_to_reproduce)->toBe('Tap pay')
            ->and($copy->legacy_bug_id)->toBeNull();
    });
});

describe('sample data', function () {
    test('the showcase seeder makes its bugs as bug tasks, in the stage each status means', function () {
        $this->seed(\Database\Seeders\DemoShowcaseSeeder::class);

        $bugs = Task::withoutGlobalScopes()->where('type', 'bug')->with('taskStage')->get();
        expect($bugs)->toHaveCount(11)
            ->and($bugs->whereNull('created_by'))->toBeEmpty()
            ->and($bugs->whereNull('severity'))->toBeEmpty()
            ->and($bugs->countBy(fn ($bug) => $bug->taskStage->name)->sortKeys()->all())
            ->toBe(['Done' => 3, 'In Progress' => 2, 'Review' => 2, 'To Do' => 4])
            ->and(\Illuminate\Support\Facades\DB::table('bugs')->count())->toBe(0);
    });

    test('the demo bug seeder makes bug tasks with comments', function () {
        [$owner, , $project] = batSetup();
        \App\Models\ProjectMember::create(['project_id' => $project->id, 'user_id' => $owner->id, 'role' => 'manager', 'assigned_by' => $owner->id, 'assigned_at' => now()]);

        $this->seed(\Database\Seeders\BugSeeder::class);

        $bugs = Task::bugs()->where('project_id', $project->id)->get();
        expect($bugs)->toHaveCount(8)
            ->and($bugs->pluck('created_by')->unique()->all())->toBe([$owner->id])
            ->and(\App\Models\TaskComment::whereIn('task_id', $bugs->pluck('id'))->count())->toBeGreaterThanOrEqual(8);
    });
});

describe('bug status words', function () {
    test('bug words map onto task stages', function () {
        [, $workspace] = batSetup();
        $stage = fn (string $word) => TaskStage::forBugStatus($workspace->id, $word)?->name;

        expect($stage('Resolved'))->toBe('Done')
            ->and($stage('Closed'))->toBe('Done')
            ->and($stage('In Testing'))->toBe('Review')
            ->and($stage('Open'))->toBe('In Progress')
            ->and($stage('New'))->toBe('To Do')
            ->and($stage('Banana'))->toBeNull();
    });
});

describe('everything that counts bugs reads bug tasks', function () {
    test('project health counts open critical bugs, not ones in a done stage', function () {
        [$owner, $workspace, $project] = batSetup();
        batItem($project, $owner, ['type' => 'bug', 'severity' => 'blocker']);
        batItem($project, $owner, ['type' => 'bug', 'severity' => 'critical', 'task_stage_id' => batStage($workspace, 'Done')->id]);
        batItem($project, $owner, ['type' => 'bug', 'severity' => 'minor']);

        expect(app(ProjectHealthService::class)->calculate($project)['metrics']['critical_bugs'])->toBe(1);
    });

    test('a client portal report becomes a bug on the board; the portal counts open bugs', function () {
        [$owner, $workspace, $project] = batSetup();
        $project->update(['portal_enabled' => true, 'portal_token' => str_repeat('a', 48)]);

        $this->post(route('portal.submit-bug', $project->portal_token), ['title' => 'Logo is blurry', 'priority' => 'urgent'])
            ->assertSessionHas('success');

        $bug = Task::bugs()->where('title', 'Logo is blurry')->first();
        expect($bug)->not->toBeNull()
            ->and($bug->priority)->toBe('critical')
            ->and($bug->severity)->toBe('major')
            ->and($bug->created_by)->toBe($owner->id)
            ->and($bug->taskStage->name)->toBe('To Do');

        batItem($project, $owner, ['type' => 'bug', 'severity' => 'minor', 'task_stage_id' => batStage($workspace, 'Done')->id]);
        $this->get(route('portal.show', $project->portal_token))
            ->assertInertia(fn (Assert $page) => $page->where('bugStats.total', 2)->where('bugStats.open', 1));
    });

    test('search lists a bug once, under bugs', function () {
        [$owner, , $project] = batSetup();
        batItem($project, $owner, ['title' => 'Login page copy']);
        batItem($project, $owner, ['title' => 'Login fails on Safari', 'type' => 'bug', 'severity' => 'major']);

        $this->actingAs($owner)->getJson(route('search', ['q' => 'Login']))
            ->assertOk()
            ->assertJsonPath('tasks.0.title', 'Login page copy')
            ->assertJsonCount(1, 'tasks')
            ->assertJsonPath('bugs.0.title', 'Login fails on Safari')
            ->assertJsonCount(1, 'bugs');
    });

    test('the delete summary counts tasks and bugs apart', function () {
        [$owner, , $project] = batSetup();
        batItem($project, $owner);
        batItem($project, $owner, ['type' => 'bug', 'severity' => 'minor']);

        expect($project->deletionSummary())->toMatchArray(['tasks' => 1, 'bugs' => 1]);
    });
});
