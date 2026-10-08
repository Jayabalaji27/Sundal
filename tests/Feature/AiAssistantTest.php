<?php
/**
 * AI Assistant (BYOA), Phase 1: access rules, BYOA settings, the tool loop
 * and the confirm-card flow. Runs against FakeProvider: no network, no cost.
 */

use App\Models\AiConversation;
use App\Models\AiProviderSetting;
use App\Models\AiToolCall;
use App\Models\AiUsage;
use App\Models\Bug;
use App\Models\BugStatus;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Task;
use App\Models\TaskStage;
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
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(PlanSeeder::class);
    // CheckModuleAccess stays on: the AI add-on gate is part of what's tested.
    $this->withoutMiddleware([
        \App\Http\Middleware\CheckInstallation::class,
        \App\Http\Middleware\ShareGlobalSettings::class,
        \App\Http\Middleware\DemoModeMiddleware::class,
        \App\Http\Middleware\CheckPlanAccess::class,
        \App\Http\Middleware\CheckPlanLimits::class,
        \App\Http\Middleware\EnsureEmailIsVerified::class,
    ]);
    config(['app.is_saas' => true]);
    Cache::flush();
});

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Owner with the AI add-on, a workspace, and one user per other role. */
function aiWorkspace(bool $withAddon = true): array
{
    $plan = Plan::where('is_default', true)->first();
    $addon = Plan::where('plan_type', 'addon')->first();

    $owner = User::factory()->create(['type' => 'company', 'plan_id' => $plan?->id, 'plan_is_active' => 1]);
    if ($withAddon) {
        $owner->update(['addon_plan_id' => $addon->id, 'addon_is_active' => 1, 'addon_expire_date' => null]);
    }
    $workspace = Workspace::create(['name' => 'AI WS', 'slug' => 'ai-' . uniqid(), 'owner_id' => $owner->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    $owner->update(['current_workspace_id' => $workspace->id]);
    $owner->assignRole('company');

    $users = ['owner' => $owner->fresh()];
    foreach (['manager', 'member', 'client'] as $role) {
        $user = User::factory()->create(['type' => $role === 'client' ? 'client' : 'company', 'current_workspace_id' => $workspace->id]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active']);
        $user->assignRole($role);
        $users[$role] = $user;
    }

    // WorkspaceObserver seeds the default stages (To Do … Done) and bug statuses.
    return [$workspace, $users];
}

function aiSettings(Workspace $workspace, array $attrs = []): AiProviderSetting
{
    return AiProviderSetting::withoutGlobalScope('workspace')->create(array_merge([
        'workspace_id' => $workspace->id,
        'provider' => 'anthropic',
        'model' => 'claude-sonnet-5-5',
        'api_key' => 'sk-ant-test-1234567890',
        'api_key_last4' => '7890',
        'managers_enabled' => true,
        'retention_days' => 90,
    ], $attrs));
}

function aiProject(Workspace $workspace, User $creator, string $title = 'Website Redesign'): Project
{
    return Project::create([
        'workspace_id' => $workspace->id, 'title' => $title, 'status' => 'active', 'priority' => 'medium',
        'progress' => 0, 'created_by' => $creator->id, 'visibility' => 'workspace',
    ]);
}

function aiTask(Project $project, User $creator, string $title, array $attrs = []): Task
{
    $stage = TaskStage::where('workspace_id', $project->workspace_id)->orderBy('order')->first();

    return Task::create(array_merge([
        'project_id' => $project->id, 'task_stage_id' => $stage->id, 'title' => $title,
        'priority' => 'medium', 'progress' => 0, 'created_by' => $creator->id,
    ], $attrs));
}

function fakeAi(array $script): FakeProvider
{
    $fake = new FakeProvider($script);
    app()->instance(AiProviderFactory::class, AiProviderFactory::fake($fake));

    return $fake;
}

function toolNames(FakeProvider $fake): array
{
    return collect($fake->requests[0]->tools)->pluck('name')->sort()->values()->all();
}

// ── Access ────────────────────────────────────────────────────────────────────

describe('access', function () {
    test('owner and manager can open the AI Assistant page', function (string $role) {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);

        $this->actingAs($users[$role])->get(route('ai-assistant.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('ai-assistant/index')
                ->where('access', 'allowed')
                ->where('isOwner', $role === 'owner'));
    })->with(['owner', 'manager']);

    test('members and clients get a 403 on every AI Assistant route', function (string $role) {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $user = $users[$role];

        $this->actingAs($user)->get(route('ai-assistant.index'))->assertForbidden();
        $this->actingAs($user)->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertForbidden();
        $this->actingAs($user)->putJson(route('ai-assistant.settings.update'), [])->assertForbidden();
    })->with(['member', 'client']);

    test('the shared aiAssistant prop is null for members and clients', function () {
        [$workspace, $users] = aiWorkspace();

        $this->actingAs($users['member'])->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.aiAssistant', null));
    });

    test('without the AI add-on the owner is sent to the plans page and the manager to the dashboard', function () {
        [$workspace, $users] = aiWorkspace(withAddon: false);
        aiSettings($workspace);

        $this->actingAs($users['owner'])->get(route('ai-assistant.index'))->assertRedirect(route('plans.index'));
        $this->actingAs($users['manager'])->get(route('ai-assistant.index'))->assertRedirect(route('dashboard'));
        $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertStatus(402);
    });

    test('a manager cannot chat when the owner turned managers off', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace, ['managers_enabled' => false]);
        $fake = fakeAi([['text' => 'hello']]);

        $this->actingAs($users['manager'])->get(route('ai-assistant.index'))
            ->assertInertia(fn (Assert $page) => $page->where('access', 'managers_off'));
        $this->actingAs($users['manager'])->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertForbidden();
        expect($fake->requests)->toBeEmpty();
    });
});

// ── BYOA settings ─────────────────────────────────────────────────────────────

describe('settings', function () {
    test('owner saves a key; it is encrypted at rest and only the last 4 characters are shown', function () {
        [$workspace, $users] = aiWorkspace();

        $this->actingAs($users['owner'])->put(route('ai-assistant.settings.update'), [
            'provider' => 'anthropic', 'model' => 'claude-sonnet-5-5', 'api_key' => 'sk-ant-secret-key-ABCD',
            'managers_enabled' => true, 'retention_days' => 90,
        ])->assertSessionHasNoErrors();

        $raw = DB::table('ai_provider_settings')->where('workspace_id', $workspace->id)->value('api_key');
        expect($raw)->not->toContain('sk-ant-secret-key')
            ->and(AiProviderSetting::withoutGlobalScope('workspace')->first()->api_key)->toBe('sk-ant-secret-key-ABCD');

        $this->actingAs($users['owner'])->get(route('ai-assistant.index'))
            ->assertInertia(fn (Assert $page) => $page->where('settings.masked_key', '••••ABCD')->missing('settings.api_key'));
    });

    test('managers cannot change or test the settings', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);

        $this->actingAs($users['manager'])->putJson(route('ai-assistant.settings.update'), ['provider' => 'openai'])->assertForbidden();
        $this->actingAs($users['manager'])->postJson(route('ai-assistant.settings.test'), ['provider' => 'openai'])->assertForbidden();
    });

    test('only Azure OpenAI hosts are accepted as an endpoint', function (string $endpoint, bool $ok) {
        [$workspace, $users] = aiWorkspace();

        $response = $this->actingAs($users['owner'])->putJson(route('ai-assistant.settings.update'), [
            'provider' => 'azure_openai', 'model' => 'gpt-deploy', 'api_key' => 'azure-key-12345678',
            'azure_endpoint' => $endpoint, 'azure_deployment' => 'gpt-deploy', 'retention_days' => 90,
        ]);

        $ok ? $response->assertSessionHasNoErrors() : $response->assertJsonValidationErrors('azure_endpoint');
    })->with([
        ['https://my-company.openai.azure.com', true],
        ['http://169.254.169.254', false],
        ['https://localhost:11434', false],
        ['https://evil.example.com', false],
    ]);

    test('an OpenRouter key and vendor/model id can be saved', function () {
        [$workspace, $users] = aiWorkspace();

        $this->actingAs($users['owner'])->putJson(route('ai-assistant.settings.update'), [
            'provider' => 'openrouter', 'model' => 'openai/gpt-4o', 'api_key' => 'sk-or-v1-test-12345678', 'retention_days' => 90,
        ])->assertSessionHasNoErrors();

        expect(AiProviderSetting::withoutGlobalScope('workspace')->first()->provider)->toBe('openrouter');
    });

    test('an empty company key never falls back to a server key', function () {
        $provider = new \App\Services\Ai\Providers\PrismProvider(\Prism\Prism\Enums\Provider::Anthropic, 'claude-sonnet-5-5', '');

        expect(fn () => $provider->run(new \App\Services\Ai\AiRequest('system', [['role' => 'user', 'content' => 'hi']])))
            ->toThrow(\App\Services\Ai\AiProviderException::class, 'No API key is saved for this AI provider.');
    });

    test('local and unknown providers are rejected', function () {
        [$workspace, $users] = aiWorkspace();

        $this->actingAs($users['owner'])->putJson(route('ai-assistant.settings.update'), [
            'provider' => 'ollama', 'model' => 'llama3', 'api_key' => 'whatever-1234',
        ])->assertJsonValidationErrors('provider');
    });

    test('test connection passes only when the model calls the test tool', function () {
        [$workspace, $users] = aiWorkspace();
        $payload = ['provider' => 'anthropic', 'model' => 'claude-sonnet-5-5', 'api_key' => 'sk-ant-test-12345678'];

        fakeAi([['tool' => 'confirm_connection', 'args' => ['status' => 'ok']], ['text' => 'done']]);
        $this->actingAs($users['owner'])->postJson(route('ai-assistant.settings.test'), $payload)->assertJsonPath('passed', true);

        fakeAi([['text' => 'I cannot use tools']]);
        $this->actingAs($users['owner'])->postJson(route('ai-assistant.settings.test'), $payload)->assertJsonPath('passed', false);
    });
});

// ── Conversation and read tools ───────────────────────────────────────────────

describe('chat', function () {
    test('a read tool answers from workspace data and is logged', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $project = aiProject($workspace, $users['owner']);
        aiTask($project, $users['owner'], 'Fix header', ['end_date' => now()->subDays(2)->toDateString()]);
        aiTask($project, $users['owner'], 'Write docs', ['end_date' => now()->addDays(10)->toDateString()]);

        $fake = fakeAi([
            ['tool' => 'list_tasks', 'args' => ['overdue' => true]],
            fn ($request, $results) => ['text' => str_contains($results[0]['result'], 'Fix header') ? 'One overdue task: Fix header.' : 'none'],
        ]);

        $response = $this->actingAs($users['manager'])->postJson(route('ai-assistant.send'), ['content' => 'What is overdue?'])->assertOk();

        expect($response->json('messages.1.content'))->toBe('One overdue task: Fix header.')
            ->and($fake->toolResults[0]['result'])->not->toContain('Write docs')
            ->and(AiToolCall::withoutGlobalScope('workspace')->where('tool', 'list_tasks')->value('status'))->toBe('done')
            ->and(AiUsage::withoutGlobalScope('workspace')->count())->toBe(1);
    });

    test('owners and managers get the Phase 1 tools', function (string $role) {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $fake = fakeAi([['text' => 'ok']]);

        $this->actingAs($users[$role])->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertOk();

        expect(toolNames($fake))->toBe([
            'assign_bug', 'assign_task', 'change_task_status', 'create_task',
            'list_bugs', 'list_projects', 'list_tasks', 'list_team_members',
        ]);
    })->with(['owner', 'manager']);

    test('a read-only model gets no write tools', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace, ['model' => 'claude-haiku-4-5-20251001']);
        $fake = fakeAi([['text' => 'ok']]);

        $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertOk();

        expect(toolNames($fake))->toBe(['list_bugs', 'list_projects', 'list_tasks', 'list_team_members']);
    });

    test('nothing works before a provider is connected', function () {
        [$workspace, $users] = aiWorkspace();

        $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'hi'])
            ->assertStatus(502)
            ->assertJsonPath('error', __('No AI provider is connected yet. Ask your company owner to set one up.'));
    });

    test('the monthly token cap stops new messages', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace, ['monthly_token_cap' => 1000]);
        AiUsage::withoutGlobalScope('workspace')->create([
            'workspace_id' => $workspace->id, 'user_id' => $users['owner']->id, 'provider' => 'anthropic',
            'model' => 'claude-sonnet-5-5', 'input_tokens' => 900, 'output_tokens' => 200,
        ]);
        $fake = fakeAi([['text' => 'ok']]);

        $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertStatus(502);
        expect($fake->requests)->toBeEmpty();
    });

    test('users only see their own conversations', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $conversation = AiConversation::withoutGlobalScope('workspace')->create(['workspace_id' => $workspace->id, 'user_id' => $users['manager']->id, 'title' => 'Mine']);

        $this->actingAs($users['owner'])->getJson(route('ai-assistant.conversations.show', $conversation))->assertNotFound();
        $this->actingAs($users['manager'])->getJson(route('ai-assistant.conversations.show', $conversation))->assertOk();
    });
});

// ── Write tools and confirm cards ─────────────────────────────────────────────

describe('confirm cards', function () {
    test('assign_task changes nothing until the user confirms, then records who did it', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $ravi = User::factory()->create(['name' => 'Ravi Kumar']);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $ravi->id, 'role' => 'member', 'status' => 'active']);
        $task = aiTask(aiProject($workspace, $users['owner']), $users['owner'], 'Login bug fix');

        fakeAi([
            ['tool' => 'assign_task', 'args' => ['task' => 'Login bug fix', 'assignee' => 'Ravi', 'due_date' => '2026-10-09']],
            ['text' => 'Please confirm the card.'],
        ]);
        $response = $this->actingAs($users['manager'])->postJson(route('ai-assistant.send'), ['content' => 'Assign the login bug fix to Ravi, due Friday'])->assertOk();

        $card = $response->json('messages.1.cards.0');
        expect($card['status'])->toBe('pending')
            ->and($card['details'])->toHaveKey('New assignee', 'Ravi Kumar')
            ->and($task->fresh()->assigned_to)->toBeNull();

        $this->actingAs($users['manager'])->postJson(route('ai-assistant.tool-calls.confirm', $card['id']))
            ->assertOk()
            ->assertJsonPath('card.status', 'done');

        $call = AiToolCall::withoutGlobalScope('workspace')->find($card['id']);
        expect($task->fresh()->assigned_to)->toBe($ravi->id)
            ->and($task->fresh()->end_date->format('Y-m-d'))->toBe('2026-10-09')
            ->and($call->user_id)->toBe($users['manager']->id)
            ->and($call->subject_id)->toBe($task->id)
            ->and(ProjectActivity::where('metadata->ai_tool_call_id', $call->id)->where('user_id', $users['manager']->id)->exists())->toBeTrue();
    });

    test('create_task stores the confirming user as created_by', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        aiProject($workspace, $users['owner']);

        fakeAi([
            ['tool' => 'create_task', 'args' => ['project' => 'Website Redesign', 'title' => 'Checkout story', 'priority' => 'high']],
            ['text' => 'Card shown.'],
        ]);
        $cardId = $this->actingAs($users['manager'])->postJson(route('ai-assistant.send'), ['content' => 'Create a story'])->json('messages.1.cards.0.id');
        expect(Task::where('title', 'Checkout story')->exists())->toBeFalse();

        $this->actingAs($users['manager'])->postJson(route('ai-assistant.tool-calls.confirm', $cardId))->assertJsonPath('card.status', 'done');

        $task = Task::where('title', 'Checkout story')->first();
        expect($task->created_by)->toBe($users['manager']->id)->and($task->priority)->toBe('high');
    });

    test('assign_bug and change_task_status run through their actions', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $project = aiProject($workspace, $users['owner']);
        $task = aiTask($project, $users['owner'], 'API docs');
        $bug = Bug::create(['project_id' => $project->id, 'bug_status_id' => BugStatus::where('workspace_id', $workspace->id)->value('id'),
            'title' => 'Login button does nothing', 'priority' => 'high', 'severity' => 'major', 'reported_by' => $users['owner']->id]);

        fakeAi([
            ['tool' => 'change_task_status', 'args' => ['task' => 'API docs', 'stage' => 'Done']],
            ['tool' => 'assign_bug', 'args' => ['bug' => 'login button', 'assignee' => 'me']],
            ['text' => 'Two cards.'],
        ]);
        $cards = $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'do both'])->json('messages.1.cards');
        foreach ($cards as $card) {
            $this->actingAs($users['owner'])->postJson(route('ai-assistant.tool-calls.confirm', $card['id']))->assertJsonPath('card.status', 'done');
        }

        expect($task->fresh()->taskStage->name)->toBe('Done')
            ->and($bug->fresh()->assigned_to)->toBe($users['owner']->id);
    });

    test('an ambiguous name makes the assistant ask instead of guessing', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        foreach (['Ravi Kumar', 'Ravi Shankar'] as $name) {
            $person = User::factory()->create(['name' => $name]);
            WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $person->id, 'role' => 'member', 'status' => 'active']);
        }
        aiTask(aiProject($workspace, $users['owner']), $users['owner'], 'Login bug fix');

        $fake = fakeAi([['tool' => 'assign_task', 'args' => ['task' => 'Login bug fix', 'assignee' => 'Ravi']], ['text' => 'Which Ravi?']]);
        $response = $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'assign to Ravi'])->assertOk();

        expect($fake->toolResults[0]['result'])->toContain('Ravi Kumar')->toContain('Ravi Shankar')->toContain('do not guess')
            ->and($response->json('messages.1.cards'))->toBeEmpty();
    });

    test('records in another workspace are invisible to the tools', function () {
        [$workspace, $users] = aiWorkspace();
        [$otherWorkspace, $otherUsers] = aiWorkspace();
        aiSettings($workspace);
        aiTask(aiProject($otherWorkspace, $otherUsers['owner'], 'Secret Project'), $otherUsers['owner'], 'Secret task');

        $fake = fakeAi([['tool' => 'list_tasks', 'args' => ['search' => 'Secret']], ['tool' => 'assign_task', 'args' => ['task' => 'Secret task', 'assignee' => 'me']], ['text' => 'nothing']]);
        $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'secret?'])->assertOk();

        expect($fake->toolResults[0]['result'])->not->toContain('Secret task')
            ->and($fake->toolResults[1]['result'])->toContain('No task matches')
            ->and(AiToolCall::withoutGlobalScope('workspace')->where('status', 'pending')->count())->toBe(0);
    });

    test('only the user who got the card can confirm it, and only once', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        aiTask(aiProject($workspace, $users['owner']), $users['owner'], 'API docs');
        fakeAi([['tool' => 'change_task_status', 'args' => ['task' => 'API docs', 'stage' => 'Done']], ['text' => 'ok']]);
        $cardId = $this->actingAs($users['manager'])->postJson(route('ai-assistant.send'), ['content' => 'done'])->json('messages.1.cards.0.id');

        $this->actingAs($users['owner'])->postJson(route('ai-assistant.tool-calls.confirm', $cardId))->assertForbidden();
        $this->actingAs($users['manager'])->postJson(route('ai-assistant.tool-calls.confirm', $cardId))->assertOk();
        $this->actingAs($users['manager'])->postJson(route('ai-assistant.tool-calls.confirm', $cardId))->assertStatus(422);
    });

    test('cancel leaves the record unchanged; expired cards cannot be confirmed', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $task = aiTask(aiProject($workspace, $users['owner']), $users['owner'], 'API docs');
        fakeAi([
            ['tool' => 'change_task_status', 'args' => ['task' => 'API docs', 'stage' => 'Done']],
            ['tool' => 'assign_task', 'args' => ['task' => 'API docs', 'assignee' => 'me']],
            ['text' => 'ok'],
        ]);
        [$first, $second] = $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'x'])->json('messages.1.cards');

        $this->actingAs($users['owner'])->postJson(route('ai-assistant.tool-calls.cancel', $first['id']))->assertJsonPath('card.status', 'cancelled');

        AiToolCall::withoutGlobalScope('workspace')->whereKey($second['id'])->update(['created_at' => now()->subHour()]);
        $this->actingAs($users['owner'])->postJson(route('ai-assistant.tool-calls.confirm', $second['id']))->assertStatus(422);

        expect($task->fresh()->taskStage->name)->toBe('To Do')->and($task->fresh()->assigned_to)->toBeNull();
    });

    test('a card cannot be confirmed after the owner switches managers off', function () {
        [$workspace, $users] = aiWorkspace();
        $settings = aiSettings($workspace);
        $task = aiTask(aiProject($workspace, $users['owner']), $users['owner'], 'API docs');
        fakeAi([['tool' => 'change_task_status', 'args' => ['task' => 'API docs', 'stage' => 'Done']], ['text' => 'ok']]);
        $cardId = $this->actingAs($users['manager'])->postJson(route('ai-assistant.send'), ['content' => 'x'])->json('messages.1.cards.0.id');

        $settings->update(['managers_enabled' => false]);

        $this->actingAs($users['manager'])->postJson(route('ai-assistant.tool-calls.confirm', $cardId))->assertForbidden();
        expect($task->fresh()->taskStage->name)->toBe('To Do');
    });
});

// ── Retention ─────────────────────────────────────────────────────────────────

describe('retention', function () {
    test('old chats are deleted, the audit rows stay without their prompt text', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace, ['retention_days' => 30]);
        $old = AiConversation::withoutGlobalScope('workspace')->create(['workspace_id' => $workspace->id, 'user_id' => $users['owner']->id, 'last_message_at' => now()->subDays(31)]);
        $recent = AiConversation::withoutGlobalScope('workspace')->create(['workspace_id' => $workspace->id, 'user_id' => $users['owner']->id, 'last_message_at' => now()->subDays(5)]);
        $audit = AiToolCall::withoutGlobalScope('workspace')->create([
            'workspace_id' => $workspace->id, 'user_id' => $users['owner']->id, 'ai_conversation_id' => $old->id,
            'prompt' => 'assign the bug', 'tool' => 'assign_bug', 'status' => 'done',
        ]);

        $this->artisan('ai:prune-conversations')->assertSuccessful();

        expect(AiConversation::withoutGlobalScope('workspace')->find($old->id))->toBeNull()
            ->and(AiConversation::withoutGlobalScope('workspace')->find($recent->id))->not->toBeNull()
            ->and($audit->fresh()->prompt)->toBeNull()
            ->and($audit->fresh()->ai_conversation_id)->toBeNull()
            ->and($audit->fresh()->tool)->toBe('assign_bug');
    });
});

// ── Controller refactor (shared Action classes) ──────────────────────────────

describe('task screens still work through the shared actions', function () {
    test('a new workspace counts its Done stage as completed', function () {
        [$workspace] = aiWorkspace();

        expect(TaskStage::where('workspace_id', $workspace->id)->where('is_completed', true)->pluck('name')->all())->toBe(['Done']);
    });

    test('creating a task from the normal screen sets created_by and the first stage', function () {
        [$workspace, $users] = aiWorkspace();
        $project = aiProject($workspace, $users['owner']);

        $this->actingAs($users['manager'])->post(route('tasks.store'), [
            'project_id' => $project->id, 'title' => 'From the form', 'priority' => 'low',
        ])->assertSessionHasNoErrors();

        $task = Task::where('title', 'From the form')->first();
        expect($task->created_by)->toBe($users['manager']->id)->and($task->taskStage->name)->toBe('To Do');
    });

    test('changing a stage from the board still works', function () {
        [$workspace, $users] = aiWorkspace();
        $task = aiTask(aiProject($workspace, $users['owner']), $users['owner'], 'Board card');
        $done = TaskStage::where('workspace_id', $workspace->id)->where('name', 'Done')->first();

        $this->actingAs($users['owner'])->put(route('tasks.change-stage', $task), ['task_stage_id' => $done->id])->assertSessionHasNoErrors();

        expect($task->fresh()->task_stage_id)->toBe($done->id);
    });
});
