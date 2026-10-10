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

/** Signed in as someone who just opened the AI Assistant: AI session started. */
function asAi($test, User $user)
{
    return $test->actingAs($user)->withSession([
        'ai_mode' => [
            'workspace_id' => (int) $user->current_workspace_id,
            'last_activity' => now()->getTimestamp(),
            'started_at' => now()->getTimestamp(),
            'key' => 'test-' . $user->id,
        ],
    ]);
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

        asAi($this, $users[$role])->get(route('ai-assistant.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('ai-assistant/index')
                ->where('access', 'allowed')
                ->where('isOwner', $role === 'owner'));
    })->with(['owner', 'manager']);

    test('members and clients get a 403 on every AI Assistant route', function (string $role) {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $user = $users[$role];

        asAi($this, $user)->get(route('ai-assistant.index'))->assertForbidden();
        asAi($this, $user)->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertForbidden();
        asAi($this, $user)->putJson(route('ai-assistant.settings.update'), [])->assertForbidden();
    })->with(['member', 'client']);

    test('the shared aiAssistant prop is null for members and clients', function () {
        [$workspace, $users] = aiWorkspace();

        $this->actingAs($users['member'])->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.aiAssistant', null));
    });

    test('without the AI add-on the owner sees the upgrade page and the manager is sent to the dashboard', function () {
        [$workspace, $users] = aiWorkspace(withAddon: false);
        aiSettings($workspace);

        asAi($this, $users['owner'])->get(route('ai-assistant.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('access', 'plan')->where('settings', null)->where('conversations', []));
        asAi($this, $users['manager'])->get(route('ai-assistant.index'))->assertRedirect(route('dashboard'));
        asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertStatus(402);
    });

    test('a manager cannot chat when the owner turned managers off', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace, ['managers_enabled' => false]);
        $fake = fakeAi([['text' => 'hello']]);

        asAi($this, $users['manager'])->get(route('ai-assistant.index'))
            ->assertInertia(fn (Assert $page) => $page->where('access', 'managers_off'));
        asAi($this, $users['manager'])->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertForbidden();
        expect($fake->requests)->toBeEmpty();
    });
});

// ── BYOA settings ─────────────────────────────────────────────────────────────

describe('settings', function () {
    test('owner saves a key; it is encrypted at rest and only the last 4 characters are shown', function () {
        [$workspace, $users] = aiWorkspace();

        asAi($this, $users['owner'])->put(route('ai-assistant.settings.update'), [
            'provider' => 'anthropic', 'model' => 'claude-sonnet-5-5', 'api_key' => 'sk-ant-secret-key-ABCD',
            'managers_enabled' => true, 'retention_days' => 90,
        ])->assertSessionHasNoErrors();

        $raw = DB::table('ai_provider_settings')->where('workspace_id', $workspace->id)->value('api_key');
        expect($raw)->not->toContain('sk-ant-secret-key')
            ->and(AiProviderSetting::withoutGlobalScope('workspace')->first()->api_key)->toBe('sk-ant-secret-key-ABCD');

        asAi($this, $users['owner'])->get(route('ai-assistant.index'))
            ->assertInertia(fn (Assert $page) => $page->where('settings.masked_key', '••••ABCD')->missing('settings.api_key'));
    });

    test('managers cannot change or test the settings', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);

        asAi($this, $users['manager'])->putJson(route('ai-assistant.settings.update'), ['provider' => 'openai'])->assertForbidden();
        asAi($this, $users['manager'])->postJson(route('ai-assistant.settings.test'), ['provider' => 'openai'])->assertForbidden();
    });

    test('only Azure OpenAI hosts are accepted as an endpoint', function (string $endpoint, bool $ok) {
        [$workspace, $users] = aiWorkspace();

        $response = asAi($this, $users['owner'])->putJson(route('ai-assistant.settings.update'), [
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

        asAi($this, $users['owner'])->putJson(route('ai-assistant.settings.update'), [
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

        asAi($this, $users['owner'])->putJson(route('ai-assistant.settings.update'), [
            'provider' => 'ollama', 'model' => 'llama3', 'api_key' => 'whatever-1234',
        ])->assertJsonValidationErrors('provider');
    });

    test('test connection passes only when the model calls the test tool', function () {
        [$workspace, $users] = aiWorkspace();
        $payload = ['provider' => 'anthropic', 'model' => 'claude-sonnet-5-5', 'api_key' => 'sk-ant-test-12345678'];

        fakeAi([['tool' => 'confirm_connection', 'args' => ['status' => 'ok']], ['text' => 'done']]);
        asAi($this, $users['owner'])->postJson(route('ai-assistant.settings.test'), $payload)->assertJsonPath('passed', true);

        fakeAi([['text' => 'I cannot use tools']]);
        asAi($this, $users['owner'])->postJson(route('ai-assistant.settings.test'), $payload)->assertJsonPath('passed', false);
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

        $response = asAi($this, $users['manager'])->postJson(route('ai-assistant.send'), ['content' => 'What is overdue?'])->assertOk();

        expect($response->json('messages.1.content'))->toBe('One overdue task: Fix header.')
            ->and($fake->toolResults[0]['result'])->not->toContain('Write docs')
            ->and(AiToolCall::withoutGlobalScope('workspace')->where('tool', 'list_tasks')->value('status'))->toBe('done')
            ->and(AiUsage::withoutGlobalScope('workspace')->count())->toBe(1);
    });

    test('the owner gets every tool', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $fake = fakeAi([['text' => 'ok']]);

        asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertOk();

        expect(toolNames($fake))->toBe(collect(app(\App\Services\Ai\ToolRegistry::class)->all())->keys()->sort()->values()->all())
            ->and(toolNames($fake))->toHaveCount(44);
    });

    test('a manager gets the manager workflow tools their role allows', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $fake = fakeAi([['text' => 'ok']]);

        asAi($this, $users['manager'])->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertOk();

        expect(toolNames($fake))->toContain(
            'list_tasks', 'create_task', 'assign_task', 'change_task_status', 'assign_bug', 'create_bug', 'change_bug_status',
            'list_sprints', 'add_tasks_to_sprint', 'list_timesheet_approvals', 'decide_timesheets',
            'list_expense_approvals', 'decide_expenses', 'get_budget_status', 'get_project_report',
        );
        // Every offered tool really is allowed by the manager's permissions.
        foreach ($fake->requests[0]->tools as $spec) {
            expect(app(\App\Services\Ai\ToolRegistry::class)->find($spec->name)->allowedFor($users['manager']))->toBeTrue();
        }
    });

    test('a read-only model gets no write tools', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace, ['model' => 'claude-haiku-4-5-20251001']);
        $fake = fakeAi([['text' => 'ok']]);

        asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertOk();

        $registry = app(\App\Services\Ai\ToolRegistry::class);
        expect(toolNames($fake))->toContain('list_projects', 'get_project_report', 'search_knowledge_base')
            ->and(collect(toolNames($fake))->filter(fn ($name) => $registry->find($name)->isWrite()))->toBeEmpty();
    });

    test('nothing works before a provider is connected', function () {
        [$workspace, $users] = aiWorkspace();

        asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'hi'])
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

        asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertStatus(502);
        expect($fake->requests)->toBeEmpty();
    });

    test('users only see their own conversations', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $conversation = AiConversation::withoutGlobalScope('workspace')->create(['workspace_id' => $workspace->id, 'user_id' => $users['manager']->id, 'title' => 'Mine']);

        asAi($this, $users['owner'])->getJson(route('ai-assistant.conversations.show', $conversation))->assertNotFound();
        asAi($this, $users['manager'])->getJson(route('ai-assistant.conversations.show', $conversation))->assertOk();
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
        $response = asAi($this, $users['manager'])->postJson(route('ai-assistant.send'), ['content' => 'Assign the login bug fix to Ravi, due Friday'])->assertOk();

        $card = $response->json('messages.1.cards.0');
        expect($card['status'])->toBe('pending')
            ->and($card['details'])->toHaveKey('New assignee', 'Ravi Kumar')
            ->and($task->fresh()->assigned_to)->toBeNull();

        asAi($this, $users['manager'])->postJson(route('ai-assistant.tool-calls.confirm', $card['id']))
            ->assertOk()
            ->assertJsonPath('card.status', 'done');

        $call = AiToolCall::withoutGlobalScope('workspace')->find($card['id']);
        expect($task->fresh()->assigned_to)->toBe($ravi->id)
            ->and($task->fresh()->end_date->format('Y-m-d'))->toBe('2026-10-09')
            ->and($call->user_id)->toBe($users['manager']->id)
            ->and($call->subject_id)->toBe($task->id)
            ->and(ProjectActivity::where('metadata->ai_tool_call_id', $call->id)->where('user_id', $users['manager']->id)->exists())->toBeTrue();
    });

    test('only the must-know value is asked; defaults fill the rest; a clicked choice needs no AI call', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        aiProject($workspace, $users['owner']);
        $mobile = aiProject($workspace, $users['owner'], 'Mobile App');

        // The model "helpfully" invents a project and a priority the user never gave.
        $fake = fakeAi([
            ['tool' => 'create_task', 'args' => ['project' => 'Website Redesign', 'title' => 'Login page', 'priority' => 'high']],
            ['text' => 'Which project should it go in? It will be medium priority and unassigned.'],
        ]);
        $card = asAi($this, $users['manager'])->postJson(route('ai-assistant.send'), ['content' => 'create a to do task for a login page'])
            ->json('messages.1.cards.0');
        $fields = collect($card['fields'])->keyBy('name');

        // One question, for the project only; the invented values were dropped for the defaults.
        expect($card['stage'])->toBe('question')
            ->and($card['ask'])->toBe(['project'])
            ->and($card['question'])->toBe('Which project should it go in?')
            ->and($fields['title']['value'])->toBe('Login page')
            ->and($fields['priority']['value'])->toBe('medium')
            ->and($fields['priority']['defaulted'])->toBeTrue()
            ->and($fields['assignee']['value'])->toBe('none')
            ->and(collect($fields['project']['options'])->pluck('label')->all())->toContain('Website Redesign', 'Mobile App')
            ->and($fake->toolResults[0]['result'])->toContain('Still needed from the user: Project')
            ->and($fake->toolResults[0]['result'])->toContain('Priority: Medium');

        // Clicking the "Mobile App" button: the draft is ready to confirm, no AI call.
        $ready = asAi($this, $users['manager'])->postJson(route('ai-assistant.tool-calls.update', $card['id']), ['fields' => ['project' => (string) $mobile->id]])
            ->assertJsonPath('card.stage', 'review')
            ->assertJsonPath('card.ask', [])
            ->json('card');
        expect($ready['summary'])->toBe('Create task "Login page" in Mobile App')
            ->and(Task::where('title', 'Login page')->exists())->toBeFalse();

        aiConfirm($this, $users['manager'], $card['id'])->assertJsonPath('card.status', 'done');

        $task = Task::where('title', 'Login page')->first();
        expect($fake->requests)->toHaveCount(1)
            ->and($task->created_by)->toBe($users['manager']->id)
            ->and($task->project_id)->toBe($mobile->id)
            ->and($task->priority)->toBe('medium')
            ->and($task->assigned_to)->toBeNull();
    });

    test('a typed short answer completes the draft with no AI call, and can change a default', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        aiProject($workspace, $users['owner']);
        $mobile = aiProject($workspace, $users['owner'], 'Mobile App');

        $fake = fakeAi([['tool' => 'create_task', 'args' => ['title' => 'Login page']], ['text' => 'Which project should it go in?']]);
        $first = asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'create a task for a login page']);
        [$cardId, $chat] = [$first->json('messages.1.cards.0.id'), $first->json('conversation.id')];

        $reply = asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['conversation_id' => $chat, 'content' => 'mobile app, make it high'])
            ->assertOk()
            ->json('messages.1');
        $fields = collect($reply['cards'][0]['fields'])->keyBy('name');

        expect($fake->requests)->toHaveCount(1)
            ->and($reply['content'])->toBe('Please check the card and confirm.')
            ->and($reply['cards'][0]['id'])->toBe($cardId)
            ->and($reply['cards'][0]['stage'])->toBe('review')
            ->and($fields['project']['value'])->toBe((string) $mobile->id)
            ->and($fields['priority']['value'])->toBe('high');

        // On the confirmation stage, a short change ("assign it to me") is applied the same way.
        asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['conversation_id' => $chat, 'content' => 'assign it to me'])->assertOk();
        expect($fake->requests)->toHaveCount(1)
            ->and(AiToolCall::withoutGlobalScope('workspace')->find($cardId)->payload['form']['fields'][3]['value'])->toBe((string) $users['owner']->id);
    });

    test('a reply that is a new question goes to the AI, and a repeated tool call updates the same draft', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        aiProject($workspace, $users['owner']);
        $mobile = aiProject($workspace, $users['owner'], 'Mobile App');

        fakeAi([['tool' => 'create_task', 'args' => ['title' => 'Login page']], ['text' => 'Which project?']]);
        $first = asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'create a task for a login page']);
        [$cardId, $chat] = [$first->json('messages.1.cards.0.id'), $first->json('conversation.id')];

        // Not a plain answer: the AI handles it, calls the tool again, and the draft is updated in place.
        $fake = fakeAi([['tool' => 'create_task', 'args' => ['project' => 'Mobile App']], ['text' => 'Done, please confirm.']]);
        $reply = asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['conversation_id' => $chat, 'content' => 'put it under the project for our mobile app please, the one we started last week'])
            ->json('messages.1');

        expect($fake->requests)->toHaveCount(1)
            ->and($reply['cards'])->toHaveCount(1)
            ->and($reply['cards'][0]['id'])->toBe($cardId)
            ->and($reply['cards'][0]['stage'])->toBe('review')
            ->and(AiToolCall::withoutGlobalScope('workspace')->where('tool', 'create_task')->count())->toBe(1)
            ->and(collect($reply['cards'][0]['fields'])->firstWhere('name', 'project')['value'])->toBe((string) $mobile->id);
    });

    test('values the user did say are filled in, and the only project is picked for them', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        aiProject($workspace, $users['owner']);

        fakeAi([['tool' => 'create_task', 'args' => ['title' => 'Checkout story', 'priority' => 'high', 'assignee' => 'me']], ['text' => 'ok']]);
        $card = asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'add a high priority task Checkout story for me'])
            ->json('messages.1.cards.0');
        $fields = collect($card['fields'])->keyBy('name');

        expect($fields['project']['value'])->not->toBeNull()
            ->and($fields['priority']['value'])->toBe('high')
            ->and($fields['assignee']['value'])->toBe((string) $users['owner']->id)
            ->and($card['summary'])->toBe('Create task "Checkout story" in Website Redesign');

        aiConfirm($this, $users['owner'], $card['id'])->assertJsonPath('card.status', 'done');
        expect(Task::where('title', 'Checkout story')->value('assigned_to'))->toBe($users['owner']->id);
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
        $cards = asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'move API docs to Done and assign the login button bug to me'])->json('messages.1.cards');
        foreach ($cards as $card) {
            asAi($this, $users['owner'])->postJson(route('ai-assistant.tool-calls.confirm', $card['id']))->assertJsonPath('card.status', 'done');
        }

        expect($task->fresh()->taskStage->name)->toBe('Done')
            ->and($bug->fresh()->assigned_to)->toBe($users['owner']->id);
    });

    test('an ambiguous name is never guessed: the card lists the matches first, in one AI call', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        foreach (['Ravi Kumar', 'Ravi Shankar'] as $name) {
            $person = User::factory()->create(['name' => $name]);
            WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $person->id, 'role' => 'member', 'status' => 'active']);
        }
        aiTask(aiProject($workspace, $users['owner']), $users['owner'], 'Login bug fix');

        $fake = fakeAi([['tool' => 'assign_task', 'args' => ['task' => 'Login bug fix', 'assignee' => 'Ravi']], ['text' => 'Pick the person on the card.']]);
        $response = asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'assign the login bug fix to Ravi'])->assertOk();
        $fields = collect($response->json('messages.1.cards.0.fields'))->keyBy('name');

        expect($fake->requests)->toHaveCount(1)
            ->and($fields['task']['value'])->not->toBeNull()
            ->and($fields['assignee']['value'])->toBeNull()
            ->and($fields['assignee']['note'])->toContain('Several match')
            ->and(collect($fields['assignee']['options'])->take(2)->pluck('label')->implode(' '))->toContain('Ravi Kumar')->toContain('Ravi Shankar')
            ->and($response->json('messages.1.cards.0.ask'))->toBe(['assignee'])
            ->and($fake->toolResults[0]['result'])->toContain('Still needed from the user: Assignee');
    });

    test('records in another workspace are invisible to the tools and never offered on a card', function () {
        [$workspace, $users] = aiWorkspace();
        [$otherWorkspace, $otherUsers] = aiWorkspace();
        aiSettings($workspace);
        aiTask(aiProject($otherWorkspace, $otherUsers['owner'], 'Secret Project'), $otherUsers['owner'], 'Secret task');

        $fake = fakeAi([['tool' => 'list_tasks', 'args' => ['search' => 'Secret']], ['tool' => 'assign_task', 'args' => ['task' => 'Secret task', 'assignee' => 'me']], ['text' => 'nothing']]);
        $card = asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'assign the secret task to me'])->json('messages.1.cards.0');
        $task = collect($card['fields'])->firstWhere('name', 'task');

        expect($fake->toolResults[0]['result'])->not->toContain('Secret task')
            ->and($task['value'])->toBeNull()
            ->and($task['note'])->toContain('Nothing matches')
            ->and(json_encode($task['options']))->not->toContain('Secret');

        // Even a hand-made request with the other workspace's id is refused.
        $secretId = Task::where('title', 'Secret task')->value('id');
        aiConfirm($this, $users['owner'], $card['id'])->assertJsonPath('card.status', 'pending');
        asAi($this, $users['owner'])->postJson(route('ai-assistant.tool-calls.confirm', $card['id']), ['fields' => ['task' => (string) $secretId]])
            ->assertJsonPath('card.status', 'pending')
            ->assertJsonPath('card.fields.0.error', 'That task is no longer available; choose again.');
        expect(Task::find($secretId)->assigned_to)->toBeNull();
    });

    test('only the user who got the card can confirm it, and only once', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        aiTask(aiProject($workspace, $users['owner']), $users['owner'], 'API docs');
        fakeAi([['tool' => 'change_task_status', 'args' => ['task' => 'API docs', 'stage' => 'Done']], ['text' => 'ok']]);
        $cardId = asAi($this, $users['manager'])->postJson(route('ai-assistant.send'), ['content' => 'move API docs to done'])->json('messages.1.cards.0.id');

        asAi($this, $users['owner'])->postJson(route('ai-assistant.tool-calls.confirm', $cardId))->assertForbidden();
        asAi($this, $users['manager'])->postJson(route('ai-assistant.tool-calls.confirm', $cardId))->assertOk();
        asAi($this, $users['manager'])->postJson(route('ai-assistant.tool-calls.confirm', $cardId))->assertStatus(422);
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
        [$first, $second] = asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'x'])->json('messages.1.cards');

        asAi($this, $users['owner'])->postJson(route('ai-assistant.tool-calls.cancel', $first['id']))->assertJsonPath('card.status', 'cancelled');

        AiToolCall::withoutGlobalScope('workspace')->whereKey($second['id'])->update(['created_at' => now()->subHour()]);
        asAi($this, $users['owner'])->postJson(route('ai-assistant.tool-calls.confirm', $second['id']))->assertStatus(422);

        expect($task->fresh()->taskStage->name)->toBe('To Do')->and($task->fresh()->assigned_to)->toBeNull();
    });

    test('a card cannot be confirmed after the owner switches managers off', function () {
        [$workspace, $users] = aiWorkspace();
        $settings = aiSettings($workspace);
        $task = aiTask(aiProject($workspace, $users['owner']), $users['owner'], 'API docs');
        fakeAi([['tool' => 'change_task_status', 'args' => ['task' => 'API docs', 'stage' => 'Done']], ['text' => 'ok']]);
        $cardId = asAi($this, $users['manager'])->postJson(route('ai-assistant.send'), ['content' => 'x'])->json('messages.1.cards.0.id');

        $settings->update(['managers_enabled' => false]);

        asAi($this, $users['manager'])->postJson(route('ai-assistant.tool-calls.confirm', $cardId))->assertForbidden();
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

// ── Phase 2 and 3 tools ───────────────────────────────────────────────────────

/** Ask the fake model to call one tool; return the resulting card and the tool's reply. */
function aiCall($test, User $user, string $tool, array $args): array
{
    $fake = fakeAi([['tool' => $tool, 'args' => $args], ['text' => 'ok']]);
    // The user's message names every value, so the form's "did they say it" check keeps them.
    $said = 'go ' . implode(' ', array_map(fn ($v) => is_array($v) ? implode(' ', $v) : (string) $v, $args));
    $response = asAi($test, $user)->postJson(route('ai-assistant.send'), ['content' => $said])->assertOk();

    return ['card' => $response->json('messages.1.cards.0'), 'result' => $fake->toolResults[0]['result'] ?? null];
}

/** The JSON a read tool handed to the model. */
function aiData(array $call): array
{
    return json_decode(\Illuminate\Support\Str::after($call['result'], "\n"), true);
}

function aiConfirm($test, User $user, int $cardId, ?string $phrase = null)
{
    return asAi($test, $user)->postJson(route('ai-assistant.tool-calls.confirm', $cardId), array_filter(['phrase' => $phrase]));
}

function aiTimesheet(Workspace $workspace, Project $project, User $person, User $approver): \App\Models\TimesheetApproval
{
    $timesheet = \App\Models\Timesheet::create(['user_id' => $person->id, 'workspace_id' => $workspace->id, 'start_date' => now()->startOfWeek(),
        'end_date' => now()->endOfWeek(), 'status' => 'submitted', 'total_hours' => 8, 'billable_hours' => 8]);
    \App\Models\TimesheetEntry::create(['timesheet_id' => $timesheet->id, 'project_id' => $project->id, 'user_id' => $person->id,
        'date' => now()->startOfWeek()->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00', 'hours' => 8, 'is_billable' => true, 'hourly_rate' => 0]);

    return \App\Models\TimesheetApproval::create(['timesheet_id' => $timesheet->id, 'approver_id' => $approver->id, 'status' => 'pending']);
}

function aiExpense(Project $project, User $submitter, string $title = 'Taxi', float $amount = 1200): \App\Models\ProjectExpense
{
    return \App\Models\ProjectExpense::create(['project_id' => $project->id, 'submitted_by' => $submitter->id, 'amount' => $amount,
        'currency' => 'INR', 'expense_date' => now()->toDateString(), 'title' => $title, 'status' => 'pending']);
}

function aiInvoice(Workspace $workspace, Project $project, User $creator, array $attrs = []): \App\Models\Invoice
{
    return \App\Models\Invoice::withoutGlobalScope('workspace')->create(array_merge(['project_id' => $project->id, 'workspace_id' => $workspace->id,
        'created_by' => $creator->id, 'title' => 'Website work', 'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(),
        'subtotal' => 1000, 'tax_rate' => '[]', 'total_amount' => 1000, 'paid_amount' => 0, 'status' => 'draft'], $attrs));
}

describe('bugs', function () {
    test('create_bug stores the confirming user as reporter', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        aiProject($workspace, $users['owner']);

        $card = aiCall($this, $users['manager'], 'create_bug', ['project' => 'Website Redesign', 'title' => 'Login button does nothing', 'severity' => 'critical'])['card'];
        asAi($this, $users['manager'])->postJson(route('ai-assistant.tool-calls.confirm', $card['id']), ['fields' => ['priority' => 'high', 'assignee' => 'none']])
            ->assertJsonPath('card.status', 'done');

        $bug = Bug::where('title', 'Login button does nothing')->first();
        expect($bug->reported_by)->toBe($users['manager']->id)->and($bug->severity)->toBe('critical')->and($bug->bugStatus->name)->toBe('New');
    });

    test('change_bug_status can be undone', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $project = aiProject($workspace, $users['owner']);
        $bug = Bug::create(['project_id' => $project->id, 'bug_status_id' => BugStatus::where('workspace_id', $workspace->id)->where('name', 'New')->value('id'),
            'title' => 'Crash on save', 'priority' => 'high', 'severity' => 'major', 'reported_by' => $users['owner']->id]);

        $card = aiCall($this, $users['owner'], 'change_bug_status', ['bug' => 'Crash on save', 'status' => 'Resolved'])['card'];
        aiConfirm($this, $users['owner'], $card['id'])->assertJsonPath('card.can_undo', true);
        expect($bug->fresh()->bugStatus->name)->toBe('Resolved')->and($bug->fresh()->resolved_by)->toBe($users['owner']->id);

        asAi($this, $users['owner'])->postJson(route('ai-assistant.tool-calls.undo', $card['id']))->assertJsonPath('card.status', 'undone');
        expect($bug->fresh()->bugStatus->name)->toBe('New');
    });
});

describe('undo', function () {
    test('undo restores the previous assignee, but not after another change or after 10 minutes', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $task = aiTask(aiProject($workspace, $users['owner']), $users['owner'], 'API docs', ['assigned_to' => $users['member']->id]);

        $card = aiCall($this, $users['owner'], 'assign_task', ['task' => 'API docs', 'assignee' => 'me'])['card'];
        aiConfirm($this, $users['owner'], $card['id']);
        asAi($this, $users['owner'])->postJson(route('ai-assistant.tool-calls.undo', $card['id']))->assertOk();
        expect($task->fresh()->assigned_to)->toBe($users['member']->id);

        $second = aiCall($this, $users['owner'], 'assign_task', ['task' => 'API docs', 'assignee' => 'me'])['card'];
        aiConfirm($this, $users['owner'], $second['id']);
        $task->update(['assigned_to' => $users['manager']->id]);
        asAi($this, $users['owner'])->postJson(route('ai-assistant.tool-calls.undo', $second['id']))->assertStatus(422);
        expect($task->fresh()->assigned_to)->toBe($users['manager']->id);

        $third = aiCall($this, $users['owner'], 'assign_task', ['task' => 'API docs', 'assignee' => 'me'])['card'];
        aiConfirm($this, $users['owner'], $third['id']);
        AiToolCall::withoutGlobalScope('workspace')->whereKey($third['id'])->update(['confirmed_at' => now()->subMinutes(11)]);
        asAi($this, $users['owner'])->postJson(route('ai-assistant.tool-calls.undo', $third['id']))->assertStatus(422);
    });
});

describe('approvals', function () {
    test('a manager approves a member timesheet on a project they manage, never their own', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $project = aiProject($workspace, $users['owner']);
        \App\Models\ProjectMember::create(['project_id' => $project->id, 'user_id' => $users['manager']->id, 'role' => 'manager', 'assigned_by' => $users['owner']->id]);
        $memberSheet = aiTimesheet($workspace, $project, $users['member'], $users['manager']);
        $ownSheet = aiTimesheet($workspace, $project, $users['manager'], $users['owner']);

        $card = aiCall($this, $users['manager'], 'decide_timesheets', ['decision' => 'approve', 'project' => 'Website Redesign'])['card'];
        expect($card['items'])->toHaveCount(1)->and($card['items'][0])->toContain($users['member']->name);

        aiConfirm($this, $users['manager'], $card['id'])->assertJsonPath('card.status', 'done');
        expect($memberSheet->fresh()->status)->toBe('approved')
            ->and($memberSheet->timesheet->fresh()->status)->toBe('approved')
            ->and($ownSheet->fresh()->status)->toBe('pending');
    });

    test('rejecting a timesheet needs a reason', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        aiTimesheet($workspace, aiProject($workspace, $users['owner']), $users['member'], $users['owner']);

        $call = aiCall($this, $users['owner'], 'decide_timesheets', ['decision' => 'reject', 'person' => $users['member']->email]);
        expect($call['card'])->toBeNull()->and($call['result'])->toContain('reason is required');
    });

    test('more than 10 records need the typed phrase', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $project = aiProject($workspace, $users['owner']);
        foreach (range(1, 11) as $i) {
            aiTimesheet($workspace, $project, $users['member'], $users['owner']);
        }

        $card = aiCall($this, $users['owner'], 'decide_timesheets', ['decision' => 'approve', 'person' => $users['member']->email])['card'];
        expect($card['items'])->toHaveCount(11)->and($card['confirm_phrase'])->toBe('APPROVE 11');

        aiConfirm($this, $users['owner'], $card['id'])->assertStatus(422);
        aiConfirm($this, $users['owner'], $card['id'], 'wrong')->assertStatus(422);
        aiConfirm($this, $users['owner'], $card['id'], 'approve 11')->assertJsonPath('card.status', 'done');
        expect(\App\Models\TimesheetApproval::where('status', 'approved')->count())->toBe(11);
    });

    test('expenses: approval records the approver, rejection needs a reason, own expenses are left out', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $project = aiProject($workspace, $users['owner']);
        \App\Models\ProjectMember::create(['project_id' => $project->id, 'user_id' => $users['manager']->id, 'role' => 'manager', 'assigned_by' => $users['owner']->id]);
        $taxi = aiExpense($project, $users['member']);
        $own = aiExpense($project, $users['manager'], 'Own hotel');

        $card = aiCall($this, $users['manager'], 'decide_expenses', ['decision' => 'approve', 'project' => 'Website Redesign'])['card'];
        expect($card['items'])->toHaveCount(1);
        aiConfirm($this, $users['manager'], $card['id'])->assertJsonPath('card.status', 'done');

        expect($taxi->fresh()->status)->toBe('approved')
            ->and(\App\Models\ExpenseApproval::where('project_expense_id', $taxi->id)->value('approver_id'))->toBe($users['manager']->id)
            ->and($own->fresh()->status)->toBe('pending');

        $noReason = aiCall($this, $users['owner'], 'decide_expenses', ['decision' => 'reject', 'expense_ids' => (string) $own->id]);
        expect($noReason['card'])->toBeNull()->and($noReason['result'])->toContain('reason is required');
    });
});

describe('sprints, budgets and reports', function () {
    test('tasks join an existing sprint with who added them; tasks of other projects are refused', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $project = aiProject($workspace, $users['owner']);
        $other = aiProject($workspace, $users['owner'], 'Other Project');
        $sprint = \App\Models\Sprint::withoutGlobalScope('workspace')->create(['project_id' => $project->id, 'workspace_id' => $workspace->id, 'name' => 'Sprint 7', 'status' => 'planning', 'created_by' => $users['owner']->id]);
        aiTask($project, $users['owner'], 'Checkout page');
        aiTask($other, $users['owner'], 'Elsewhere task');

        $card = aiCall($this, $users['manager'], 'add_tasks_to_sprint', ['sprint' => 'Sprint 7', 'tasks' => 'Checkout page'])['card'];
        aiConfirm($this, $users['manager'], $card['id'])->assertJsonPath('card.status', 'done');
        expect(DB::table('sprint_tasks')->where('sprint_id', $sprint->id)->value('created_by'))->toBe($users['manager']->id);

        $refused = aiCall($this, $users['manager'], 'add_tasks_to_sprint', ['sprint' => 'Sprint 7', 'tasks' => 'Elsewhere task']);
        expect($refused['card'])->toBeNull()->and($refused['result'])->toContain('No task matches');
    });

    test('budget status and project report read the real numbers', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $project = aiProject($workspace, $users['owner']);
        aiTask($project, $users['owner'], 'Late task', ['end_date' => now()->subDays(3)->toDateString()]);
        \App\Models\ProjectBudget::create(['project_id' => $project->id, 'workspace_id' => $workspace->id, 'total_budget' => 1000,
            'period_type' => 'project', 'start_date' => now()->subMonth()->toDateString(), 'status' => 'active', 'currency' => 'INR', 'created_by' => $users['owner']->id]);

        $budget = aiData(aiCall($this, $users['owner'], 'get_budget_status', []));
        expect($budget['budgets'][0]['project'])->toBe('Website Redesign')->and($budget['budgets'][0]['total_budget'])->toEqual(1000);

        $report = aiData(aiCall($this, $users['owner'], 'get_project_report', ['project' => 'Website Redesign']));
        expect($report['tasks']['total'])->toBe(1)->and($report['tasks']['overdue'][0]['title'])->toBe('Late task');
    });
});

describe('owner tools', function () {
    test('create_project creates it with members, by the confirming owner', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);

        $card = aiCall($this, $users['owner'], 'create_project', ['title' => 'Sundal', 'status' => 'active', 'priority' => 'high', 'members' => $users['member']->email])['card'];
        expect($card['details'])->toHaveKey('Members', $users['member']->name);
        aiConfirm($this, $users['owner'], $card['id'])->assertJsonPath('card.status', 'done');

        $project = Project::where('title', 'Sundal')->first();
        expect($project->created_by)->toBe($users['owner']->id)
            ->and($project->workspace_id)->toBe($workspace->id)
            ->and($project->members()->where('user_id', $users['member']->id)->exists())->toBeTrue();
    });

    test('a manager cannot staff a project they do not manage', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        aiProject($workspace, $users['owner']);

        $call = aiCall($this, $users['manager'], 'add_project_members', ['project' => 'Website Redesign', 'people' => $users['member']->email]);
        expect($call['card']['form_error'])->toContain('only staff projects you manage')
            ->and($call['result'])->toContain('only staff projects you manage');
        aiConfirm($this, $users['manager'], $call['card']['id'])->assertJsonPath('card.status', 'pending');
        expect(\App\Models\ProjectMember::where('user_id', $users['member']->id)->exists())->toBeFalse();
    });

    test('send_invoice needs the invoice number typed', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $invoice = aiInvoice($workspace, aiProject($workspace, $users['owner']), $users['owner']);

        $card = aiCall($this, $users['owner'], 'send_invoice', ['invoice' => $invoice->invoice_number])['card'];
        expect($card['confirm_phrase'])->toBe($invoice->invoice_number);

        aiConfirm($this, $users['owner'], $card['id'], 'nope')->assertJsonPath('card.status', 'pending')
            ->assertJsonPath('card.form_error', "Type \"{$invoice->invoice_number}\" to confirm.");
        expect($invoice->fresh()->status)->toBe('draft');
        aiConfirm($this, $users['owner'], $card['id'], $invoice->invoice_number)->assertJsonPath('card.status', 'done');
        expect($invoice->fresh()->status)->toBe('sent');
    });

    test('invite_user follows the role rules: managers invite members only', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);

        $role = collect(aiCall($this, $users['manager'], 'invite_user', ['email' => 'boss@acme.com', 'role' => 'manager'])['card']['fields'])->firstWhere('name', 'role');
        expect($role['value'])->toBeNull()->and(collect($role['options'])->pluck('value')->all())->toBe(['member']);

        // The Free plan's 2-user limit is already used: refused before any card.
        $full = aiCall($this, $users['owner'], 'invite_user', ['email' => 'john@acme.com', 'role' => 'client']);
        expect($full['card']['form_error'])->not->toBeEmpty()->and($full['card']['status'])->toBe('pending');

        Plan::where('is_default', true)->update(['max_users_per_workspace' => 10, 'max_clients_per_workspace' => 10]);
        $card = aiCall($this, $users['owner'], 'invite_user', ['email' => 'john@acme.com', 'role' => 'client'])['card'];
        aiConfirm($this, $users['owner'], $card['id'])->assertJsonPath('card.status', 'done');
        expect(\App\Models\WorkspaceInvitation::where('email', 'john@acme.com')->value('invited_by'))->toBe($users['owner']->id);
    });

    test('revenue summary adds billed and collected for the period', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $project = aiProject($workspace, $users['owner']);
        $sent = aiInvoice($workspace, $project, $users['owner'], ['status' => 'partial_paid', 'invoice_date' => '2026-09-10', 'total_amount' => 1000, 'paid_amount' => 400]);
        aiInvoice($workspace, $project, $users['owner'], ['status' => 'draft', 'invoice_date' => '2026-09-12', 'total_amount' => 999]);
        \App\Models\Payment::create(['invoice_id' => $sent->id, 'amount' => 400, 'payment_method' => 'bank', 'payment_date' => '2026-09-20', 'created_by' => $users['owner']->id]);

        $data = aiData(aiCall($this, $users['owner'], 'get_revenue_summary', ['from' => '2026-09-01', 'to' => '2026-09-30']));
        expect($data['billed'])->toEqual(1000)->and($data['collected'])->toEqual(400)->and($data['outstanding_now'])->toEqual(600);
    });

    test('knowledge base search returns matching published articles only', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        \App\Models\KbArticle::withoutGlobalScope('workspace')->create(['workspace_id' => $workspace->id, 'kb_category_id' => 1, 'title' => 'How to submit a timesheet', 'content' => 'Open Timesheets and press Submit.', 'is_published' => true, 'created_by' => $users['owner']->id]);
        \App\Models\KbArticle::withoutGlobalScope('workspace')->create(['workspace_id' => $workspace->id, 'kb_category_id' => 1, 'title' => 'Draft timesheet notes', 'content' => 'Unpublished', 'is_published' => false, 'created_by' => $users['owner']->id]);

        $data = aiData(aiCall($this, $users['manager'], 'search_knowledge_base', ['query' => 'submit timesheet']));
        expect($data['found'])->toBe(1)->and($data['articles'][0]['title'])->toBe('How to submit a timesheet');
    });

    test('list_contracts shows contracts expiring soon', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        \App\Models\Contract::withoutGlobalScope('workspace')->create(['subject' => 'Acme support', 'contract_type_id' => 1, 'contract_value' => 5000,
            'start_date' => now()->subYear()->toDateString(), 'end_date' => now()->addDays(10)->toDateString(), 'workspace_id' => $workspace->id,
            'created_by' => $users['owner']->id, 'currency' => 'USD']);

        $data = aiData(aiCall($this, $users['owner'], 'list_contracts', ['expiring_within_days' => 30]));
        expect($data['contracts'][0]['subject'])->toBe('Acme support');
    });
});

describe('history log and creators', function () {
    test('every change is in the history with who did it and whether it came from the AI', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $project = aiProject($workspace, $users['owner']);

        // From the normal screen
        $this->actingAs($users['manager'])->post(route('tasks.store'), ['project_id' => $project->id, 'title' => 'Story A', 'priority' => 'low']);
        $task = Task::where('title', 'Story A')->first();

        // From the assistant
        $card = aiCall($this, $users['manager'], 'assign_task', ['task' => 'Story A', 'assignee' => $users['member']->email])['card'];
        aiConfirm($this, $users['manager'], $card['id']);

        $log = \Spatie\Activitylog\Models\Activity::where('subject_type', $task->getMorphClass())->where('subject_id', $task->id)->orderBy('id')->get();
        expect($log->pluck('event')->all())->toBe(['created', 'updated'])
            ->and($log->every(fn ($a) => (int) $a->causer_id === $users['manager']->id))->toBeTrue()
            ->and($log[0]->properties['source'])->toBe('screen')
            ->and($log[1]->properties['source'])->toBe('ai_assistant')
            ->and($log[1]->properties['ai_tool_call_id'])->toBe($card['id'])
            ->and($log[1]->properties['attributes']['assigned_to'])->toBe($users['member']->id)
            ->and($log[1]->workspace_id)->toBe($workspace->id);

        $history = aiData(aiCall($this, $users['owner'], 'get_record_history', ['type' => 'task', 'record' => 'Story A']));
        expect($history['created_by'])->toBe($users['manager']->name)
            ->and($history['history'][0]['who'])->toBe($users['manager']->name)
            ->and($history['history'][0]['source'])->toBe('ai_assistant')
            ->and($history['history'][0]['changes'][0]['field'])->toBe('assigned_to');
    });

    test('a record created without a creator gets the signed-in user', function () {
        [$workspace, $users] = aiWorkspace();
        $this->actingAs($users['manager']);

        $note = \App\Models\Note::create(['workspace' => $workspace->id, 'title' => 'Minutes', 'text' => 'x', 'color' => '#ffffff']);
        expect($note->created_by)->toBe($users['manager']->id);

        $bug = Bug::create(['project_id' => aiProject($workspace, $users['owner'])->id, 'bug_status_id' => BugStatus::where('workspace_id', $workspace->id)->value('id'),
            'title' => 'No reporter given', 'priority' => 'low', 'severity' => 'minor']);
        expect($bug->reported_by)->toBe($users['manager']->id);
    });

    test('a change with no signed-in user is no longer credited to user 1 in the project feed', function () {
        [$workspace, $users] = aiWorkspace();
        $project = aiProject($workspace, $users['owner']);
        auth()->logout();

        aiTask($project, $users['owner'], 'Made by a job');

        expect(ProjectActivity::where('project_id', $project->id)->where('user_id', 1)->exists())->toBeFalse();
    });
});

describe('usage, errors, queue, writing helper and evaluation', function () {
    test('the owner sees 30 days of daily usage', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        AiUsage::withoutGlobalScope('workspace')->create(['workspace_id' => $workspace->id, 'user_id' => $users['owner']->id,
            'provider' => 'anthropic', 'model' => 'claude-sonnet-5-5', 'input_tokens' => 300, 'output_tokens' => 20]);

        asAi($this, $users['owner'])->get(route('ai-assistant.index'))
            ->assertInertia(fn (Assert $page) => $page->has('usage.daily', 30)->where('usage.daily.29.tokens', 320)->where('usage.tokens_this_month', 320));
    });

    test('crossing 80% of the cap triggers the owner warning once a month', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace, ['monthly_token_cap' => 1000]);
        AiUsage::withoutGlobalScope('workspace')->create(['workspace_id' => $workspace->id, 'user_id' => $users['owner']->id,
            'provider' => 'anthropic', 'model' => 'claude-sonnet-5-5', 'input_tokens' => 800, 'output_tokens' => 0]);
        fakeAi([['text' => 'ok']]);

        asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertOk();

        expect(\Illuminate\Support\Facades\Cache::has("ai-cap-warning:{$workspace->id}:" . now()->format('Y-m')))->toBeTrue();
    });

    test('a provider error is kept in the chat but never sent back to the model', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        app()->instance(AiProviderFactory::class, AiProviderFactory::fake(new class implements \App\Services\Ai\AiProvider {
            public function run(\App\Services\Ai\AiRequest $request): \App\Services\Ai\AiResult
            {
                throw new \App\Services\Ai\AiProviderException('Your AI provider account has no credits left.');
            }
        }));

        $response = asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertStatus(502);
        expect($response->json('messages.1.error'))->toBeTrue()->and($response->json('messages.1.content'))->toContain('no credits');

        $fake = fakeAi([['text' => 'hello again']]);
        asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['conversation_id' => $response->json('conversation.id'), 'content' => 'hi again'])->assertOk();
        expect(collect($fake->requests[0]->messages)->pluck('content')->all())->toBe(['hi', 'hi again']);
    });

    test('queue mode answers in a job running as the sender', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        aiTask(aiProject($workspace, $users['owner']), $users['owner'], 'API docs');
        config(['ai_assistant.queue' => true]);
        \Illuminate\Support\Facades\Queue::fake();

        $response = asAi($this, $users['manager'])->postJson(route('ai-assistant.send'), ['content' => 'move API docs to Done'])
            ->assertStatus(202)->assertJsonPath('pending', true);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ProcessAiMessage::class);

        auth()->logout();
        fakeAi([['tool' => 'change_task_status', 'args' => ['task' => 'API docs', 'stage' => 'Done']], ['text' => 'Card shown.']]);
        (new \App\Jobs\ProcessAiMessage($users['manager']->id, $response->json('conversation.id'), $response->json('messages.0.id')))->handle(app(\App\Services\Ai\AiAssistant::class));

        $call = AiToolCall::withoutGlobalScope('workspace')->where('tool', 'change_task_status')->first();
        expect($call->status)->toBe('pending')->and($call->user_id)->toBe($users['manager']->id)
            ->and(\App\Models\AiMessage::where('role', 'assistant')->latest('id')->value('content'))->toBe('Card shown.')
            ->and(auth()->check())->toBeFalse();
    });

    test('the writing helper uses the workspace AI provider and counts toward the cap', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        fakeAi([['text' => 'A short project description.']]);

        $this->actingAs($users['owner'])->postJson(route('chatgpt.generate'), ['prompt' => 'Describe a website project'])
            ->assertOk()->assertJsonPath('content', 'A short project description.');
        expect(AiUsage::withoutGlobalScope('workspace')->where('workspace_id', $workspace->id)->exists())->toBeTrue();
    });

    test('ai:eval scores the first tool the model picks', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        fakeAi([['tool' => 'list_tasks', 'args' => ['overdue' => true]]]);

        $this->artisan('ai:eval', ['--user' => $users['manager']->email, '--limit' => 1, '--threshold' => 100])
            ->expectsOutputToContain('Correct tool picks: 1/1 (100%)')
            ->assertSuccessful();
    });
});

describe('screens still work through the new shared actions', function () {
    test('bug form, bug status, expense approval, timesheet approval, invoice send and project form', function () {
        [$workspace, $users] = aiWorkspace();
        $project = aiProject($workspace, $users['owner']);
        $owner = $users['owner'];

        $this->actingAs($owner)->post(route('bugs.store'), ['project_id' => $project->id, 'title' => 'Form bug', 'priority' => 'low', 'severity' => 'minor'])->assertSessionHasNoErrors();
        $bug = Bug::where('title', 'Form bug')->first();
        expect($bug->reported_by)->toBe($owner->id);

        $resolved = BugStatus::where('workspace_id', $workspace->id)->where('name', 'Resolved')->first();
        $this->actingAs($owner)->put(route('bugs.change-status', $bug), ['bug_status_id' => $resolved->id]);
        expect($bug->fresh()->resolved_by)->toBe($owner->id);

        $expense = aiExpense($project, $users['member']);
        $this->actingAs($owner)->post(route('expense-approvals.approve', $expense), ['notes' => 'ok']);
        expect($expense->fresh()->status)->toBe('approved');

        $approval = aiTimesheet($workspace, $project, $users['member'], $owner);
        $this->actingAs($owner)->post(route('timesheet-approvals.approve', $approval));
        expect($approval->fresh()->status)->toBe('approved')->and($approval->timesheet->fresh()->status)->toBe('approved');

        $invoice = aiInvoice($workspace, $project, $owner);
        $this->actingAs($owner)->post(route('invoices.send', $invoice));
        expect($invoice->fresh()->status)->toBe('sent');

        $this->actingAs($owner)->post(route('projects.store'), ['title' => 'From form', 'status' => 'active', 'priority' => 'high', 'member_ids' => [$users['member']->id]])
            ->assertSessionHasNoErrors();
        expect(Project::where('title', 'From form')->first()->members()->count())->toBe(1);
    });
});

// ── Deterministic forms and fallbacks ─────────────────────────────────────────

describe('deterministic forms', function () {
    test('the model is never told a form field is required', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $fake = fakeAi([['text' => 'ok']]);

        asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertOk();

        $createTask = collect($fake->requests[0]->tools)->firstWhere('name', 'create_task');
        expect(collect($createTask->parameters)->pluck('required')->unique()->all())->toBe([false]);
    });

    test('the topic buttons offered match the tools the user may use', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);

        asAi($this, $users['owner'])->get(route('ai-assistant.index'))
            ->assertInertia(fn (Assert $page) => $page->where('topics', collect(\App\Services\Ai\Topics::keys())
                ->map(fn ($key) => ['key' => $key, 'label' => \App\Services\Ai\Topics::label($key)])->all()));
    });

    test('a topic narrows the tools, stays on the conversation, and is cleared with an empty value', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);

        $fake = fakeAi([['text' => 'ok']]);
        $conversationId = asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'hi', 'topic' => 'tasks'])
            ->assertJsonPath('conversation.topic', 'tasks')
            ->json('conversation.id');

        $expected = collect(\App\Services\Ai\Topics::toolNames('tasks'))->sort()->values()->all();
        expect(toolNames($fake))->toBe($expected)
            ->and($fake->requests[0]->system)->toContain('picked the "Tasks" topic');

        // Next message without a topic value: the topic is still on.
        $fake = fakeAi([['text' => 'ok']]);
        asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['conversation_id' => $conversationId, 'content' => 'login page for the website'])->assertOk();
        expect(toolNames($fake))->toBe($expected);

        // Removed: every tool again.
        $fake = fakeAi([['text' => 'ok']]);
        asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['conversation_id' => $conversationId, 'content' => 'hi', 'topic' => ''])
            ->assertJsonPath('conversation.topic', null);
        expect(toolNames($fake))->toHaveCount(count(app(\App\Services\Ai\ToolRegistry::class)->all()));
    });

    test('a message clearly about another topic gets every tool, in the same single AI call', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);

        $fake = fakeAi([['text' => 'ok']]);
        asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'approve the pending timesheets', 'topic' => 'tasks'])->assertOk();

        expect($fake->requests)->toHaveCount(1)
            ->and(toolNames($fake))->toContain('decide_timesheets', 'create_task')
            ->and($fake->requests[0]->system)->not->toContain('picked the');
    });

    test('a topic never adds tools the user may not use', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);

        $fake = fakeAi([['text' => 'ok']]);
        asAi($this, $users['manager'])->postJson(route('ai-assistant.send'), ['content' => 'hi', 'topic' => 'finance'])->assertOk();

        foreach ($fake->requests[0]->tools as $spec) {
            expect(app(\App\Services\Ai\ToolRegistry::class)->find($spec->name)->allowedFor($users['manager']))->toBeTrue();
        }
        asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'hi', 'topic' => 'nonsense'])->assertJsonValidationErrors('topic');
    });

    test('with the AI down, a bare message under a topic still starts that topic\'s draft', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        aiProject($workspace, $users['owner']);
        fakeAi([fn () => throw new \App\Services\Ai\AiProviderException('Your AI provider could not be reached.')]);

        $error = collect(asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'Checkout page', 'topic' => 'tasks'])
            ->assertStatus(502)->json('messages'))->firstWhere('role', 'assistant');

        $card = $error['cards'][0];
        expect($card['tool'])->toBe('create_task')
            ->and($card['stage'])->toBe('review')
            ->and(collect($card['fields'])->firstWhere('name', 'title')['value'])->toBe('Checkout page');
    });

    test('the reply matcher answers only plain short answers', function (string $reply, array $expected) {
        $form = ['fields' => [
            ['name' => 'project', 'type' => 'select', 'kind' => 'project', 'required' => true, 'value' => null, 'options' => [['value' => '1', 'label' => 'Website Redesign'], ['value' => '2', 'label' => 'Mobile App']]],
            ['name' => 'priority', 'type' => 'select', 'kind' => 'enum', 'required' => true, 'value' => 'medium', 'options' => [['value' => 'low', 'label' => 'Low'], ['value' => 'medium', 'label' => 'Medium'], ['value' => 'high', 'label' => 'High']]],
            ['name' => 'assignee', 'type' => 'select', 'kind' => 'member', 'required' => true, 'value' => 'none', 'options' => [['value' => 'none', 'label' => 'Unassigned'], ['value' => '7', 'label' => 'Ravi Kumar (ravi@x.com)'], ['value' => '9', 'label' => 'Priya Shah (priya@x.com)']]],
        ]];

        expect(app(\App\Services\Ai\Forms\ReplyMatcher::class)->match($form, ['project'], $reply, 9))->toBe($expected);
    })->with([
        ['Mobile App', ['project' => '2']],
        ['mobile, make it high', ['project' => '2', 'priority' => 'high']],
        ['website redesign and assign to Ravi', ['project' => '1', 'assignee' => '7']],
        ['the mobile one for me', ['project' => '2', 'assignee' => '9']],
        ['which projects are overdue?', []],
        ['show high priority bugs in mobile app', []],
        ['high', []],
        ['put it under the project for our mobile app please, the one we started last week', []],
    ]);

    test('fallback: when the AI provider fails, a clear command still gets its form', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        fakeAi([fn () => throw new \App\Services\Ai\AiProviderException('Your AI provider account has no credits left.')]);

        $messages = asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'create a to do task for a login page'])
            ->assertStatus(502)
            ->json('messages');

        $error = collect($messages)->firstWhere('role', 'assistant');
        expect($error['error'])->toBeTrue()
            ->and($error['cards'][0]['summary'])->toBe('New task')
            ->and(collect($error['cards'][0]['fields'])->firstWhere('name', 'title')['value'])->toBe('Login page');
    });

    test('fallback: when the model answers without a card, the matching form is offered', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        fakeAi([['text' => 'Which project should it go in?']]);

        $reply = asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'report a bug: checkout crashes'])
            ->assertOk()
            ->json('messages.1');

        expect($reply['content'])->toBe('Which project should it go in?')
            ->and($reply['cards'][0]['summary'])->toBe('Report a bug');
    });

    test('a read question gets no form', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        fakeAi([['text' => 'None are overdue.']]);

        expect(asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'which tasks are overdue?'])->json('messages.1.cards'))
            ->toBeEmpty();
    });

    test('the keyword matcher recognises clear commands only', function (string $text, ?string $tool, ?string $title) {
        $match = app(\App\Services\Ai\Forms\IntentMatcher::class)->match($text);

        expect($match['tool'] ?? null)->toBe($tool)
            ->and($match['args']['title'] ?? null)->toBe($title);
    })->with([
        ['create a to do task for a login page', 'create_task', 'Login page'],
        ['report a bug: checkout crashes', 'create_bug', 'Checkout crashes'],
        ['create a project in the name of sundal', 'create_project', 'Sundal'],
        ['create a invoice in sundal project', 'create_invoice', null],
        ['add a contract for acme', null, null],
        ['create a task in sundal project', 'create_task', null],
        ['create a task to add invoice export', 'create_task', null],
        ['add an expense for hosting', 'create_expense', 'Hosting'],
        ['log 3 hours on website redesign', 'log_time', null],
        ['log 2.5h on the login task', 'log_time', null],
        ['mark INV-104 as paid', 'mark_invoice_paid', null],
        ['submit my timesheet', 'submit_timesheet', null],
        ['start the timer on mobile app', 'start_timer', null],
        ['change the budget of sundal project', null, null],
        ['stop the timer', null, null],
        ['assign the login bug to Ravi', 'assign_bug', null],
        ['move API docs to done', 'change_task_status', null],
        ['which tasks are overdue?', null, null],
        ['show tasks created this week', null, null],
    ]);
});

// ── Sidebar: favorites, archive, waiting, paging ──────────────────────────────

function aiChat(Workspace $workspace, User $user, string $title, array $attrs = []): AiConversation
{
    $chat = AiConversation::withoutGlobalScope('workspace')->create(array_merge([
        'workspace_id' => $workspace->id, 'user_id' => $user->id, 'title' => $title, 'last_message_at' => now(),
    ], $attrs));
    $chat->messages()->create(['role' => 'assistant', 'content' => "Reply in **{$title}**, see [the task](/tasks/1)."]);

    return $chat;
}

describe('sidebar', function () {
    test('the list shows previews and waiting cards, and filters favorites and archived chats, with counts', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $plain = aiChat($workspace, $users['owner'], 'Plain');
        $starred = aiChat($workspace, $users['owner'], 'Starred', ['is_favorite' => true]);
        $archived = aiChat($workspace, $users['owner'], 'Put away', ['archived_at' => now()]);
        $waiting = aiChat($workspace, $users['owner'], 'Needs OK');
        AiToolCall::withoutGlobalScope('workspace')->create(['workspace_id' => $workspace->id, 'user_id' => $users['owner']->id,
            'ai_conversation_id' => $waiting->id, 'tool' => 'create_task', 'status' => 'pending', 'summary' => 'New task']);
        aiChat($workspace, $users['manager'], 'Not mine');

        $list = fn (string $filter) => collect(asAi($this, $users['owner'])
            ->getJson(route('ai-assistant.conversations.index', ['filter' => $filter]))->assertOk()->json('conversations'))->pluck('title')->all();

        expect($list('all'))->toEqualCanonicalizing(['Plain', 'Starred', 'Needs OK'])
            ->and($list('favorites'))->toBe(['Starred'])
            ->and($list('archived'))->toBe(['Put away']);

        $row = collect(asAi($this, $users['owner'])->getJson(route('ai-assistant.conversations.index'))->json('conversations'))->firstWhere('title', 'Needs OK');
        expect($row['preview'])->toBe('Reply in Needs OK, see the task.')->and($row['waiting'])->toBe(1);

        asAi($this, $users['owner'])->getJson(route('ai-assistant.conversations.index'))
            ->assertJsonPath('counts', ['favorites' => 1, 'archived' => 1]);
        asAi($this, $users['owner'])->get(route('ai-assistant.index'))
            ->assertInertia(fn (Assert $page) => $page->where('conversationCounts', ['favorites' => 1, 'archived' => 1])->has('conversations', 3));
        asAi($this, $users['owner'])->getJson(route('ai-assistant.conversations.index', ['filter' => 'waiting']))->assertJsonValidationErrors('filter');
    });

    test('a card past its confirmation time no longer counts as waiting', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $chat = aiChat($workspace, $users['owner'], 'Old card');
        $card = AiToolCall::withoutGlobalScope('workspace')->create(['workspace_id' => $workspace->id, 'user_id' => $users['owner']->id,
            'ai_conversation_id' => $chat->id, 'tool' => 'create_task', 'status' => 'pending', 'summary' => 'New task']);
        $card->forceFill(['created_at' => now()->subHour()])->save();

        $row = collect(asAi($this, $users['owner'])->getJson(route('ai-assistant.conversations.index'))->json('conversations'))->firstWhere('title', 'Old card');
        expect($row['waiting'])->toBe(0);
    });

    test('star and archive a chat; writing in an archived chat brings it back', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $chat = aiChat($workspace, $users['owner'], 'Weekly report');

        asAi($this, $users['owner'])->patchJson(route('ai-assistant.conversations.update', $chat), ['is_favorite' => true])
            ->assertJsonPath('conversation.is_favorite', true)->assertJsonPath('counts.favorites', 1);
        asAi($this, $users['owner'])->patchJson(route('ai-assistant.conversations.update', $chat), ['archived' => true])
            ->assertJsonPath('conversation.archived', true)->assertJsonPath('counts.archived', 1);
        expect($chat->fresh()->title)->toBe('Weekly report');

        fakeAi([['text' => 'Here it is.']]);
        asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['conversation_id' => $chat->id, 'content' => 'again please'])
            ->assertOk()->assertJsonPath('conversation.archived', false);
        expect($chat->fresh()->archived_at)->toBeNull();

        // Someone else's chat cannot be starred.
        asAi($this, $users['manager'])->patchJson(route('ai-assistant.conversations.update', $chat), ['is_favorite' => false])->assertNotFound();
    });

    test('the list comes 20 at a time', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        foreach (range(1, 21) as $i) {
            aiChat($workspace, $users['owner'], "Chat {$i}", ['last_message_at' => now()->subMinutes($i)]);
        }

        asAi($this, $users['owner'])->get(route('ai-assistant.index'))
            ->assertInertia(fn (Assert $page) => $page->has('conversations', 20)->where('conversationsHasMore', true));
        asAi($this, $users['owner'])->getJson(route('ai-assistant.conversations.index', ['page' => 2]))
            ->assertJsonPath('has_more', false)->assertJsonPath('conversations.0.title', 'Chat 21');
        asAi($this, $users['owner'])->getJson(route('ai-assistant.conversations.index', ['filter' => 'nonsense']))->assertJsonValidationErrors('filter');
    });
});

// ── Module tools, phase 1: finance and time ───────────────────────────────────

describe('finance and time tools', function () {
    test('under a topic button, a draft still waiting in the chat keeps its tool', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        aiProject($workspace, $users['owner']);

        $fake = fakeAi([['tool' => 'create_invoice', 'args' => []], ['text' => 'Which tasks?']]);
        $chat = asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'create an invoice please'])->json('conversation.id');
        expect(toolNames($fake))->toContain('create_invoice', 'create_task', 'log_time');

        $fake = fakeAi([['text' => 'ok']]);
        asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['conversation_id' => $chat, 'content' => 'what about the login page task from yesterday?', 'topic' => 'tasks'])->assertOk();
        expect(toolNames($fake))->toContain('create_task', 'list_tasks', 'create_invoice')
            ->and(toolNames($fake))->not->toContain('create_expense');
    });

    test('create_invoice bills unbilled tasks as a draft, and can be undone', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $project = aiProject($workspace, $users['owner']);
        $login = aiTask($project, $users['owner'], 'Login page');
        $signup = aiTask($project, $users['owner'], 'Signup form');
        $billed = aiTask($project, $users['owner'], 'Old work');
        $sent = aiInvoice($workspace, $project, $users['owner'], ['status' => 'sent']);
        \App\Models\InvoiceItem::create(['invoice_id' => $sent->id, 'type' => 'task', 'description' => 'Old work', 'rate' => 10, 'amount' => 10, 'task_id' => $billed->id, 'sort_order' => 1]);

        $card = aiCall($this, $users['owner'], 'create_invoice', ['project' => 'Website Redesign', 'tasks' => 'Login page;Signup form', 'amount' => '500'])['card'];
        $tasks = collect($card['fields'])->firstWhere('name', 'tasks');

        expect($card['stage'])->toBe('review')
            ->and($tasks['value'])->toBe([(string) $login->id, (string) $signup->id])
            ->and(collect($tasks['options'])->pluck('value')->all())->not->toContain((string) $billed->id)
            ->and($card['details']['Total'])->toBe('1,000.00')
            ->and($card['items'])->toHaveCount(2);

        aiConfirm($this, $users['owner'], $card['id'])->assertJsonPath('card.status', 'done')->assertJsonPath('card.can_undo', true);

        $invoice = \App\Models\Invoice::withoutGlobalScope('workspace')->where('title', 'Invoice for Website Redesign')->first();
        expect($invoice->status)->toBe('draft')
            ->and((float) $invoice->total_amount)->toBe(1000.0)
            ->and($invoice->created_by)->toBe($users['owner']->id)
            ->and($invoice->items()->pluck('task_id')->sort()->values()->all())->toBe([$login->id, $signup->id]);

        asAi($this, $users['owner'])->postJson(route('ai-assistant.tool-calls.undo', $card['id']))->assertJsonPath('card.status', 'undone');
        expect(\App\Models\Invoice::withoutGlobalScope('workspace')->whereKey($invoice->id)->exists())->toBeFalse();
    });

    test('the invoice draft asks project, then that project\'s tasks, then a typed amount with no AI call', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $web = aiProject($workspace, $users['owner']);
        $mobile = aiProject($workspace, $users['owner'], 'Mobile App');
        aiTask($web, $users['owner'], 'Header');
        $push = aiTask($mobile, $users['owner'], 'Push alerts');

        $fake = fakeAi([['tool' => 'create_invoice', 'args' => []], ['text' => 'Which project?']]);
        $first = asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'create an invoice']);
        [$card, $chat] = [$first->json('messages.1.cards.0'), $first->json('conversation.id')];
        expect($card['ask'])->toBe(['project', 'tasks', 'amount'])->and($card['question'])->toBe('Which project is the invoice for?');

        // Picking Mobile App narrows the tasks to that project.
        $next = asAi($this, $users['owner'])->postJson(route('ai-assistant.tool-calls.update', $card['id']), ['fields' => ['project' => (string) $mobile->id]])->json('card');
        $tasks = collect($next['fields'])->firstWhere('name', 'tasks');
        expect($next['ask'])->toBe(['tasks', 'amount'])
            ->and(collect($tasks['options'])->pluck('value')->all())->toBe([(string) $push->id]);

        $next = asAi($this, $users['owner'])->postJson(route('ai-assistant.tool-calls.update', $card['id']), ['fields' => ['tasks' => [(string) $push->id]]])->json('card');
        expect($next['ask'])->toBe(['amount']);

        $reply = asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['conversation_id' => $chat, 'content' => '750'])->json('messages.1');
        expect($fake->requests)->toHaveCount(1)
            ->and($reply['cards'][0]['stage'])->toBe('review')
            ->and($reply['cards'][0]['details']['Total'])->toBe('750.00');
    });

    test('delete_invoice needs the number typed and only offers drafts', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $project = aiProject($workspace, $users['owner']);
        $draft = aiInvoice($workspace, $project, $users['owner']);
        $sent = aiInvoice($workspace, $project, $users['owner'], ['status' => 'sent']);

        $card = aiCall($this, $users['owner'], 'delete_invoice', ['invoice' => $draft->invoice_number])['card'];
        expect($card['confirm_phrase'])->toBe($draft->invoice_number);
        aiConfirm($this, $users['owner'], $card['id'], 'nope')->assertJsonPath('card.status', 'pending');
        expect($draft->fresh())->not->toBeNull();
        aiConfirm($this, $users['owner'], $card['id'], $draft->invoice_number)->assertJsonPath('card.status', 'done');
        expect(\App\Models\Invoice::withoutGlobalScope('workspace')->whereKey($draft->id)->exists())->toBeFalse();

        $refused = collect(aiCall($this, $users['owner'], 'delete_invoice', ['invoice' => $sent->invoice_number])['card']['fields'])->firstWhere('name', 'invoice');
        expect($refused['value'])->toBeNull()
            ->and(collect($refused['options'])->pluck('value')->all())->not->toContain((string) $sent->id);
    });

    test('mark_invoice_paid needs the number typed and can be undone', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $invoice = aiInvoice($workspace, aiProject($workspace, $users['owner']), $users['owner'], ['status' => 'sent']);

        $card = aiCall($this, $users['owner'], 'mark_invoice_paid', ['invoice' => $invoice->invoice_number])['card'];
        expect($card['details']['Amount paid'])->toBe('1,000.00')->and($card['confirm_phrase'])->toBe($invoice->invoice_number);

        aiConfirm($this, $users['owner'], $card['id'], $invoice->invoice_number)->assertJsonPath('card.status', 'done');
        expect($invoice->fresh()->status)->toBe('paid')->and((float) $invoice->fresh()->paid_amount)->toBe(1000.0);

        asAi($this, $users['owner'])->postJson(route('ai-assistant.tool-calls.undo', $card['id']))->assertJsonPath('card.status', 'undone');
        expect($invoice->fresh()->status)->toBe('sent')->and((float) $invoice->fresh()->paid_amount)->toBe(0.0);
    });

    test('update_invoice changes only what was said, and can be undone', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $invoice = aiInvoice($workspace, aiProject($workspace, $users['owner']), $users['owner']);
        $oldDue = $invoice->due_date->format('Y-m-d');
        $newDue = now()->addDays(40)->toDateString();

        $card = aiCall($this, $users['owner'], 'update_invoice', ['invoice' => $invoice->invoice_number, 'due_date' => $newDue])['card'];
        expect($card['details']['Due date'])->toBe("{$oldDue} → {$newDue}");

        aiConfirm($this, $users['owner'], $card['id'])->assertJsonPath('card.status', 'done');
        expect($invoice->fresh()->due_date->format('Y-m-d'))->toBe($newDue)->and($invoice->fresh()->title)->toBe('Website work');

        asAi($this, $users['owner'])->postJson(route('ai-assistant.tool-calls.undo', $card['id']))->assertJsonPath('card.status', 'undone');
        expect($invoice->fresh()->due_date->format('Y-m-d'))->toBe($oldDue);
    });

    test('expenses: create waits for approval, no future dates, update can be undone, approved ones are left alone', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        aiProject($workspace, $users['owner']);

        $card = aiCall($this, $users['owner'], 'create_expense', ['title' => 'Hosting', 'project' => 'Website Redesign', 'amount' => '120'])['card'];
        aiConfirm($this, $users['owner'], $card['id'])->assertJsonPath('card.status', 'done');
        $expense = \App\Models\ProjectExpense::where('title', 'Hosting')->first();
        expect($expense->status)->toBe('pending')->and((float) $expense->amount)->toBe(120.0)->and($expense->submitted_by)->toBe($users['owner']->id);

        $future = aiCall($this, $users['owner'], 'create_expense', ['title' => 'Later', 'project' => 'Website Redesign', 'amount' => '5', 'expense_date' => now()->addDay()->toDateString()]);
        expect($future['card']['form_error'])->toContain('cannot be in the future');

        $card = aiCall($this, $users['owner'], 'update_expense', ['expense' => 'Hosting', 'amount' => '150'])['card'];
        aiConfirm($this, $users['owner'], $card['id'])->assertJsonPath('card.status', 'done');
        expect((float) $expense->fresh()->amount)->toBe(150.0);
        asAi($this, $users['owner'])->postJson(route('ai-assistant.tool-calls.undo', $card['id']))->assertJsonPath('card.status', 'undone');
        expect((float) $expense->fresh()->amount)->toBe(120.0);

        $expense->update(['status' => 'approved']);
        $delete = collect(aiCall($this, $users['owner'], 'delete_expense', ['expense' => 'Hosting'])['card']['fields'])->firstWhere('name', 'expense');
        expect($delete['value'])->toBeNull()->and($expense->fresh())->not->toBeNull();
    });

    test('budgets: one per project with a General category; update can be undone', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $project = aiProject($workspace, $users['owner']);

        $card = aiCall($this, $users['owner'], 'create_budget', ['project' => 'Website Redesign', 'total' => '5000'])['card'];
        aiConfirm($this, $users['owner'], $card['id'])->assertJsonPath('card.status', 'done');
        $budget = \App\Models\ProjectBudget::where('project_id', $project->id)->first();
        expect((float) $budget->total_budget)->toBe(5000.0)
            ->and($budget->period_type)->toBe('project')
            ->and($budget->categories()->pluck('name')->all())->toBe(['General'])
            ->and($budget->created_by)->toBe($users['owner']->id);

        expect(aiCall($this, $users['owner'], 'create_budget', ['project' => 'Website Redesign', 'total' => '100'])['card']['form_error'])->toContain('already has a budget');

        $card = aiCall($this, $users['owner'], 'update_budget', ['project' => 'Website Redesign', 'total' => '8000', 'status' => 'completed'])['card'];
        aiConfirm($this, $users['owner'], $card['id'])->assertJsonPath('card.status', 'done');
        expect((float) $budget->fresh()->total_budget)->toBe(8000.0)->and($budget->fresh()->status)->toBe('completed');
        asAi($this, $users['owner'])->postJson(route('ai-assistant.tool-calls.undo', $card['id']))->assertJsonPath('card.status', 'undone');
        expect((float) $budget->fresh()->total_budget)->toBe(5000.0)->and($budget->fresh()->status)->toBe('active');
    });

    test('time: log, change and undo, the 24-hour day, submit, then the week is locked', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        aiProject($workspace, $users['manager'], 'Website Redesign');
        $manager = $users['manager'];

        $card = aiCall($this, $manager, 'log_time', ['project' => 'Website Redesign', 'hours' => '3', 'description' => 'Header work'])['card'];
        aiConfirm($this, $manager, $card['id'])->assertJsonPath('card.status', 'done');
        $entry = \App\Models\TimesheetEntry::where('user_id', $manager->id)->first();
        expect((float) $entry->hours)->toBe(3.0)
            ->and($entry->timesheet->start_date->format('Y-m-d'))->toBe(now()->startOfWeek()->toDateString())
            ->and($entry->timesheet->status)->toBe('draft');

        expect(aiCall($this, $manager, 'log_time', ['project' => 'Website Redesign', 'hours' => '22'])['card']['form_error'])->toContain('at most 24 hours');

        $card = aiCall($this, $manager, 'update_time_entry', ['entry' => "#{$entry->id}", 'hours' => '4'])['card'];
        aiConfirm($this, $manager, $card['id'])->assertJsonPath('card.status', 'done');
        expect((float) $entry->fresh()->hours)->toBe(4.0)->and((float) $entry->timesheet->fresh()->total_hours)->toBe(4.0);
        asAi($this, $manager)->postJson(route('ai-assistant.tool-calls.undo', $card['id']))->assertJsonPath('card.status', 'undone');
        expect((float) $entry->fresh()->hours)->toBe(3.0);

        // Only one timesheet can be submitted: it is picked without asking.
        $card = aiCall($this, $manager, 'submit_timesheet', [])['card'];
        expect($card['stage'])->toBe('review');
        aiConfirm($this, $manager, $card['id'])->assertJsonPath('card.status', 'done');
        expect($entry->timesheet->fresh()->status)->toBe('submitted')
            ->and(\App\Models\TimesheetApproval::where('timesheet_id', $entry->timesheet_id)->value('approver_id'))->toBe($users['owner']->id);

        expect(aiCall($this, $manager, 'log_time', ['project' => 'Website Redesign', 'hours' => '1'])['card']['form_error'])->toContain('already submitted');
        $delete = collect(aiCall($this, $manager, 'delete_time_entry', ['entry' => "#{$entry->id}"])['card']['fields'])->firstWhere('name', 'entry');
        expect($delete['value'])->toBeNull();
    });

    test('nobody changes someone else\'s time through the assistant', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $project = aiProject($workspace, $users['owner']);
        $timesheet = \App\Actions\Timesheets\LogTime::weekTimesheet($users['member']->id, $workspace->id, now()->toDateString());
        $theirs = \App\Models\TimesheetEntry::create(['timesheet_id' => $timesheet->id, 'project_id' => $project->id, 'user_id' => $users['member']->id,
            'date' => now()->toDateString(), 'hours' => 2, 'hourly_rate' => 0, 'is_billable' => true]);

        $field = collect(aiCall($this, $users['owner'], 'delete_time_entry', ['entry' => "#{$theirs->id}"])['card']['fields'])->firstWhere('name', 'entry');
        expect($field['value'])->toBeNull()->and($field['options'])->toBeEmpty()->and($theirs->fresh())->not->toBeNull();
    });

    test('the timer starts and stops through confirm cards', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        aiProject($workspace, $users['owner']);

        $card = aiCall($this, $users['owner'], 'start_timer', ['project' => 'Website Redesign'])['card'];
        aiConfirm($this, $users['owner'], $card['id'])->assertJsonPath('card.status', 'done');
        $owner = $users['owner']->fresh();
        expect((bool) $owner->timer_active)->toBeTrue();

        $this->travel(90)->minutes();
        $card = aiCall($this, $owner, 'stop_timer', [])['card'];
        aiConfirm($this, $owner, $card['id'])->assertJsonPath('card.status', 'done');
        expect((bool) $owner->fresh()->timer_active)->toBeFalse()
            ->and((float) \App\Models\TimesheetEntry::where('user_id', $owner->id)->value('hours'))->toBe(1.5);
    });

    test('list_my_time shows the user\'s own entries with ids', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $project = aiProject($workspace, $users['owner']);
        app(\App\Actions\Timesheets\LogTime::class)->handle($users['owner'], ['project_id' => $project->id, 'date' => now()->toDateString(), 'hours' => 2, 'description' => 'Mine']);
        $timesheet = \App\Actions\Timesheets\LogTime::weekTimesheet($users['member']->id, $workspace->id, now()->toDateString());
        \App\Models\TimesheetEntry::create(['timesheet_id' => $timesheet->id, 'project_id' => $project->id, 'user_id' => $users['member']->id,
            'date' => now()->toDateString(), 'hours' => 5, 'hourly_rate' => 0, 'is_billable' => true, 'description' => 'Theirs']);

        $result = app(\App\Services\Ai\Tools\ListMyTime::class)->run([], $users['owner']);
        expect($result['entries'])->toHaveCount(1)
            ->and($result['entries'][0]['description'])->toBe('Mine')
            ->and($result['total_hours'])->toBe(2.0);
    });

    test('the reply matcher reads a number only when one was asked', function () {
        $form = ['fields' => [['name' => 'amount', 'type' => 'number', 'kind' => 'number', 'required' => true, 'value' => null, 'options' => []]]];
        $matcher = app(\App\Services\Ai\Forms\ReplyMatcher::class);

        expect($matcher->match($form, ['amount'], '1,200.50', 1))->toBe(['amount' => '1200.50'])
            ->and($matcher->match($form, ['amount'], '2.5 hours', 1))->toBe(['amount' => '2.5'])
            ->and($matcher->match($form, [], '500', 1))->toBe([])
            ->and($matcher->match($form, ['amount'], 'between 3 and 4', 1))->toBe([]);
    });

    test('the screens still create invoices, expenses, budgets and time through the shared actions', function () {
        [$workspace, $users] = aiWorkspace();
        $project = aiProject($workspace, $users['owner']);
        $task = aiTask($project, $users['owner'], 'Login page');
        $this->actingAs($users['owner']);

        $this->post(route('invoices.store'), ['project_id' => $project->id, 'title' => 'Screen invoice', 'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(), 'items' => [['type' => 'task', 'amount' => 300, 'task_id' => $task->id]]])->assertRedirect();
        $invoice = \App\Models\Invoice::where('title', 'Screen invoice')->first();
        expect((float) $invoice->total_amount)->toBe(300.0)->and($invoice->items)->toHaveCount(1);

        $this->post(route('expenses.store'), ['project_id' => $project->id, 'amount' => 40, 'expense_date' => now()->toDateString(), 'title' => 'Screen expense'])->assertRedirect();
        expect(\App\Models\ProjectExpense::where('title', 'Screen expense')->value('status'))->toBe('pending');

        $this->post(route('budgets.store'), ['project_id' => $project->id, 'total_budget' => 900, 'period_type' => 'project', 'start_date' => now()->toDateString(),
            'categories' => [['name' => 'Dev', 'allocated_amount' => 900]]])->assertRedirect();
        expect(\App\Models\ProjectBudget::where('project_id', $project->id)->first()->categories()->pluck('name')->all())->toBe(['Dev']);

        $this->post(route('timesheet-entries.store'), ['project_id' => $project->id, 'date' => now()->toDateString(), 'hours' => 2])->assertRedirect();
        expect((float) \App\Models\TimesheetEntry::where('user_id', $users['owner']->id)->value('hours'))->toBe(2.0);
    });
});

// ── AI mode (own tab, session rules) ──────────────────────────────────────────

/** Open the AI mode tab with the current login. */
function openAiMode($test, User $user)
{
    return $test->actingAs($user)->get(route('ai-mode'));
}

function aiModeHeaders(Workspace $workspace): array
{
    return ['X-AI-Mode' => '1', 'X-AI-Workspace' => (string) $workspace->id];
}

describe('ai mode', function () {
    test('AI mode opens with the current login, no password prompt', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);

        openAiMode($this, $users['owner'])->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('ai-assistant/index')
            ->where('standalone', true)
            ->where('aiMode.workspaceId', $workspace->id)
            ->where('aiMode.idleSeconds', 30 * 60));
    });

    test('members and clients cannot open AI mode', function (string $role) {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);

        openAiMode($this, $users[$role])->assertForbidden();
    })->with(['member', 'client']);

    test('AI mode requests work after opening, and need the tab to have been opened', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        fakeAi([['text' => 'hello']]);

        $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'hi'], aiModeHeaders($workspace))
            ->assertStatus(423)->assertJsonPath('code', 'ai_mode_ended');

        openAiMode($this, $users['owner'])->assertOk();
        $this->postJson(route('ai-assistant.send'), ['content' => 'hi'], aiModeHeaders($workspace))->assertOk();

        // The normal AI Assistant page (no header) is not affected by AI mode rules.
        $this->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertOk();
    });

    test('a workspace switch in Sundal locks the AI mode tab to its own workspace', function () {
        [$workspace, $users] = aiWorkspace();
        [$other] = aiWorkspace();
        aiSettings($workspace);
        WorkspaceMember::create(['workspace_id' => $other->id, 'user_id' => $users['owner']->id, 'role' => 'owner', 'status' => 'active']);
        fakeAi([['text' => 'hello']]);
        openAiMode($this, $users['owner'])->assertOk();

        $users['owner']->update(['current_workspace_id' => $other->id]);

        $this->actingAs($users['owner']->fresh())->postJson(route('ai-assistant.send'), ['content' => 'hi'], aiModeHeaders($workspace))
            ->assertStatus(409)->assertJsonPath('code', 'workspace_changed');
    });

    test('AI mode locks after the idle timeout; polling does not count as activity, actions do', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace, ['idle_timeout_minutes' => 15]);
        fakeAi([['text' => 'ok'], ['text' => 'ok']]);
        openAiMode($this, $users['owner'])->assertOk();

        // 10 minutes of the tab polling: no activity.
        $this->travel(10)->minutes();
        $this->post(route('ai-mode.heartbeat'));
        $this->getJson(route('ai-mode.status'), aiModeHeaders($workspace))->assertJsonPath('code', 'ok');

        // An action restarts the clock.
        $this->postJson(route('ai-assistant.send'), ['content' => 'hi'], aiModeHeaders($workspace))->assertOk();
        $this->travel(10)->minutes();
        $this->post(route('ai-mode.heartbeat'));
        $this->getJson(route('ai-mode.status'))->assertJsonPath('code', 'ok');

        // 16 minutes since the last action: locked.
        $this->travel(6)->minutes();
        $this->post(route('ai-mode.heartbeat'));
        $this->postJson(route('ai-assistant.send'), ['content' => 'hi'], aiModeHeaders($workspace))
            ->assertStatus(423)->assertJsonPath('code', 'locked_idle');
        $this->postJson(route('ai-mode.keep-alive'))->assertStatus(423);
    });

    test('Continue after the idle pause resumes with the current login', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        openAiMode($this, $users['owner'])->assertOk();
        $this->travel(31)->minutes();
        $this->post(route('ai-mode.heartbeat'));
        $this->getJson(route('ai-mode.status'))->assertJsonPath('code', 'locked_idle');

        $this->postJson(route('ai-mode.unlock'))->assertOk()->assertJsonPath('code', 'ok');
        $this->getJson(route('ai-mode.status'))->assertJsonPath('code', 'ok');
    });

    test('AI mode only works while a Sundal tab is open', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        fakeAi([['text' => 'ok']]);
        openAiMode($this, $users['owner'])->assertOk();

        // No Sundal tab checked in for 3 minutes (closed): locked, even with recent activity.
        $this->travel(3)->minutes();
        $this->postJson(route('ai-assistant.send'), ['content' => 'hi'], aiModeHeaders($workspace))
            ->assertStatus(423)->assertJsonPath('code', 'locked_app_closed');

        // Continue does not lift it; Sundal coming back does.
        $this->postJson(route('ai-mode.unlock'))->assertJsonPath('code', 'locked_app_closed');
        $this->post(route('ai-mode.heartbeat'))->assertOk();
        $this->getJson(route('ai-mode.status'))->assertJsonPath('code', 'ok');
    });

    test('the owner chooses the idle timeout from the allowed options', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $base = ['provider' => 'anthropic', 'model' => 'claude-sonnet-5-5', 'retention_days' => 90];

        asAi($this, $users['owner'])->putJson(route('ai-assistant.settings.update'), [...$base, 'idle_timeout_minutes' => 45])
            ->assertJsonValidationErrors('idle_timeout_minutes');
        asAi($this, $users['owner'])->put(route('ai-assistant.settings.update'), [...$base, 'idle_timeout_minutes' => 60])->assertSessionHasNoErrors();

        expect(AiProviderSetting::withoutGlobalScope('workspace')->first()->idle_timeout_minutes)->toBe(60);
        openAiMode($this, $users['owner'])->assertInertia(fn (Assert $page) => $page->where('aiMode.idleSeconds', 3600));
    });
});

// ── Security hardening ────────────────────────────────────────────────────────

describe('security', function () {
    test('the normal AI Assistant page opens with the current login and starts the AI session', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        fakeAi([['text' => 'ok']]);

        $this->actingAs($users['owner'])->get(route('ai-assistant.index'))->assertOk();
        $this->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertOk();
    });

    test('the session rules cannot be skipped by leaving out the AI mode header', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        fakeAi([['text' => 'ok'], ['text' => 'ok']]);

        // No AI session opened: refused, header or not.
        $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'hi'])
            ->assertStatus(423)->assertJsonPath('code', 'ai_mode_ended');

        // Opened from the normal page: works, and Sundal-open does not apply there.
        $this->actingAs($users['owner'])->get(route('ai-assistant.index'))->assertOk();
        $this->travel(5)->minutes();
        $this->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertOk();

        // The idle lock applies to the normal page too.
        $this->travel(31)->minutes();
        $this->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertStatus(423)->assertJsonPath('code', 'locked_idle');
        $this->putJson(route('ai-assistant.settings.update'), ['provider' => 'anthropic', 'model' => 'claude-sonnet-5-5'])->assertStatus(423);
    });

    test('changing the Azure endpoint needs the API key again, so the saved key never goes to a new address', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace, ['provider' => 'azure_openai', 'model' => 'gpt-deploy', 'azure_endpoint' => 'https://ours.openai.azure.com', 'azure_deployment' => 'gpt-deploy']);
        $base = ['provider' => 'azure_openai', 'model' => 'gpt-deploy', 'azure_deployment' => 'gpt-deploy', 'retention_days' => 90];

        asAi($this, $users['owner'])->postJson(route('ai-assistant.settings.test'), [...$base, 'azure_endpoint' => 'https://someone-else.openai.azure.com'])
            ->assertJsonValidationErrors('api_key');
        asAi($this, $users['owner'])->putJson(route('ai-assistant.settings.update'), [...$base, 'azure_endpoint' => 'https://someone-else.openai.azure.com'])
            ->assertJsonValidationErrors('api_key');

        // Same endpoint: the saved key may be kept.
        asAi($this, $users['owner'])->put(route('ai-assistant.settings.update'), [...$base, 'azure_endpoint' => 'https://ours.openai.azure.com/'])->assertSessionHasNoErrors();
    });

    test('card values must be plain values; nested or misplaced lists are refused without an error page', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        aiProject($workspace, $users['owner']);
        aiProject($workspace, $users['owner'], 'Mobile App');
        fakeAi([['tool' => 'create_task', 'args' => ['title' => 'Login page']], ['text' => 'Which project?']]);
        $cardId = asAi($this, $users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'create a task for a login page'])->json('messages.1.cards.0.id');

        asAi($this, $users['owner'])->postJson(route('ai-assistant.tool-calls.update', $cardId), ['fields' => ['project' => [['nested']]]])
            ->assertJsonValidationErrors('fields.project');
        asAi($this, $users['owner'])->postJson(route('ai-assistant.tool-calls.update', $cardId), ['fields' => ['project' => str_repeat('x', 5000)]])
            ->assertJsonValidationErrors('fields.project');

        // A list where one value is expected: the card says so, nothing is created.
        asAi($this, $users['owner'])->postJson(route('ai-assistant.tool-calls.update', $cardId), ['fields' => ['project' => ['1', '2']]])
            ->assertOk()
            ->assertJsonPath('card.fields.1.error', 'Choose a valid option.');
        expect(Task::where('title', 'Login page')->exists())->toBeFalse();
    });

    test('card actions and connection tests are rate limited', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);

        for ($i = 0; $i < 60; $i++) {
            \Illuminate\Support\Facades\RateLimiter::hit('ai-cards:' . $users['owner']->id, 60);
        }
        $card = AiToolCall::withoutGlobalScope('workspace')->create(['workspace_id' => $workspace->id, 'user_id' => $users['owner']->id, 'tool' => 'create_task', 'status' => 'pending', 'summary' => 'New task']);
        asAi($this, $users['owner'])->postJson(route('ai-assistant.tool-calls.cancel', $card->id))->assertStatus(429);
        expect($card->fresh()->status)->toBe('pending');

        for ($i = 0; $i < 10; $i++) {
            \Illuminate\Support\Facades\RateLimiter::hit('ai-connection-test:' . $users['owner']->id, 60);
        }
        asAi($this, $users['owner'])->postJson(route('ai-assistant.settings.test'), ['provider' => 'anthropic', 'model' => 'claude-sonnet-5-5'])
            ->assertStatus(429);
    });

    test('a queued reply does nothing if the user switched workspace before it ran', function () {
        [$workspace, $users] = aiWorkspace();
        [$other] = aiWorkspace();
        aiSettings($workspace);
        $fake = fakeAi([['tool' => 'list_projects', 'args' => []], ['text' => 'ok']]);
        WorkspaceMember::create(['workspace_id' => $other->id, 'user_id' => $users['owner']->id, 'role' => 'owner', 'status' => 'active']);

        $conversation = AiConversation::withoutGlobalScope('workspace')->create(['workspace_id' => $workspace->id, 'user_id' => $users['owner']->id]);
        $message = $conversation->messages()->create(['role' => 'user', 'content' => 'list my projects']);
        $users['owner']->update(['current_workspace_id' => $other->id]);

        (new \App\Jobs\ProcessAiMessage($users['owner']->id, $conversation->id, $message->id))->handle(app(\App\Services\Ai\AiAssistant::class));

        $reply = $conversation->messages()->reorder('id', 'desc')->first();
        expect($fake->requests)->toBeEmpty()
            ->and((bool) $reply->is_error)->toBeTrue()
            ->and($reply->content)->toContain('You switched workspace');
    });
});
