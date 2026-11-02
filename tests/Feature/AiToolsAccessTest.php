<?php

/*
 * AI Tools (Risk Radar, Resource Conflicts, Standup Bot, custom agents) are for
 * owners and managers, and a manager only sees their own projects and people.
 */

use App\Models\Agent;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectClient;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\TaskStage;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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

/** [$owner, $workspace, $manager, $member, $otherMember, $client] */
function atWorkspace(): array
{
    $plan = Plan::where('is_default', true)->first();
    $owner = User::factory()->create(['type' => 'company', 'plan_id' => $plan?->id, 'plan_is_active' => 1]);
    $workspace = Workspace::create(['name' => 'AT WS', 'slug' => 'at-' . uniqid(), 'owner_id' => $owner->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    $owner->update(['current_workspace_id' => $workspace->id]);
    $owner->assignRole('company');

    $users = [];
    foreach (['manager', 'member', 'member', 'client'] as $role) {
        $user = User::factory()->create(['type' => 'company', 'current_workspace_id' => $workspace->id]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active']);
        $user->assignRole($role);
        $users[] = $user;
    }

    return [$owner, $workspace, ...$users];
}

/** A private, active project with the given members and clients. */
function atProject(Workspace $workspace, User $creator, string $title, array $members = [], array $clients = []): Project
{
    $project = Project::create([
        'workspace_id' => $workspace->id, 'title' => $title, 'status' => 'active',
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

/** $count open tasks due in a few days for $assignee: enough to count as a resource conflict. */
function atLoad(Project $project, User $assignee, int $count = 3): void
{
    $stage = TaskStage::forWorkspace($project->workspace_id)->ordered()->where('is_completed', false)->first()
        ?? TaskStage::create(['workspace_id' => $project->workspace_id, 'name' => 'To Do', 'color' => '#000', 'order' => 1, 'is_default' => true]);
    foreach (range(1, $count) as $i) {
        Task::create(['project_id' => $project->id, 'task_stage_id' => $stage->id, 'title' => "T$i " . uniqid(), 'priority' => 'medium',
            'progress' => 0, 'created_by' => $project->created_by, 'assigned_to' => $assignee->id, 'end_date' => now()->addDays(3)->toDateString()]);
    }
}

// ── Scope ─────────────────────────────────────────────────────────────────────

test('risk radar shows a manager only the projects they are on; the owner sees all', function () {
    [$owner, $ws, $manager] = atWorkspace();
    atProject($ws, $owner, 'Mine', [$manager]);
    atProject($ws, $owner, 'Other');

    $titles = fn (User $user) => collect($this->actingAs($user)->getJson(route('risk-radar.api'))->assertOk()->json())
        ->pluck('title')->sort()->values()->all();

    expect($titles($owner))->toBe(['Mine', 'Other'])
        ->and($titles($manager))->toBe(['Mine']);
});

test('resource conflicts only count the load on projects the manager is on', function () {
    [$owner, $ws, $manager, $alice, $bob] = atWorkspace();
    atLoad(atProject($ws, $owner, 'Mine', [$manager, $alice]), $alice);
    atLoad(atProject($ws, $owner, 'Other', [$bob]), $bob);

    $people = fn (User $user) => collect($this->actingAs($user)->getJson(route('resource-conflicts.api'))->assertOk()->json())
        ->pluck('user.id')->sort()->values()->all();

    expect($people($owner))->toBe(collect([$alice->id, $bob->id])->sort()->values()->all())
        ->and($people($manager))->toBe([$alice->id]);
});

test('standup leaves out clients, and a manager gets themselves and the people on their projects', function () {
    [$owner, $ws, $manager, $alice, $bob, $client] = atWorkspace();
    atProject($ws, $owner, 'Mine', [$manager, $alice], [$client]);
    atLoad(atProject($ws, $owner, 'Other', [$bob]), $bob);

    $people = fn (User $user) => collect($this->actingAs($user)->getJson(route('standup.api'))->assertOk()->json('standups'))
        ->pluck('user.id')->sort()->values()->all();

    expect($people($owner))->toBe(collect([$owner->id, $manager->id, $alice->id, $bob->id])->sort()->values()->all())
        ->and($people($manager))->toBe(collect([$manager->id, $alice->id])->sort()->values()->all());
});

test('a manager does not see blockers from projects they are not on', function () {
    [$owner, $ws, $manager, $alice] = atWorkspace();
    atProject($ws, $owner, 'Mine', [$manager, $alice]);
    $other = atProject($ws, $owner, 'Other', [$alice]);
    $stage = TaskStage::forWorkspace($ws->id)->ordered()->where('is_completed', false)->first();
    Task::create(['project_id' => $other->id, 'task_stage_id' => $stage->id, 'title' => 'Late', 'priority' => 'medium', 'progress' => 0,
        'created_by' => $owner->id, 'assigned_to' => $alice->id, 'end_date' => now()->subDays(2)->toDateString()]);

    $aliceFor = fn (User $user) => collect($this->actingAs($user)->getJson(route('standup.api'))->json('standups'))->firstWhere('user.id', $alice->id);

    expect($aliceFor($owner)['data']['blockers'])->toHaveCount(1)
        ->and($aliceFor($manager)['data']['blockers'])->toBe([]);
});

// ── Access ────────────────────────────────────────────────────────────────────

test('members and clients cannot open the AI tools or use agents', function () {
    [$owner, $ws, , $member, , $client] = atWorkspace();
    $agent = Agent::create(['workspace_id' => $ws->id, 'name' => 'Helper', 'is_active' => true, 'created_by' => $owner->id]);

    foreach ([$member, $client] as $user) {
        $this->actingAs($user)->get(route('ai.index'))->assertForbidden();
        $this->actingAs($user)->getJson(route('standup.api'))->assertForbidden();
        $this->actingAs($user)->getJson(route('risk-radar.api'))->assertForbidden();
        $this->actingAs($user)->get(route('agents.chat', $agent))->assertForbidden();
    }

    expect($member->fresh()->hasPermissionTo('agent_use'))->toBeFalse();
});

test('owners and managers can open the AI tools page', function () {
    [$owner, , $manager] = atWorkspace();

    $this->actingAs($owner)->get(route('ai.index'))->assertOk();
    $this->actingAs($manager)->get(route('ai.index'))->assertOk();
});

test('a manager\'s agent question uses the OpenAI key the owner saved', function () {
    [$owner, $ws, $manager] = atWorkspace();
    updateSetting('chatgptKey', 'sk-owner-key', $owner->id, $ws->id);
    $agent = Agent::create(['workspace_id' => $ws->id, 'name' => 'Helper', 'is_active' => true, 'created_by' => $owner->id]);
    Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'Hello']]]])]);

    $this->actingAs($manager)->postJson(route('agents.ask', $agent), ['message' => 'Hi'])
        ->assertOk()
        ->assertJson(['answer' => 'Hello']);

    Http::assertSent(fn (HttpRequest $request) => $request->hasHeader('Authorization', 'Bearer sk-owner-key'));
});
