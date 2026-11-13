<?php
/**
 * Task comments as the team's communication channel: who may comment, @mentions,
 * and the bell notifications they send.
 */

use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectMember;
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
        \App\Http\Middleware\EnsureEmailIsVerified::class,
    ]);
    Cache::flush();
});

/** Owner + workspace + users with the given workspace roles. */
function tcmWorkspace(array $roles = ['manager', 'member', 'member', 'client']): array
{
    $plan = Plan::where('is_default', true)->first();
    $owner = User::factory()->create(['type' => 'company', 'plan_id' => $plan?->id, 'plan_is_active' => 1]);
    $workspace = Workspace::create(['name' => 'TC WS', 'slug' => 'tc-' . uniqid(), 'owner_id' => $owner->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    $owner->update(['current_workspace_id' => $workspace->id]);
    $owner->assignRole('company');

    $users = [];
    foreach ($roles as $role) {
        $user = User::factory()->create(['type' => $role === 'client' ? 'client' : 'company', 'current_workspace_id' => $workspace->id]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active']);
        $user->assignRole($role);
        $users[] = $user;
    }

    return [$owner, $workspace, $users];
}

/** A private project with the given members, and one task in it. */
function tcmTask(Workspace $workspace, User $creator, array $members = [], ?User $assignee = null): Task
{
    $project = Project::create([
        'workspace_id' => $workspace->id, 'title' => 'P' . uniqid(), 'status' => 'active',
        'priority' => 'medium', 'progress' => 0, 'created_by' => $creator->id, 'visibility' => 'private',
    ]);
    foreach ($members as $user) {
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $user->id, 'role' => 'member', 'assigned_by' => $creator->id]);
    }
    $stage = TaskStage::firstOrCreate(['workspace_id' => $workspace->id, 'name' => 'To Do'], ['color' => '#999', 'order' => 1]);

    return Task::create([
        'project_id' => $project->id, 'task_stage_id' => $stage->id, 'title' => 'Logo exploration',
        'priority' => 'medium', 'progress' => 0, 'created_by' => $creator->id, 'assigned_to' => $assignee?->id,
    ]);
}

/** Bell notifications of the user in the task's workspace, as [type => count]. */
function tcmBell(User $user): array
{
    return $user->fresh()->workspaceNotifications()->get()
        ->groupBy(fn ($n) => $n->data['type'] ?? '')
        ->map->count()->all();
}

describe('who may comment', function () {
    test('a user of another company cannot comment on, edit or delete comments of a task', function () {
        [$ownerA, $wsA] = tcmWorkspace([]);
        $task = tcmTask($wsA, $ownerA);
        $comment = TaskComment::create(['task_id' => $task->id, 'user_id' => $ownerA->id, 'comment' => 'Hi', 'mentions' => []]);

        [$ownerB] = tcmWorkspace([]);

        $this->actingAs($ownerB)->post(route('task-comments.store', $task), ['comment' => 'Sneaky'])->assertNotFound();
        $this->actingAs($ownerB)->put(route('task-comments.update', $comment), ['comment' => 'Changed'])->assertNotFound();
        $this->actingAs($ownerB)->delete(route('task-comments.destroy', $comment))->assertNotFound();

        expect(TaskComment::where('task_id', $task->id)->pluck('comment')->all())->toBe(['Hi']);
    });

    test('a user of another company cannot open the task', function () {
        [$ownerA, $wsA] = tcmWorkspace([]);
        $task = tcmTask($wsA, $ownerA);
        [$ownerB] = tcmWorkspace([]);

        $this->actingAs($ownerB)->getJson(route('tasks.show', $task))->assertNotFound();
    });

    test('a member who is not on a private project cannot comment on its tasks', function () {
        [$owner, $ws, [, $member]] = tcmWorkspace();
        $task = tcmTask($ws, $owner);

        $this->actingAs($member)->post(route('task-comments.store', $task), ['comment' => 'Hi'])->assertNotFound();
        expect(TaskComment::count())->toBe(0);
    });

    test('a project member can comment', function () {
        [$owner, $ws, [, $member]] = tcmWorkspace();
        $task = tcmTask($ws, $owner, [$member]);

        $this->actingAs($member)->post(route('task-comments.store', $task), ['comment' => 'On it'])->assertRedirect();
        expect(TaskComment::where('task_id', $task->id)->value('comment'))->toBe('On it');
    });
});

describe('notifications', function () {
    test('a mentioned teammate gets a mention, the assignee and creator a comment notice, the author nothing', function () {
        [$owner, $ws, [$manager, $member, $other]] = tcmWorkspace();
        $task = tcmTask($ws, $manager, [$manager, $member, $other], assignee: $member);

        $this->actingAs($owner)->post(route('task-comments.store', $task), [
            'comment' => "@{$other->name} can you share the brand colours?",
            'mentions' => [$other->id],
        ])->assertRedirect();

        expect(tcmBell($other))->toBe(['mention' => 1])
            ->and(tcmBell($member))->toBe(['comment' => 1])   // assignee
            ->and(tcmBell($manager))->toBe(['comment' => 1])  // creator
            ->and(tcmBell($owner))->toBe([]);                 // author

        $note = $other->fresh()->workspaceNotifications()->first()->data;
        expect($note['title'])->toContain($owner->name)->toContain('Logo exploration')
            ->and($note['link'])->toContain('task_id=' . $task->id)
            ->and($note['workspace_id'])->toBe($ws->id);
    });

    test('a mentioned assignee gets one mention, not a mention and a comment notice', function () {
        [$owner, $ws, [, $member]] = tcmWorkspace();
        $task = tcmTask($ws, $owner, [$member], assignee: $member);

        $this->actingAs($owner)->post(route('task-comments.store', $task), ['comment' => '@x', 'mentions' => [$member->id]]);

        expect(tcmBell($member))->toBe(['mention' => 1]);
    });

    test('people who cannot see the task are not notified, even when mentioned', function () {
        [$owner, $ws, [, $member, , $client]] = tcmWorkspace();
        $task = tcmTask($ws, $owner);                       // private, member not on it
        [$ownerB] = tcmWorkspace([]);                       // another company

        $this->actingAs($owner)->post(route('task-comments.store', $task), [
            'comment' => 'hello', 'mentions' => [$member->id, $client->id, $ownerB->id, 999999, 'abc'],
        ])->assertRedirect();

        expect(tcmBell($member))->toBe([])
            ->and(tcmBell($client))->toBe([])
            ->and($ownerB->fresh()->notifications()->count())->toBe(0)
            ->and(TaskComment::first()->mentions)->toBe([]);
    });

    test('editing a comment notifies only people newly mentioned', function () {
        [$owner, $ws, [$manager, $member]] = tcmWorkspace();
        $task = tcmTask($ws, $owner, [$manager, $member]);

        $this->actingAs($owner)->post(route('task-comments.store', $task), ['comment' => '@m', 'mentions' => [$manager->id]]);
        $comment = TaskComment::first();
        $this->actingAs($owner)->put(route('task-comments.update', $comment), [
            'comment' => '@m @n', 'mentions' => [$manager->id, $member->id],
        ])->assertRedirect();

        expect(tcmBell($manager))->toBe(['mention' => 1])
            ->and(tcmBell($member))->toBe(['mention' => 1])
            ->and($comment->fresh()->mentions)->toBe([$manager->id, $member->id]);
    });
});
