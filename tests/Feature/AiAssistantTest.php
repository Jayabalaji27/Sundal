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

    test('without the AI add-on the owner sees the upgrade page and the manager is sent to the dashboard', function () {
        [$workspace, $users] = aiWorkspace(withAddon: false);
        aiSettings($workspace);

        $this->actingAs($users['owner'])->get(route('ai-assistant.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('access', 'plan')->where('settings', null)->where('conversations', []));
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

    test('the owner gets every tool', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $fake = fakeAi([['text' => 'ok']]);

        $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertOk();

        expect(toolNames($fake))->toBe(collect(app(\App\Services\Ai\ToolRegistry::class)->all())->keys()->sort()->values()->all())
            ->and(toolNames($fake))->toHaveCount(27);
    });

    test('a manager gets the manager workflow tools their role allows', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        $fake = fakeAi([['text' => 'ok']]);

        $this->actingAs($users['manager'])->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertOk();

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

        $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertOk();

        $registry = app(\App\Services\Ai\ToolRegistry::class);
        expect(toolNames($fake))->toContain('list_projects', 'get_project_report', 'search_knowledge_base')
            ->and(collect(toolNames($fake))->filter(fn ($name) => $registry->find($name)->isWrite()))->toBeEmpty();
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
        $card = $this->actingAs($users['manager'])->postJson(route('ai-assistant.send'), ['content' => 'create a to do task for a login page'])
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
        $ready = $this->actingAs($users['manager'])->postJson(route('ai-assistant.tool-calls.update', $card['id']), ['fields' => ['project' => (string) $mobile->id]])
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
        $first = $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'create a task for a login page']);
        [$cardId, $chat] = [$first->json('messages.1.cards.0.id'), $first->json('conversation.id')];

        $reply = $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['conversation_id' => $chat, 'content' => 'mobile app, make it high'])
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
        $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['conversation_id' => $chat, 'content' => 'assign it to me'])->assertOk();
        expect($fake->requests)->toHaveCount(1)
            ->and(AiToolCall::withoutGlobalScope('workspace')->find($cardId)->payload['form']['fields'][3]['value'])->toBe((string) $users['owner']->id);
    });

    test('a reply that is a new question goes to the AI, and a repeated tool call updates the same draft', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        aiProject($workspace, $users['owner']);
        $mobile = aiProject($workspace, $users['owner'], 'Mobile App');

        fakeAi([['tool' => 'create_task', 'args' => ['title' => 'Login page']], ['text' => 'Which project?']]);
        $first = $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'create a task for a login page']);
        [$cardId, $chat] = [$first->json('messages.1.cards.0.id'), $first->json('conversation.id')];

        // Not a plain answer: the AI handles it, calls the tool again, and the draft is updated in place.
        $fake = fakeAi([['tool' => 'create_task', 'args' => ['project' => 'Mobile App']], ['text' => 'Done, please confirm.']]);
        $reply = $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['conversation_id' => $chat, 'content' => 'put it under the project for our mobile app please, the one we started last week'])
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
        $card = $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'add a high priority task Checkout story for me'])
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
        $cards = $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'move API docs to Done and assign the login button bug to me'])->json('messages.1.cards');
        foreach ($cards as $card) {
            $this->actingAs($users['owner'])->postJson(route('ai-assistant.tool-calls.confirm', $card['id']))->assertJsonPath('card.status', 'done');
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
        $response = $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'assign the login bug fix to Ravi'])->assertOk();
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
        $card = $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'assign the secret task to me'])->json('messages.1.cards.0');
        $task = collect($card['fields'])->firstWhere('name', 'task');

        expect($fake->toolResults[0]['result'])->not->toContain('Secret task')
            ->and($task['value'])->toBeNull()
            ->and($task['note'])->toContain('Nothing matches')
            ->and(json_encode($task['options']))->not->toContain('Secret');

        // Even a hand-made request with the other workspace's id is refused.
        $secretId = Task::where('title', 'Secret task')->value('id');
        aiConfirm($this, $users['owner'], $card['id'])->assertJsonPath('card.status', 'pending');
        $this->actingAs($users['owner'])->postJson(route('ai-assistant.tool-calls.confirm', $card['id']), ['fields' => ['task' => (string) $secretId]])
            ->assertJsonPath('card.status', 'pending')
            ->assertJsonPath('card.fields.0.error', 'That task is no longer available; choose again.');
        expect(Task::find($secretId)->assigned_to)->toBeNull();
    });

    test('only the user who got the card can confirm it, and only once', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        aiTask(aiProject($workspace, $users['owner']), $users['owner'], 'API docs');
        fakeAi([['tool' => 'change_task_status', 'args' => ['task' => 'API docs', 'stage' => 'Done']], ['text' => 'ok']]);
        $cardId = $this->actingAs($users['manager'])->postJson(route('ai-assistant.send'), ['content' => 'move API docs to done'])->json('messages.1.cards.0.id');

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

// ── Phase 2 and 3 tools ───────────────────────────────────────────────────────

/** Ask the fake model to call one tool; return the resulting card and the tool's reply. */
function aiCall($test, User $user, string $tool, array $args): array
{
    $fake = fakeAi([['tool' => $tool, 'args' => $args], ['text' => 'ok']]);
    // The user's message names every value, so the form's "did they say it" check keeps them.
    $said = 'go ' . implode(' ', array_map(fn ($v) => is_array($v) ? implode(' ', $v) : (string) $v, $args));
    $response = $test->actingAs($user)->postJson(route('ai-assistant.send'), ['content' => $said])->assertOk();

    return ['card' => $response->json('messages.1.cards.0'), 'result' => $fake->toolResults[0]['result'] ?? null];
}

/** The JSON a read tool handed to the model. */
function aiData(array $call): array
{
    return json_decode(\Illuminate\Support\Str::after($call['result'], "\n"), true);
}

function aiConfirm($test, User $user, int $cardId, ?string $phrase = null)
{
    return $test->actingAs($user)->postJson(route('ai-assistant.tool-calls.confirm', $cardId), array_filter(['phrase' => $phrase]));
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
        $this->actingAs($users['manager'])->postJson(route('ai-assistant.tool-calls.confirm', $card['id']), ['fields' => ['priority' => 'high', 'assignee' => 'none']])
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

        $this->actingAs($users['owner'])->postJson(route('ai-assistant.tool-calls.undo', $card['id']))->assertJsonPath('card.status', 'undone');
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
        $this->actingAs($users['owner'])->postJson(route('ai-assistant.tool-calls.undo', $card['id']))->assertOk();
        expect($task->fresh()->assigned_to)->toBe($users['member']->id);

        $second = aiCall($this, $users['owner'], 'assign_task', ['task' => 'API docs', 'assignee' => 'me'])['card'];
        aiConfirm($this, $users['owner'], $second['id']);
        $task->update(['assigned_to' => $users['manager']->id]);
        $this->actingAs($users['owner'])->postJson(route('ai-assistant.tool-calls.undo', $second['id']))->assertStatus(422);
        expect($task->fresh()->assigned_to)->toBe($users['manager']->id);

        $third = aiCall($this, $users['owner'], 'assign_task', ['task' => 'API docs', 'assignee' => 'me'])['card'];
        aiConfirm($this, $users['owner'], $third['id']);
        AiToolCall::withoutGlobalScope('workspace')->whereKey($third['id'])->update(['confirmed_at' => now()->subMinutes(11)]);
        $this->actingAs($users['owner'])->postJson(route('ai-assistant.tool-calls.undo', $third['id']))->assertStatus(422);
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

        $this->actingAs($users['owner'])->get(route('ai-assistant.index'))
            ->assertInertia(fn (Assert $page) => $page->has('usage.daily', 30)->where('usage.daily.29.tokens', 320)->where('usage.tokens_this_month', 320));
    });

    test('crossing 80% of the cap triggers the owner warning once a month', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace, ['monthly_token_cap' => 1000]);
        AiUsage::withoutGlobalScope('workspace')->create(['workspace_id' => $workspace->id, 'user_id' => $users['owner']->id,
            'provider' => 'anthropic', 'model' => 'claude-sonnet-5-5', 'input_tokens' => 800, 'output_tokens' => 0]);
        fakeAi([['text' => 'ok']]);

        $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertOk();

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

        $response = $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertStatus(502);
        expect($response->json('messages.1.error'))->toBeTrue()->and($response->json('messages.1.content'))->toContain('no credits');

        $fake = fakeAi([['text' => 'hello again']]);
        $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['conversation_id' => $response->json('conversation.id'), 'content' => 'hi again'])->assertOk();
        expect(collect($fake->requests[0]->messages)->pluck('content')->all())->toBe(['hi', 'hi again']);
    });

    test('queue mode answers in a job running as the sender', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        aiTask(aiProject($workspace, $users['owner']), $users['owner'], 'API docs');
        config(['ai_assistant.queue' => true]);
        \Illuminate\Support\Facades\Queue::fake();

        $response = $this->actingAs($users['manager'])->postJson(route('ai-assistant.send'), ['content' => 'move API docs to Done'])
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

        $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'hi'])->assertOk();

        $createTask = collect($fake->requests[0]->tools)->firstWhere('name', 'create_task');
        expect(collect($createTask->parameters)->pluck('required')->unique()->all())->toBe([false]);
    });

    test('the topic buttons offered match the tools the user may use', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);

        $this->actingAs($users['owner'])->get(route('ai-assistant.index'))
            ->assertInertia(fn (Assert $page) => $page->where('topics', collect(\App\Services\Ai\Topics::keys())
                ->map(fn ($key) => ['key' => $key, 'label' => \App\Services\Ai\Topics::label($key)])->all()));
    });

    test('a topic narrows the tools, stays on the conversation, and is cleared with an empty value', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);

        $fake = fakeAi([['text' => 'ok']]);
        $conversationId = $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'hi', 'topic' => 'tasks'])
            ->assertJsonPath('conversation.topic', 'tasks')
            ->json('conversation.id');

        $expected = collect(\App\Services\Ai\Topics::toolNames('tasks'))->sort()->values()->all();
        expect(toolNames($fake))->toBe($expected)
            ->and($fake->requests[0]->system)->toContain('picked the "Tasks" topic');

        // Next message without a topic value: the topic is still on.
        $fake = fakeAi([['text' => 'ok']]);
        $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['conversation_id' => $conversationId, 'content' => 'login page for the website'])->assertOk();
        expect(toolNames($fake))->toBe($expected);

        // Removed: every tool again.
        $fake = fakeAi([['text' => 'ok']]);
        $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['conversation_id' => $conversationId, 'content' => 'hi', 'topic' => ''])
            ->assertJsonPath('conversation.topic', null);
        expect(toolNames($fake))->toHaveCount(count(app(\App\Services\Ai\ToolRegistry::class)->all()));
    });

    test('a message clearly about another topic gets every tool, in the same single AI call', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);

        $fake = fakeAi([['text' => 'ok']]);
        $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'approve the pending timesheets', 'topic' => 'tasks'])->assertOk();

        expect($fake->requests)->toHaveCount(1)
            ->and(toolNames($fake))->toContain('decide_timesheets', 'create_task')
            ->and($fake->requests[0]->system)->not->toContain('picked the');
    });

    test('a topic never adds tools the user may not use', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);

        $fake = fakeAi([['text' => 'ok']]);
        $this->actingAs($users['manager'])->postJson(route('ai-assistant.send'), ['content' => 'hi', 'topic' => 'finance'])->assertOk();

        foreach ($fake->requests[0]->tools as $spec) {
            expect(app(\App\Services\Ai\ToolRegistry::class)->find($spec->name)->allowedFor($users['manager']))->toBeTrue();
        }
        $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'hi', 'topic' => 'nonsense'])->assertJsonValidationErrors('topic');
    });

    test('with the AI down, a bare message under a topic still starts that topic\'s draft', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        aiProject($workspace, $users['owner']);
        fakeAi([fn () => throw new \App\Services\Ai\AiProviderException('Your AI provider could not be reached.')]);

        $error = collect($this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'Checkout page', 'topic' => 'tasks'])
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

        $messages = $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'create a to do task for a login page'])
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

        $reply = $this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'report a bug: checkout crashes'])
            ->assertOk()
            ->json('messages.1');

        expect($reply['content'])->toBe('Which project should it go in?')
            ->and($reply['cards'][0]['summary'])->toBe('Report a bug');
    });

    test('a read question gets no form', function () {
        [$workspace, $users] = aiWorkspace();
        aiSettings($workspace);
        fakeAi([['text' => 'None are overdue.']]);

        expect($this->actingAs($users['owner'])->postJson(route('ai-assistant.send'), ['content' => 'which tasks are overdue?'])->json('messages.1.cards'))
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
        ['assign the login bug to Ravi', 'assign_bug', null],
        ['move API docs to done', 'change_task_status', null],
        ['which tasks are overdue?', null, null],
        ['show tasks created this week', null, null],
    ]);
});
