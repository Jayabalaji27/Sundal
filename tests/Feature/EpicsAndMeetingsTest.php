<?php
/**
 * Project Epics and the project Meetings tab (Zoho Sprints' project tabs, 2026-10-19).
 */

use App\Models\Epic;
use App\Models\GoogleMeeting;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\TaskStage;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\ZoomMeeting;
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
        \App\Http\Middleware\CheckModuleAccess::class,
        \App\Http\Middleware\EnsureEmailIsVerified::class,
    ]);
    Cache::flush();
});

/** Owner, manager and member in one workspace; two projects the manager and member work on. */
function emSetup(): array
{
    $plan = Plan::where('is_default', true)->first();
    $owner = User::factory()->create(['type' => 'company', 'plan_id' => $plan?->id, 'plan_is_active' => 1]);
    $workspace = Workspace::create(['name' => 'Epic WS', 'slug' => 'epic-' . uniqid(), 'owner_id' => $owner->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    $owner->update(['current_workspace_id' => $workspace->id]);
    $owner->assignRole('company');

    $users = ['owner' => $owner];
    foreach (['manager', 'member'] as $role) {
        $user = User::factory()->create(['type' => $role, 'current_workspace_id' => $workspace->id]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active']);
        $user->assignRole($role);
        $users[$role] = $user;
    }

    $projects = [];
    foreach (['Shop', 'Mobile'] as $title) {
        $project = Project::create([
            'workspace_id' => $workspace->id, 'title' => $title, 'status' => 'active', 'priority' => 'medium',
            'progress' => 0, 'created_by' => $users['manager']->id, 'visibility' => 'private',
        ]);
        foreach (['manager', 'member'] as $role) {
            ProjectMember::create(['project_id' => $project->id, 'user_id' => $users[$role]->id, 'role' => $role, 'assigned_by' => $owner->id, 'assigned_at' => now()]);
        }
        $projects[] = $project;
    }

    return [$workspace, $users, $projects];
}

function emItem(Project $project, User $creator, string $title, array $attrs = []): Task
{
    $stage = TaskStage::where('workspace_id', $project->workspace_id)->where('name', $attrs['stage'] ?? 'To Do')->first();
    unset($attrs['stage']);

    return Task::create(array_merge([
        'project_id' => $project->id, 'type' => 'task', 'task_stage_id' => $stage->id, 'title' => $title,
        'priority' => 'medium', 'progress' => $stage->is_completed ? 100 : 0, 'created_by' => $creator->id,
    ], $attrs));
}

describe('epics', function () {
    test('a manager creates an epic; a member may not; the page shows its progress', function () {
        [$workspace, $users, [$shop]] = emSetup();

        $this->actingAs($users['member'])->post(route('epics.store', $shop), ['name' => 'Nope'])->assertForbidden();

        $this->actingAs($users['manager'])->post(route('epics.store', $shop), [
            'name' => 'Checkout', 'description' => 'Cart to payment', 'color' => Epic::COLORS[2],
            'owner_id' => $users['member']->id, 'start_date' => '2026-10-20', 'end_date' => '2026-11-30',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $epic = Epic::where('name', 'Checkout')->firstOrFail();
        expect($epic->status)->toBe('open')->and($epic->color)->toBe(Epic::COLORS[2])->and($epic->created_by)->toBe($users['manager']->id);

        $done = emItem($shop, $users['manager'], 'Pay button', ['stage' => 'Done', 'story_points' => 3, 'epic_id' => $epic->id]);
        emItem($shop, $users['manager'], 'Coupon bug', ['type' => 'bug', 'severity' => 'major', 'story_points' => 2, 'epic_id' => $epic->id]);
        emItem($shop, $users['manager'], 'Not filed');

        $this->actingAs($users['member'])->get(route('epics.index', $shop))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('projects/Epics')
                ->has('epics', 1)
                ->where('epics.0.name', 'Checkout')
                ->where('epics.0.item_count', 2)
                ->where('epics.0.done_count', 1)
                ->where('epics.0.bug_count', 1)
                ->where('epics.0.points', 5)
                ->where('epics.0.done_points', 3)
                ->where('epics.0.owner.id', $users['member']->id)
                ->has('unfiled', 1)
                ->where('unfiled.0.title', 'Not filed'));
    });

    test('an epic owner is on the project, and it ends after it starts', function () {
        [$workspace, $users, [$shop]] = emSetup();
        $stranger = User::factory()->create();

        $this->actingAs($users['manager'])->post(route('epics.store', $shop), ['name' => 'X', 'owner_id' => $stranger->id])
            ->assertSessionHasErrors('owner_id');
        $this->actingAs($users['manager'])->post(route('epics.store', $shop), ['name' => 'X', 'start_date' => '2026-11-10', 'end_date' => '2026-11-01'])
            ->assertSessionHasErrors('end_date');
    });

    test('a member files items into an epic and takes them out; an item of another project is refused', function () {
        [$workspace, $users, [$shop, $mobile]] = emSetup();
        $epic = Epic::create(['workspace_id' => $workspace->id, 'project_id' => $shop->id, 'name' => 'Checkout', 'created_by' => $users['manager']->id]);
        $item = emItem($shop, $users['manager'], 'Cart totals');
        $elsewhere = emItem($mobile, $users['manager'], 'Login on Android');

        $this->actingAs($users['member'])->post(route('epics.add-task', $epic), ['task_id' => $item->id])->assertSessionHasNoErrors();
        expect($item->fresh()->epic_id)->toBe($epic->id);

        $this->actingAs($users['member'])->post(route('epics.add-task', $epic), ['task_id' => $elsewhere->id])->assertSessionHasErrors('task_id');
        expect($elsewhere->fresh()->epic_id)->toBeNull();

        $this->actingAs($users['member'])->delete(route('epics.remove-task', $epic), ['task_id' => $item->id]);
        expect($item->fresh()->epic_id)->toBeNull();
    });

    test('the task form files an item under an epic of the same project only', function () {
        [$workspace, $users, [$shop, $mobile]] = emSetup();
        $shopEpic = Epic::create(['workspace_id' => $workspace->id, 'project_id' => $shop->id, 'name' => 'Checkout']);
        $mobileEpic = Epic::create(['workspace_id' => $workspace->id, 'project_id' => $mobile->id, 'name' => 'Onboarding']);

        $this->actingAs($users['manager'])->post(route('tasks.store'), ['project_id' => $shop->id, 'title' => 'Wrong epic', 'priority' => 'low', 'epic_id' => $mobileEpic->id])
            ->assertSessionHasErrors('epic_id');

        $this->actingAs($users['manager'])->post(route('tasks.store'), ['project_id' => $shop->id, 'title' => 'Right epic', 'priority' => 'low', 'epic_id' => $shopEpic->id])
            ->assertSessionHasNoErrors();
        $task = Task::where('title', 'Right epic')->firstOrFail();
        expect($task->epic_id)->toBe($shopEpic->id);

        // Editing without sending the epic keeps it; sending none clears it.
        $this->actingAs($users['manager'])->put(route('tasks.update', $task), ['title' => 'Right epic', 'priority' => 'high'])->assertSessionHasNoErrors();
        expect($task->fresh()->epic_id)->toBe($shopEpic->id);
        $this->actingAs($users['manager'])->put(route('tasks.update', $task), ['title' => 'Right epic', 'priority' => 'high', 'epic_id' => ''])->assertSessionHasNoErrors();
        expect($task->fresh()->epic_id)->toBeNull();
    });

    test('the board and the task form know the epics', function () {
        [$workspace, $users, [$shop]] = emSetup();
        $open = Epic::create(['workspace_id' => $workspace->id, 'project_id' => $shop->id, 'name' => 'Checkout', 'color' => '#10b981']);
        Epic::create(['workspace_id' => $workspace->id, 'project_id' => $shop->id, 'name' => 'Old', 'status' => 'done']);
        emItem($shop, $users['manager'], 'Pay button', ['epic_id' => $open->id]);

        $this->actingAs($users['manager'])->get(route('projects.board', [$shop, 'scope' => 'all']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tasks.0.epic', ['id' => $open->id, 'name' => 'Checkout', 'color' => '#10b981'])
                // Only open epics are offered in the form.
                ->has('taskForm.projects.0.epics', 1)
                ->where('taskForm.projects.0.epics.0.name', 'Checkout'));
    });

    test('deleting an epic keeps its items in the project', function () {
        [$workspace, $users, [$shop]] = emSetup();
        $epic = Epic::create(['workspace_id' => $workspace->id, 'project_id' => $shop->id, 'name' => 'Checkout']);
        $item = emItem($shop, $users['manager'], 'Pay button', ['epic_id' => $epic->id]);

        $this->actingAs($users['member'])->delete(route('epics.destroy', $epic))->assertForbidden();
        $this->actingAs($users['manager'])->delete(route('epics.destroy', $epic))->assertSessionHas('success');

        expect(Epic::find($epic->id))->toBeNull()
            ->and(Task::find($item->id))->not->toBeNull()
            ->and($item->fresh()->epic_id)->toBeNull();
    });
});

describe('project meetings', function () {
    test('the project tab lists its Zoom and Google meetings; others see only meetings they are in', function () {
        [$workspace, $users, [$shop, $mobile]] = emSetup();
        $meeting = fn (string $model, Project $project, User $host, string $title, string $when, array $invited = []) => tap($model::withoutGlobalScopes()->create([
            'title' => $title, 'start_time' => $when, 'end_time' => \Carbon\Carbon::parse($when)->addHour(), 'duration' => 60,
            'user_id' => $host->id, 'workspace_id' => $workspace->id, 'project_id' => $project->id, 'join_url' => 'https://example.test/j/1',
        ]), fn ($m) => $m->members()->attach($invited));

        $meeting(ZoomMeeting::class, $shop, $users['owner'], 'Sprint review', now()->addDay()->toDateTimeString(), [$users['manager']->id]);
        $meeting(GoogleMeeting::class, $shop, $users['owner'], 'Client call', now()->subDay()->toDateTimeString());
        $meeting(ZoomMeeting::class, $mobile, $users['owner'], 'Other project', now()->addDay()->toDateTimeString(), [$users['manager']->id]);

        $this->actingAs($users['owner'])->get(route('projects.meetings', $shop))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('projects/Meetings')
                ->has('meetings', 2)
                ->where('meetings.0.title', 'Client call')
                ->where('meetings.0.provider', 'google')
                ->where('meetings.1.title', 'Sprint review')
                ->where('meetings.1.provider', 'zoom')
                ->where('meetings.1.join_url', 'https://example.test/j/1'));

        // The manager is invited to the review only.
        $this->actingAs($users['manager'])->get(route('projects.meetings', $shop))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('meetings', 1)->where('meetings.0.title', 'Sprint review'));
    });

    test('managers schedule, edit and cancel Zoom and Google meetings; members and clients only view and join', function () {
        [$workspace, $users] = emSetup();
        $abilities = ['zoom_meeting_create', 'zoom_meeting_update', 'zoom_meeting_delete', 'google_meeting_create', 'google_meeting_update', 'google_meeting_delete'];

        foreach ($abilities as $ability) {
            expect($users['manager']->hasPermissionTo($ability))->toBeTrue()
                ->and($users['member']->hasPermissionTo($ability))->toBeFalse();
        }
        expect($users['member']->hasPermissionTo('zoom_meeting_join'))->toBeTrue()
            ->and(\Spatie\Permission\Models\Role::findByName('client')->hasPermissionTo('google_meeting_join'))->toBeTrue()
            ->and(\Spatie\Permission\Models\Role::findByName('client')->hasPermissionTo('google_meeting_create'))->toBeFalse();
    });

    test('a manager changes only the meetings they scheduled; the owner changes any', function () {
        [$workspace, $users, [$shop]] = emSetup();
        $make = fn (string $model, User $host, array $invited = []) => tap($model::withoutGlobalScopes()->create([
            'title' => 'Call', 'start_time' => now()->addDay(), 'end_time' => now()->addDay()->addHour(), 'duration' => 60,
            'user_id' => $host->id, 'workspace_id' => $workspace->id, 'project_id' => $shop->id,
        ]), fn ($m) => $m->members()->attach($invited));

        foreach ([[ZoomMeeting::class, 'zoom-meetings'], [GoogleMeeting::class, 'google-meetings']] as [$model, $prefix]) {
            $ownersMeeting = $make($model, $users['owner'], [$users['manager']->id]);
            $managersMeeting = $make($model, $users['manager']);
            $param = $model === ZoomMeeting::class ? 'zoomMeeting' : 'googleMeeting';

            // The detail page offers Edit and Delete only where they work.
            $this->actingAs($users['manager'])->get(route("{$prefix}.show", [$param => $ownersMeeting->id]))
                ->assertInertia(fn (Assert $page) => $page->where('permissions.update', false)->where('permissions.delete', false));
            $this->actingAs($users['manager'])->get(route("{$prefix}.show", [$param => $managersMeeting->id]))
                ->assertInertia(fn (Assert $page) => $page->where('permissions.update', true)->where('permissions.delete', true));

            $this->actingAs($users['manager'])->delete(route("{$prefix}.destroy", [$param => $ownersMeeting->id]))->assertForbidden();
            expect($model::withoutGlobalScopes()->find($ownersMeeting->id))->not->toBeNull();

            $this->actingAs($users['manager'])->delete(route("{$prefix}.destroy", [$param => $managersMeeting->id]))->assertRedirect();
            expect($model::withoutGlobalScopes()->find($managersMeeting->id))->toBeNull();

            $this->actingAs($users['owner'])->delete(route("{$prefix}.destroy", [$param => $ownersMeeting->id]))->assertRedirect();
            expect($model::withoutGlobalScopes()->find($ownersMeeting->id))->toBeNull();
        }
    });
});

// ── History and integration logs (19 Oct) ────────────────────────────────────

describe('history and integration logs', function () {
    test('filing into an epic, and planning into and out of a sprint, is in the item history', function () {
        [$workspace, $users, [$shop]] = emSetup();
        $epic = Epic::create(['workspace_id' => $workspace->id, 'project_id' => $shop->id, 'name' => 'Checkout', 'created_by' => $users['manager']->id]);
        $sprint = \App\Models\Sprint::create(['project_id' => $shop->id, 'workspace_id' => $workspace->id, 'name' => 'Sprint 1', 'status' => 'planning', 'created_by' => $users['manager']->id]);
        $item = emItem($shop, $users['manager'], 'Cart totals');

        $this->actingAs($users['member'])->post(route('epics.add-task', $epic), ['task_id' => $item->id])->assertSessionHasNoErrors();
        $this->actingAs($users['member'])->post(route('sprints.add-task', $sprint), ['task_id' => $item->id])->assertSessionHasNoErrors();
        $this->actingAs($users['member'])->delete(route('sprints.remove-task', $sprint), ['task_id' => $item->id])->assertSessionHasNoErrors();

        $history = \Spatie\Activitylog\Models\Activity::where('subject_type', $item->getMorphClass())->where('subject_id', $item->id)->orderBy('id')->get();
        expect($history->pluck('event')->all())->toBe(['created', 'updated', 'added_to_sprint', 'removed_from_sprint'])
            ->and($history[1]->properties['attributes'])->toBe(['epic_id' => $epic->id])
            ->and($history[2]->properties['attributes'])->toBe(['sprint_id' => $sprint->id, 'sprint' => 'Sprint 1'])
            ->and($history[2]->causer_id)->toBe($users['member']->id)
            ->and($history[3]->properties['source'])->toBe('screen');
    });

    test('a scheduled meeting is in the history without its host link, and in the integrations log', function () {
        [$workspace, $users, [$shop]] = emSetup();
        $this->mock(\App\Services\ZoomService::class, function ($zoom) {
            $zoom->shouldReceive('hasValidCredentials')->andReturn(true);
            $zoom->shouldReceive('createMeeting')->andReturn(['success' => true, 'data' => ['id' => '123', 'start_url' => 'https://zoom.test/s/host-secret', 'join_url' => 'https://zoom.test/j/1']]);
        });
        $log = captureLog('integrations');

        $this->actingAs($users['manager']);
        $meeting = app(\App\Actions\Meetings\ScheduleMeeting::class)->handle($users['manager'], 'zoom', [
            'title' => 'Stand-up', 'start_time' => now()->addDay(), 'duration' => 15, 'project_id' => $shop->id, 'member_ids' => [$users['member']->id],
        ]);

        $created = \Spatie\Activitylog\Models\Activity::where('subject_type', $meeting->getMorphClass())->where('subject_id', $meeting->id)->where('event', 'created')->firstOrFail();
        expect($created->causer_id)->toBe($users['manager']->id)
            ->and($created->properties['attributes'])->toHaveKey('join_url')
            ->and($created->properties['attributes'])->not->toHaveKey('start_url');

        expect(loggedLines($log, 'meetings.schedule')[0])->toMatchArray([
            'ok' => true, 'provider' => 'zoom', 'project_id' => $shop->id, 'meeting_id' => $meeting->id, 'attendees' => 1,
            'join_link' => true, 'source' => 'screen', 'user_id' => $users['manager']->id, 'workspace_id' => $workspace->id,
        ]);
    });

    test('emails are logged with masked recipients; secrets are masked; responses carry a request id', function () {
        $log = captureLog('integrations');
        \Illuminate\Support\Facades\Mail::raw('Hi', fn ($m) => $m->to('jaya@example.com')->subject('Welcome'));
        expect(loggedLines($log, 'mail.send')[0])->toMatchArray(['ok' => true, 'to' => ['ja***@example.com'], 'subject' => 'Welcome']);

        expect(\App\Services\IntegrationLog::redact('POST https://api.telegram.org/bot123456:ABC-def_GHI/sendMessage failed'))->toBe('POST https://api.telegram.org/bot***/sendMessage failed')
            ->and(\App\Services\IntegrationLog::redact('https://hooks.slack.com/services/T000/B000/XXXX said no'))->toBe('https://hooks.slack.com/services/*** said no')
            ->and(\App\Services\IntegrationLog::redact('{"access_token":"ya29.abc","expires_in":3599}'))->toBe('{"access_token":"***","expires_in":3599}')
            ->and(\App\Services\IntegrationLog::redact('Authorization: Bearer eyJhbGciOi.x.y'))->toBe('Authorization: Bearer ***')
            ->and(\App\Services\IntegrationLog::host('https://hooks.zapier.com/hooks/catch/123/abc/'))->toBe('hooks.zapier.com');

        [$workspace, $users, [$shop]] = emSetup();
        expect($this->actingAs($users['member'])->get(route('epics.index', $shop))->headers->get('X-Request-Id'))->toMatch('/^[0-9a-f-]{36}$/')
            // An id set by the proxy in front of the app is kept.
            ->and($this->actingAs($users['member'])->get(route('epics.index', $shop), ['X-Request-Id' => 'nginx-req-12345678'])->headers->get('X-Request-Id'))->toBe('nginx-req-12345678');
    });
});
