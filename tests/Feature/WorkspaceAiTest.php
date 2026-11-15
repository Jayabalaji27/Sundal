<?php
/**
 * Every AI feature runs on the workspace's AI (Settings → AI Assistant), not
 * an OpenAI key: AI Project Generator, AI agents and the chatbot.
 */

use App\Models\AiProviderSetting;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Ai\AiProviderFactory;
use App\Services\Ai\Providers\FakeProvider;
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
    $this->workspace = Workspace::create(['name' => 'AI WS', 'slug' => 'wsai-' . uniqid(), 'owner_id' => $owner->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    $owner->update(['current_workspace_id' => $this->workspace->id]);
    $owner->assignRole('company');

    $this->manager = User::factory()->create(['type' => 'company', 'current_workspace_id' => $this->workspace->id]);
    WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $this->manager->id, 'role' => 'manager', 'status' => 'active']);
    $this->manager->assignRole('manager');
});

function wsaiConnect(Workspace $workspace): void
{
    AiProviderSetting::withoutGlobalScope('workspace')->create([
        'workspace_id' => $workspace->id, 'provider' => 'openrouter', 'model' => 'free/model:free',
        'api_key' => 'sk-or-test-1234567890', 'api_key_last4' => '7890', 'managers_enabled' => true, 'retention_days' => 90,
    ]);
}

test('the AI Project Generator uses the workspace AI, even with no OpenAI key', function () {
    wsaiConnect($this->workspace);
    // A free model wrapping the JSON in a sentence and a code fence.
    $reply = "Here is your plan:\n```json\n" . json_encode([
        'title' => 'CodeHunters Launch', 'description' => 'A coding challenge site.', 'priority' => 'URGENT', 'estimated_hours' => '40',
        'tasks' => [
            ['title' => 'Set up auth', 'priority' => 'urgent', 'estimated_hours' => 8],
            ['title' => '', 'priority' => 'low'],
            ['title' => 'Leaderboard', 'priority' => 'weird'],
        ],
    ]) . "\n```\nGood luck!";
    app()->instance(AiProviderFactory::class, AiProviderFactory::fake(new FakeProvider([['text' => $reply]])));

    $plan = $this->actingAs($this->manager)->postJson(route('ai.projects.parse'), ['requirements' => 'create a project for codehunters'])
        ->assertOk()->json('plan');

    expect($plan['title'])->toBe('CodeHunters Launch')
        ->and($plan['priority'])->toBe('urgent')
        ->and(collect($plan['tasks'])->pluck('title')->all())->toBe(['Set up auth', 'Leaderboard'])
        ->and(collect($plan['tasks'])->pluck('priority')->all())->toBe(['critical', 'medium']);
    expect(\App\Models\AiUsage::where('workspace_id', $this->workspace->id)->exists())->toBeTrue();
});

test('without any AI connected the generator says where to set it up', function () {
    $this->actingAs($this->manager)->postJson(route('ai.projects.parse'), ['requirements' => 'create a project for codehunters'])
        ->assertStatus(422)
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'Settings → AI Assistant'));
});

test('the chatbot answers with the workspace AI', function () {
    wsaiConnect($this->workspace);
    app()->instance(AiProviderFactory::class, AiProviderFactory::fake(new FakeProvider([['text' => 'A sprint is a timebox.']])));

    $this->actingAs($this->manager)->postJson(route('chatbot.ask'), ['message' => 'What is a sprint?'])
        ->assertOk()->assertJsonPath('answer', 'A sprint is a timebox.');
});
