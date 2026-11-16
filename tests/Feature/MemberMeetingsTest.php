<?php
/**
 * Members schedule meetings (2026-11-16): only for the projects they're on,
 * only with people in the workspace, and edit/cancel only their own.
 */

use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\ZoomMeeting;
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
});

/** Owner, manager, member and an outsider; the member is on "Mine" only, not on "Other". */
function mmSetup(): array
{
    $plan = Plan::where('is_default', true)->first();
    $owner = User::factory()->create(['type' => 'company', 'plan_id' => $plan?->id, 'plan_is_active' => 1]);
    $workspace = Workspace::create(['name' => 'Meet WS', 'slug' => 'meet-' . uniqid(), 'owner_id' => $owner->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    $owner->update(['current_workspace_id' => $workspace->id]);
    $owner->assignRole('company');

    $users = ['owner' => $owner];
    foreach (['manager', 'member', 'member2'] as $key) {
        $role = $key === 'member2' ? 'member' : $key;
        $user = User::factory()->create(['type' => $role, 'current_workspace_id' => $workspace->id]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active']);
        $user->assignRole($role);
        $users[$key] = $user;
    }
    $users['outsider'] = User::factory()->create(['type' => 'member']);

    $projects = [];
    foreach (['Mine', 'Other'] as $title) {
        $projects[$title] = Project::create([
            'workspace_id' => $workspace->id, 'title' => $title, 'status' => 'active', 'priority' => 'medium',
            'progress' => 0, 'created_by' => $owner->id, 'visibility' => 'private',
        ]);
        ProjectMember::create(['project_id' => $projects[$title]->id, 'user_id' => $users['manager']->id, 'role' => 'manager', 'assigned_by' => $owner->id, 'assigned_at' => now()]);
    }
    ProjectMember::create(['project_id' => $projects['Mine']->id, 'user_id' => $users['member']->id, 'role' => 'member', 'assigned_by' => $owner->id, 'assigned_at' => now()]);

    return [$workspace, $users, $projects];
}

function mmPayload(Project $project, array $memberIds): array
{
    return [
        'title' => 'Stand-up', 'start_time' => now()->addDay()->toDateTimeString(), 'duration' => 30,
        'timezone' => 'UTC', 'project_id' => $project->id, 'member_ids' => $memberIds,
    ];
}

test('the member role can schedule, edit and cancel Zoom and Google meetings', function () {
    [, $users] = mmSetup();
    foreach (['zoom', 'google'] as $provider) {
        foreach (['create', 'update', 'delete'] as $action) {
            expect($users['member']->hasPermissionTo("{$provider}_meeting_{$action}"))->toBeTrue();
        }
    }
});

test('a member cannot schedule a meeting for a project they are not on', function () {
    [, $users, $projects] = mmSetup();

    foreach (['zoom-meetings.store', 'google-meetings.store'] as $route) {
        $this->actingAs($users['member'])
            ->post(route($route), mmPayload($projects['Other'], [$users['member2']->id]))
            ->assertSessionHasErrors('project_id');
    }
});

test('a member can schedule a meeting for their own project', function () {
    [, $users, $projects] = mmSetup();

    $this->actingAs($users['member'])
        ->post(route('zoom-meetings.store'), mmPayload($projects['Mine'], [$users['member2']->id]))
        ->assertSessionHasNoErrors();

    expect(ZoomMeeting::where('project_id', $projects['Mine']->id)->where('user_id', $users['member']->id)->exists())->toBeTrue();
});

test('people outside the workspace cannot be invited', function () {
    [, $users, $projects] = mmSetup();

    $this->actingAs($users['member'])
        ->post(route('zoom-meetings.store'), mmPayload($projects['Mine'], [$users['outsider']->id]))
        ->assertSessionHasErrors('member_ids.0');
});

test('managers still schedule for any project in the workspace', function () {
    [, $users, $projects] = mmSetup();
    $projects['Other']->members()->where('user_id', $users['manager']->id)->delete();

    $this->actingAs($users['manager'])
        ->post(route('zoom-meetings.store'), mmPayload($projects['Other'], [$users['member']->id]))
        ->assertSessionHasNoErrors();
});

test('the meeting form only lists the member\'s own projects and their people', function () {
    [, $users, $projects] = mmSetup();

    $this->actingAs($users['member'])
        ->get(route('zoom-meetings.index'))
        ->assertInertia(fn ($page) => $page->has('projects', 1)->where('projects.0.title', 'Mine'));

    $this->actingAs($users['member'])->getJson(route('api.projects.members', $projects['Other']))->assertNotFound();
    $this->actingAs($users['member'])->getJson(route('api.projects.members', $projects['Mine']))->assertOk();
});

test('a member cannot edit or cancel a meeting someone else scheduled', function () {
    [, $users, $projects] = mmSetup();
    ProjectMember::create(['project_id' => $projects['Mine']->id, 'user_id' => $users['member2']->id, 'role' => 'member', 'assigned_by' => $users['owner']->id, 'assigned_at' => now()]);
    $this->actingAs($users['member2'])
        ->post(route('zoom-meetings.store'), mmPayload($projects['Mine'], [$users['member']->id]))
        ->assertSessionHasNoErrors();
    $meeting = ZoomMeeting::where('user_id', $users['member2']->id)->firstOrFail();

    $this->actingAs($users['member'])->delete(route('zoom-meetings.destroy', $meeting))->assertForbidden();
    $this->actingAs($users['member'])
        ->put(route('zoom-meetings.update', $meeting), mmPayload($projects['Mine'], [$users['member2']->id]))
        ->assertForbidden();
});
