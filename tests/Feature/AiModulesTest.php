<?php

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\TaskStage;
use App\Models\Task;
use App\Services\ProjectHealthService;
use App\Services\ScopeCreepService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->withoutMiddleware([
        \App\Http\Middleware\CheckInstallation::class,
        \App\Http\Middleware\ShareGlobalSettings::class,
        \App\Http\Middleware\DemoModeMiddleware::class,
    ]);
});

function makeOwner(): array {
    $owner = User::factory()->create(['type' => 'company']);
    $workspace = Workspace::create(['name' => 'Test WS', 'slug' => 'tw-'.uniqid(), 'owner_id' => $owner->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner']);
    $owner->update(['current_workspace_id' => $workspace->id]);
    $owner->assignRole('company');
    return [$owner, $workspace];
}

function makeProject(int $wsId, int $userId, array $attrs = []): Project {
    return Project::create(array_merge(['workspace_id' => $wsId, 'title' => 'Test Project', 'status' => 'active', 'priority' => 'medium', 'progress' => 0, 'created_by' => $userId], $attrs));
}

function makeStage(int $wsId): TaskStage {
    return TaskStage::create(['workspace_id' => $wsId, 'name' => 'To Do', 'color' => '#000', 'order' => 0, 'is_default' => true]);
}

// ── 1. ProjectHealthService ──────────────────────────────────────────────────

describe('ProjectHealthService', function () {

    test('fresh project scores 100', function () {
        [$owner, $ws] = makeOwner();
        $result = (new ProjectHealthService())->calculate(makeProject($ws->id, $owner->id));
        expect($result['score'])->toBe(100)->and($result['status'])->toBe('healthy');
    });

    test('overdue tasks reduce score', function () {
        [$owner, $ws] = makeOwner();
        $project = makeProject($ws->id, $owner->id);
        $stage = makeStage($ws->id);
        foreach (range(1,3) as $i) Task::create(['project_id'=>$project->id,'task_stage_id'=>$stage->id,'title'=>"T$i",'priority'=>'medium','end_date'=>now()->subDays(5)->toDateString(),'progress'=>0,'created_by'=>$owner->id]);
        $result = (new ProjectHealthService())->calculate($project);
        expect($result['score'])->toBeLessThan(100)->and($result['status'])->toBeIn(['at_risk','critical']);
    });

    test('passed deadline reduces score', function () {
        [$owner, $ws] = makeOwner();
        $project = makeProject($ws->id, $owner->id, ['start_date'=>now()->subDays(60)->toDateString(),'deadline'=>now()->subDays(10)->toDateString(),'progress'=>10]);
        $result = (new ProjectHealthService())->calculate($project);
        expect($result['score'])->toBeLessThan(80);
    });

    test('score always between 0 and 100', function () {
        [$owner, $ws] = makeOwner();
        $project = makeProject($ws->id, $owner->id, ['start_date'=>now()->subDays(120)->toDateString(),'deadline'=>now()->subDays(60)->toDateString(),'progress'=>0]);
        $stage = makeStage($ws->id);
        foreach (range(1,8) as $i) Task::create(['project_id'=>$project->id,'task_stage_id'=>$stage->id,'title'=>"T$i",'priority'=>'critical','end_date'=>now()->subDays(30)->toDateString(),'progress'=>0,'created_by'=>$owner->id]);
        $result = (new ProjectHealthService())->calculate($project);
        expect($result['score'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
    });
});

// ── 2. ScopeCreepService ─────────────────────────────────────────────────────

describe('ScopeCreepService', function () {

    test('fresh project reports none', function () {
        [$owner, $ws] = makeOwner();
        $result = (new ScopeCreepService())->detect(makeProject($ws->id, $owner->id));
        expect($result['status'])->toBe('none')->and($result['total_tasks'])->toBe(0);
    });

    test('detects scope creep on late additions', function () {
        [$owner, $ws] = makeOwner();
        $project = makeProject($ws->id, $owner->id);
        $project->update(['created_at' => now()->subDays(30)]);
        $stage = makeStage($ws->id);
        foreach (range(1,2) as $i) { $t = Task::create(['project_id'=>$project->id,'task_stage_id'=>$stage->id,'title'=>"Base$i",'priority'=>'medium','progress'=>0,'created_by'=>$owner->id]); $t->update(['created_at'=>now()->subDays(29)]); }
        foreach (range(1,4) as $i) Task::create(['project_id'=>$project->id,'task_stage_id'=>$stage->id,'title'=>"New$i",'priority'=>'medium','progress'=>0,'created_by'=>$owner->id]);
        $result = (new ScopeCreepService())->detect($project);
        expect($result['total_tasks'])->toBe(6)->and($result['status'])->toBeIn(['moderate','high','severe']);
    });
});

// ── 3. Project Health Endpoint ───────────────────────────────────────────────

describe('GET /projects/{project}/health', function () {
    test('unauthenticated returns redirect', function () {
        [$owner, $ws] = makeOwner();
        $this->get(route('projects.health', makeProject($ws->id, $owner->id)->id))->assertRedirect();
    });
    test('returns health JSON', function () {
        [$owner, $ws] = makeOwner();
        $this->actingAs($owner)->getJson(route('projects.health', makeProject($ws->id, $owner->id)->id))->assertOk()->assertJsonStructure(['score','status','factors','metrics']);
    });
    test('score between 0 and 100', function () {
        [$owner, $ws] = makeOwner();
        $score = $this->actingAs($owner)->getJson(route('projects.health', makeProject($ws->id, $owner->id)->id))->json('score');
        expect($score)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
    });
    test('returns 403 for wrong workspace', function () {
        [$o1, $ws1] = makeOwner(); [$o2, $ws2] = makeOwner();
        $this->actingAs($o1)->getJson(route('projects.health', makeProject($ws2->id, $o2->id)->id))->assertStatus(403);
    });
});

// ── 4. Scope Creep Endpoint ───────────────────────────────────────────────────

describe('GET /projects/{project}/scope-creep', function () {
    test('unauthenticated returns redirect', function () {
        [$owner, $ws] = makeOwner();
        $this->get(route('projects.scope-creep', makeProject($ws->id, $owner->id)->id))->assertRedirect();
    });
    test('returns scope-creep JSON', function () {
        [$owner, $ws] = makeOwner();
        $this->actingAs($owner)->getJson(route('projects.scope-creep', makeProject($ws->id, $owner->id)->id))->assertOk()->assertJsonStructure(['baseline_tasks','total_tasks','creep_rate','status','message']);
    });
});

// ── 5. Standup API ────────────────────────────────────────────────────────────

describe('GET /api/standup', function () {
    test('unauthenticated returns redirect', function () { $this->get(route('standup.api'))->assertRedirect(); });
    test('returns standup JSON', function () {
        [$owner] = makeOwner();
        $this->actingAs($owner)->getJson(route('standup.api'))->assertOk()->assertJsonStructure(['standups','date']);
    });
    test('accepts date param', function () {
        [$owner] = makeOwner();
        $date = now()->subDays(2)->toDateString();
        expect($this->actingAs($owner)->getJson(route('standup.api', ['date'=>$date]))->assertOk()->json('date'))->toBe($date);
    });
});

// ── 6. Risk Radar API ─────────────────────────────────────────────────────────

describe('GET /api/risk-radar', function () {
    test('unauthenticated returns redirect', function () { $this->get(route('risk-radar.api'))->assertRedirect(); });
    test('returns array', function () {
        [$owner] = makeOwner();
        $this->actingAs($owner)->getJson(route('risk-radar.api'))->assertOk()->assertJsonIsArray();
    });
});

// ── 7. Resource Conflicts API ─────────────────────────────────────────────────

describe('GET /api/resource-conflicts', function () {
    test('unauthenticated returns redirect', function () { $this->get(route('resource-conflicts.api'))->assertRedirect(); });
    test('returns array', function () {
        [$owner] = makeOwner();
        $this->actingAs($owner)->getJson(route('resource-conflicts.api'))->assertOk()->assertJsonIsArray();
    });
});

// ── 8. AI Parse ───────────────────────────────────────────────────────────────

describe('POST /ai/projects/parse', function () {
    test('unauthenticated returns 401', function () { $this->postJson(route('ai.projects.parse'), ['requirements'=>'x'])->assertStatus(401); });
    test('validates minimum 20 chars', function () {
        [$owner] = makeOwner();
        $this->actingAs($owner)->postJson(route('ai.projects.parse'), ['requirements'=>'short'])->assertStatus(422)->assertJsonValidationErrors(['requirements']);
    });
    test('returns 422 without API key', function () {
        [$owner] = makeOwner();
        $this->actingAs($owner)->postJson(route('ai.projects.parse'), ['requirements'=>'Build a customer portal with auth, invoices, and project tracking for software agencies'])->assertStatus(422)->assertJson(['success'=>false]);
    });
});

// ── 9. AI Project Create ──────────────────────────────────────────────────────

describe('POST /ai/projects/create', function () {
    test('unauthenticated returns 401', function () { $this->postJson(route('ai.projects.create'), [])->assertStatus(401); });
    test('creates project and tasks', function () {
        [$owner, $ws] = makeOwner();
        $response = $this->actingAs($owner)->postJson(route('ai.projects.create'), [
            'title'=>'Customer Portal','description'=>'Client portal','priority'=>'high','estimated_hours'=>80,
            'tasks'=>[['title'=>'Design','description'=>'UI','priority'=>'high','estimated_hours'=>8],['title'=>'Auth','description'=>'Login','priority'=>'high','estimated_hours'=>12]],
        ]);
        $response->assertOk()->assertJson(['success'=>true]);
        $this->assertDatabaseHas('projects', ['id'=>$response->json('project.id'), 'workspace_id'=>$ws->id]);
        expect(Task::where('project_id', $response->json('project.id'))->count())->toBe(2);
    });
    test('rejects missing title', function () {
        [$owner] = makeOwner();
        $before = Project::count();
        $this->actingAs($owner)->postJson(route('ai.projects.create'), ['priority'=>'medium'])->assertStatus(422);
        expect(Project::count())->toBe($before);
    });
    test('rejects invalid priority', function () {
        [$owner] = makeOwner();
        $this->actingAs($owner)->postJson(route('ai.projects.create'), ['title'=>'X','priority'=>'invalid'])->assertStatus(422)->assertJsonValidationErrors(['priority']);
    });
});
