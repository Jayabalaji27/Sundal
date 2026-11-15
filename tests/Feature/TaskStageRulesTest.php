<?php
/**
 * Stage-move process rules (ChangeTaskStage): an unassigned task stays in the
 * first stage, moving back needs a reason (saved as a comment), and only the
 * owner or a manager can reopen a task from Done.
 */

use App\Models\Plan;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskStage;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

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

    $plan = Plan::where('is_default', true)->first();
    $owner = User::factory()->create(['type' => 'company', 'plan_id' => $plan?->id, 'plan_is_active' => 1]);
    $this->workspace = Workspace::create(['name' => 'Stage WS', 'slug' => 'stage-' . uniqid(), 'owner_id' => $owner->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    $owner->update(['current_workspace_id' => $this->workspace->id]);
    $owner->assignRole('company');
    $this->users = ['owner' => $owner->fresh()];
    foreach (['manager', 'member'] as $role) {
        $user = User::factory()->create(['type' => 'company', 'current_workspace_id' => $this->workspace->id]);
        WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active']);
        $user->assignRole($role);
        $this->users[$role] = $user;
    }
    $this->project = Project::create([
        'workspace_id' => $this->workspace->id, 'title' => 'Stage project', 'status' => 'active', 'priority' => 'medium',
        'progress' => 0, 'created_by' => $owner->id, 'visibility' => 'workspace',
    ]);
});

function tsrStage(string $name): TaskStage
{
    return TaskStage::where('workspace_id', test()->workspace->id)->where('name', $name)->firstOrFail();
}

function tsrTask(string $stage, ?User $assignee = null): Task
{
    return Task::create([
        'project_id' => test()->project->id, 'task_stage_id' => tsrStage($stage)->id, 'title' => 'Stage task ' . uniqid(),
        'priority' => 'medium', 'progress' => 0, 'created_by' => test()->users['owner']->id, 'assigned_to' => $assignee?->id,
    ]);
}

function tsrMove($test, User $user, Task $task, string $stage, ?string $reason = null)
{
    return $test->actingAs($user)->put(route('tasks.change-stage', $task), array_filter(['task_stage_id' => tsrStage($stage)->id, 'reason' => $reason]));
}

test('an unassigned task cannot leave the first stage', function () {
    $task = tsrTask('To Do');

    tsrMove($this, $this->users['owner'], $task, 'In Progress')->assertSessionHasErrors('task_stage_id');
    expect($task->fresh()->taskStage->name)->toBe('To Do');

    $task->update(['assigned_to' => $this->users['member']->id]);
    tsrMove($this, $this->users['owner'], $task, 'In Progress')->assertSessionHasNoErrors();
    expect($task->fresh()->taskStage->name)->toBe('In Progress');
});

test('a task with members but no single assignee counts as assigned', function () {
    $task = tsrTask('To Do');
    $task->members()->attach($this->users['member']->id, ['assigned_by' => $this->users['owner']->id, 'assigned_at' => now()]);

    tsrMove($this, $this->users['owner'], $task, 'In Progress')->assertSessionHasNoErrors();
    expect($task->fresh()->taskStage->name)->toBe('In Progress');
});

test('an unassigned task left in a later stage can still go back to the first one', function () {
    $task = tsrTask('In Progress');

    tsrMove($this, $this->users['owner'], $task, 'To Do', 'Nobody is on it yet')->assertSessionHasNoErrors();
    expect($task->fresh()->taskStage->name)->toBe('To Do');
});

test('moving back needs a reason, which is saved as a comment', function () {
    $task = tsrTask('Review', $this->users['member']);

    tsrMove($this, $this->users['member'], $task, 'In Progress')->assertSessionHasErrors('reason');
    expect($task->fresh()->taskStage->name)->toBe('Review');

    tsrMove($this, $this->users['member'], $task, 'In Progress', 'Failed QA on Safari')->assertSessionHasNoErrors();
    expect($task->fresh()->taskStage->name)->toBe('In Progress');

    $comment = TaskComment::where('task_id', $task->id)->latest('id')->first();
    expect($comment->comment)->toContain('Review')->toContain('In Progress')->toContain('Failed QA on Safari')
        ->and($comment->user_id)->toBe($this->users['member']->id);
});

test('moving forward needs no reason and adds no comment', function () {
    $task = tsrTask('In Progress', $this->users['member']);

    tsrMove($this, $this->users['member'], $task, 'Review')->assertSessionHasNoErrors();
    expect($task->fresh()->taskStage->name)->toBe('Review')
        ->and(TaskComment::where('task_id', $task->id)->count())->toBe(0);
});

test('only the owner or a manager can reopen a task from Done', function () {
    $task = tsrTask('Done', $this->users['member']);

    tsrMove($this, $this->users['member'], $task, 'In Progress', 'Bug came back')->assertSessionHasErrors('task_stage_id');
    expect($task->fresh()->taskStage->name)->toBe('Done');

    tsrMove($this, $this->users['manager'], $task, 'In Progress', 'Bug came back')->assertSessionHasNoErrors();
    expect($task->fresh()->taskStage->name)->toBe('In Progress');
});

test('the owner reopening from Done still gives a reason', function () {
    $task = tsrTask('Done', $this->users['member']);

    tsrMove($this, $this->users['owner'], $task, 'Review')->assertSessionHasErrors('reason');
    tsrMove($this, $this->users['owner'], $task, 'Review', 'Client found an issue')->assertSessionHasNoErrors();
    expect($task->fresh()->taskStage->name)->toBe('Review');
});

test('the history tab shows the stage move with its reason, and hides the reason comment', function () {
    $task = tsrTask('Review', $this->users['member']);
    tsrMove($this, $this->users['member'], $task, 'In Progress', 'Failed QA on Safari')->assertSessionHasNoErrors();

    $entries = $this->actingAs($this->users['owner'])->getJson(route('tasks.history', $task))->assertOk()->json('entries');

    $move = collect($entries)->firstWhere('kind', 'stage');
    expect($move['changes'][0]['from']['name'])->toBe('Review')
        ->and($move['changes'][0]['to']['name'])->toBe('In Progress')
        ->and($move['note'])->toBe('Failed QA on Safari')
        ->and($move['note_type'])->toBe('reason')
        ->and($move['actor']['name'])->toBe($this->users['member']->name)
        ->and(collect($entries)->where('kind', 'comment')->count())->toBe(0)
        ->and(collect($entries)->last()['kind'])->toBe('created');
});

test('the history tab is not shown for a task in another workspace', function () {
    $task = tsrTask('To Do');
    $outsider = User::factory()->create(['type' => 'company']);
    $other = Workspace::create(['name' => 'Other', 'slug' => 'other-' . uniqid(), 'owner_id' => $outsider->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $other->id, 'user_id' => $outsider->id, 'role' => 'owner', 'status' => 'active']);
    $outsider->update(['current_workspace_id' => $other->id]);
    $outsider->assignRole('company');

    $this->actingAs($outsider->fresh())->getJson(route('tasks.history', $task))->assertNotFound();
});
