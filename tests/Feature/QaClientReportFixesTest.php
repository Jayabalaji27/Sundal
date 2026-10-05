<?php
/**
 * Regression tests for the 2026-10-05 client-role QA report
 * (taskly-client-role-fixes.md), plus the client behaviour it verified as working.
 */

use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectClient;
use App\Models\ProjectMember;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TaskStage;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
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

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Owner + workspace with a manager, a member and two clients - the QA setup.
 * Invited accounts get type = 'company' in SaaS mode, so that's used here too.
 */
function cliWorkspace(): array
{
    $plan = Plan::where('is_default', true)->first();
    $owner = User::factory()->create(['type' => 'company', 'plan_id' => $plan?->id, 'plan_is_active' => 1]);
    $workspace = Workspace::create(['name' => 'QA WS', 'slug' => 'qa-' . uniqid(), 'owner_id' => $owner->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    $owner->update(['current_workspace_id' => $workspace->id]);
    $owner->assignRole('company');

    $users = [];
    foreach (['manager', 'member', 'client', 'client'] as $role) {
        $user = User::factory()->create(['type' => 'company', 'current_workspace_id' => $workspace->id]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active']);
        $user->assignRole($role);
        $users[] = $user;
    }

    // [$owner, $ws, $manager, $member, $client, $otherClient]
    return [$owner, $workspace, ...$users];
}

/** A private project with the given members and clients attached. */
function cliProject(Workspace $workspace, User $creator, array $members = [], array $clients = [], array $attrs = []): Project
{
    $project = Project::create(array_merge([
        'workspace_id' => $workspace->id, 'title' => 'P' . uniqid(), 'status' => 'active',
        'priority' => 'medium', 'progress' => 0, 'created_by' => $creator->id, 'visibility' => 'private',
    ], $attrs));
    foreach ($members as $user) {
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $user->id, 'role' => 'member', 'assigned_by' => $creator->id]);
    }
    foreach ($clients as $user) {
        ProjectClient::create(['project_id' => $project->id, 'user_id' => $user->id, 'assigned_by' => $creator->id]);
    }

    return $project;
}

function cliStage(Workspace $workspace, bool $completed = false): TaskStage
{
    return TaskStage::create(['workspace_id' => $workspace->id, 'name' => $completed ? 'Done' : 'To Do',
        'color' => '#000', 'order' => $completed ? 2 : 1, 'is_default' => !$completed, 'is_completed' => $completed]);
}

function cliTask(Project $project, TaskStage $stage, User $creator, array $attrs = []): Task
{
    return Task::create(array_merge([
        'project_id' => $project->id, 'task_stage_id' => $stage->id, 'title' => 'T' . uniqid(),
        'priority' => 'medium', 'progress' => 0, 'created_by' => $creator->id,
        'end_date' => now()->addDays(3)->toDateString(),
    ], $attrs));
}

function cliContract(Workspace $workspace, User $creator, ?User $client, string $status = 'sent'): Contract
{
    $type = ContractType::firstOrCreate(['workspace_id' => $workspace->id, 'name' => 'Std'], ['is_active' => true, 'created_by' => $creator->id]);

    return Contract::create([
        'subject' => 'C' . uniqid(), 'contract_type_id' => $type->id, 'contract_value' => 10, 'start_date' => now(),
        'end_date' => now()->addMonth(), 'status' => $status, 'client_id' => $client?->id,
        'workspace_id' => $workspace->id, 'created_by' => $creator->id,
    ]);
}

function cliInvoice(Project $project, User $creator, array $attrs = []): Invoice
{
    return Invoice::create(array_merge([
        'project_id' => $project->id, 'workspace_id' => $project->workspace_id, 'created_by' => $creator->id,
        'title' => 'Inv', 'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(10)->toDateString(),
        'total_amount' => 100, 'subtotal' => 100, 'status' => 'sent', 'tax_rate' => [],
    ], $attrs));
}

/** Value of a dashboard card by title. */
function cliCard(array $cards, string $title)
{
    return collect($cards)->firstWhere('title', $title)['value'] ?? null;
}

// ═══ #1 Calendar API only returns tasks the user can see ═══

describe('#1 calendar API', function () {
    test('client only gets tasks from their projects that share tasks, with a minimal assignee', function () {
        [$owner, $ws, $manager, $member, $client] = cliWorkspace();
        $stage = cliStage($ws);
        $shared = cliProject($ws, $owner, [], [$client], ['shared_settings' => ['task' => true]]);
        $notShared = cliProject($ws, $owner, [], [$client]);
        $other = cliProject($ws, $owner, [$member]);
        $visible = cliTask($shared, $stage, $owner, ['assigned_to' => $manager->id]);
        cliTask($notShared, $stage, $owner);
        cliTask($other, $stage, $owner, ['assigned_to' => $member->id]);

        $response = $this->actingAs($client)->getJson(route('api.tasks.calendar'))->assertOk();

        expect(collect($response->json('tasks'))->pluck('id')->all())->toBe([$visible->id])
            ->and(array_keys($response->json('tasks.0.assigned_to')))->toEqualCanonicalizing(['id', 'name', 'avatar']);
        $response->assertJsonMissing(['project_id' => $other->id]);
    });

    test('member only gets their own tasks on projects they belong to; owner gets everything', function () {
        [$owner, $ws, $manager, $member] = cliWorkspace();
        $stage = cliStage($ws);
        $theirs = cliProject($ws, $owner, [$member]);
        $other = cliProject($ws, $owner);
        $own = cliTask($theirs, $stage, $owner, ['assigned_to' => $member->id]);
        cliTask($theirs, $stage, $owner, ['assigned_to' => $manager->id]);
        cliTask($other, $stage, $owner, ['assigned_to' => $member->id]);

        $ids = fn (User $u) => collect($this->actingAs($u)->getJson(route('api.tasks.calendar'))->assertOk()->json('tasks'))->pluck('id')->all();

        expect($ids($member))->toBe([$own->id])
            ->and($ids($owner))->toHaveCount(3);
    });

    test('task detail endpoint returns only minimal user fields', function () {
        [$owner, $ws, $manager, , $client] = cliWorkspace();
        $project = cliProject($ws, $owner, [], [$client], ['shared_settings' => ['task' => true]]);
        $task = cliTask($project, cliStage($ws), $owner, ['assigned_to' => $manager->id]);

        $json = $this->actingAs($client)->getJson(route('api.task-calendar.task', $task))->assertOk()->json('task');

        expect(array_keys($json['assigned_to']))->toEqualCanonicalizing(['id', 'name', 'avatar'])
            ->and(array_keys($json['creator']))->toEqualCanonicalizing(['id', 'name', 'avatar']);
    });
});

// ═══ #2 / #3 Contracts are scoped to the client ═══

describe('#2 contracts', function () {
    test('client list only shows contracts assigned to them, with minimal user fields', function () {
        [$owner, $ws, $manager, , $client, $otherClient] = cliWorkspace();
        $own = cliContract($ws, $manager, $client);
        cliContract($ws, $manager, null);           // unassigned
        cliContract($ws, $manager, $otherClient);   // someone else's

        $this->actingAs($client)->get(route('contracts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('contracts.data', 1)
                ->where('contracts.data.0.id', $own->id)
                ->has('contracts.data.0.creator', fn (Assert $c) => $c->hasAll(['id', 'name', 'avatar']))
                ->has('clients', 1)
                ->where('clients.0.id', $client->id));
    });

    test('owner and manager still see every contract', function () {
        [$owner, $ws, $manager, , $client, $otherClient] = cliWorkspace();
        cliContract($ws, $manager, $client);
        cliContract($ws, $manager, null);
        cliContract($ws, $manager, $otherClient);

        foreach ([$owner, $manager] as $user) {
            $this->actingAs($user)->get(route('contracts.index'))
                ->assertInertia(fn (Assert $page) => $page->has('contracts.data', 3));
        }
    });

    test('client cannot open, comment on or download from a contract that is not theirs', function () {
        [$owner, $ws, $manager, , $client, $otherClient] = cliWorkspace();
        $own = cliContract($ws, $manager, $client);

        foreach ([cliContract($ws, $manager, null), cliContract($ws, $manager, $otherClient)] as $contract) {
            $this->actingAs($client)->get(route('contracts.show', $contract))->assertNotFound();
            $this->actingAs($client)->post(route('contract-comments.store', $contract), ['comment' => 'hi'])->assertNotFound();
            $attachment = \App\Models\ContractAttachment::create(['contract_id' => $contract->id, 'files' => 'x.pdf', 'workspace_id' => $ws->id]);
            $this->actingAs($client)->get(route('contract-attachments.download', $attachment))->assertNotFound();
        }

        $this->actingAs($client)->get(route('contracts.show', $own))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('contract.id', $own->id)
                ->has('contract.creator', fn (Assert $c) => $c->hasAll(['id', 'name', 'avatar']))
                ->has('contract.client', fn (Assert $c) => $c->hasAll(['id', 'name', 'email', 'avatar'])));
    });

    test('client never receives internal notes or internal comments; the team still does', function () {
        [$owner, $ws, $manager, , $client] = cliWorkspace();
        $contract = cliContract($ws, $manager, $client);
        \App\Models\ContractNote::create(['contract_id' => $contract->id, 'note' => 'Internal: client is slow to pay', 'is_pinned' => false, 'created_by' => $manager->id]);
        \App\Models\ContractComment::create(['contract_id' => $contract->id, 'comment' => 'Public', 'is_internal' => false, 'created_by' => $manager->id]);
        \App\Models\ContractComment::create(['contract_id' => $contract->id, 'comment' => 'Team only', 'is_internal' => true, 'created_by' => $manager->id]);

        $this->actingAs($client)->get(route('contracts.show', $contract))
            ->assertInertia(fn (Assert $page) => $page
                ->has('contract.notes', 0)
                ->where('permissions.viewNotes', false)
                ->has('contract.comments', 1)
                ->where('contract.comments.0.comment', 'Public'));
        $this->actingAs($client)->get(route('contracts.index'))
            ->assertInertia(fn (Assert $page) => $page->missing('contracts.data.0.notes_count'));

        $this->actingAs($owner)->get(route('contracts.show', $contract))
            ->assertInertia(fn (Assert $page) => $page
                ->has('contract.notes', 1)
                ->where('permissions.viewNotes', true)
                ->has('contract.comments', 2));
    });

    test('#3 client can accept their own sent contract, but not change someone else\'s', function () {
        [$owner, $ws, $manager, , $client, $otherClient] = cliWorkspace();
        $own = cliContract($ws, $manager, $client, 'sent');
        $theirs = cliContract($ws, $manager, $otherClient, 'sent');
        $unassigned = cliContract($ws, $manager, null, 'pending');

        $this->actingAs($client)->put(route('contracts.change-status', $theirs), ['status' => 'accept'])->assertForbidden();
        $this->actingAs($client)->put(route('contracts.change-status', $unassigned), ['status' => 'accept'])->assertForbidden();
        $this->actingAs($client)->put(route('contracts.change-status', $own), ['status' => 'sent'])->assertSessionHas('warning');
        $this->actingAs($client)->put(route('contracts.change-status', $own), ['status' => 'pending'])->assertSessionHas('error');

        $this->actingAs($client)->put(route('contracts.change-status', $own), ['status' => 'accept'])
            ->assertRedirect()
            ->assertSessionHas('success');

        expect($own->fresh()->status)->toBe('accept')
            ->and($theirs->fresh()->status)->toBe('sent')
            ->and($unassigned->fresh()->status)->toBe('pending');
    });
});

// ═══ #4 Calendar page shows the client's shared tasks ═══

describe('#4 calendar page', function () {
    test('client sees tasks from their projects that share tasks, and nothing else', function () {
        [$owner, $ws, , $member, $client] = cliWorkspace();
        $stage = cliStage($ws);
        $shared = cliProject($ws, $owner, [], [$client], ['shared_settings' => ['task' => true]]);
        $notShared = cliProject($ws, $owner, [], [$client]);
        cliTask($shared, $stage, $owner, ['title' => 'Mine']);
        cliTask($notShared, $stage, $owner, ['title' => 'Internal']);
        cliTask(cliProject($ws, $owner, [$member]), $stage, $owner, ['title' => 'Other project']);

        $this->actingAs($client)->get(route('task-calendar.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('calendar/index')
                ->has('events', 1)
                ->where('events.0.title', 'Mine'));
    });
});

// ═══ #5 Less data sent to the frontend ═══

describe('#5 data exposure', function () {
    test('project reports user filter only lists people on the client\'s projects, with id and name', function () {
        [$owner, $ws, $manager, $member, $client, $otherClient] = cliWorkspace();
        cliProject($ws, $owner, [$manager], [$client]);
        cliProject($ws, $owner, [$member], [$otherClient]);

        $this->actingAs($client)->get(route('project-reports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('users', fn ($users) => collect($users)->pluck('id')->sort()->values()->all()
                    === collect([$manager->id, $client->id])->sort()->values()->all())
                ->has('users.0', fn (Assert $u) => $u->hasAll(['id', 'name']))
                ->has('projects.data', 1));

        // The owner keeps the full workspace list for the filter.
        $this->actingAs($owner)->get(route('project-reports.index'))
            ->assertInertia(fn (Assert $page) => $page->where('users', fn ($users) => collect($users)->pluck('id')
                ->intersect([$manager->id, $member->id, $client->id, $otherClient->id])->count() === 4));
    });

    test('project report page only lists the project\'s people and no full user rows', function () {
        [$owner, $ws, $manager, $member, $client] = cliWorkspace();
        $project = cliProject($ws, $owner, [$manager], [$client]);
        cliTask($project, cliStage($ws), $owner, ['assigned_to' => $manager->id]);

        $this->actingAs($client)->get(route('project-reports.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('users', fn ($users) => collect($users)->pluck('user_id')->sort()->values()->all()
                    === collect([$manager->id, $client->id])->sort()->values()->all())
                ->has('workspace', fn (Assert $w) => $w->hasAll(['id', 'name']))
                ->has('project.clients.0', fn (Assert $c) => $c->hasAll(['id', 'name', 'avatar'])->etc())
                ->has('tasks.data.0.assigned_users', 1));
    });

    test('mail, storage and API credentials are not shared with non-owners', function () {
        [$owner, $ws, , , $client] = cliWorkspace();
        foreach (['email_host' => 'smtp.real.test', 'email_username' => 'u', 'email_port' => '587', 'email_driver' => 'smtp',
            'email_encryption' => 'tls', 'aws_secret_access_key' => 's', 'wasabi_secret_key' => 's', 'chatgptKey' => 'sk-x',
            'dateFormat' => 'd/m/Y'] as $key => $value) {
            Setting::create(['user_id' => $owner->id, 'workspace_id' => $ws->id, 'key' => $key, 'value' => $value]);
        }

        $this->actingAs($client)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('globalSettings.email_host')
                ->missing('globalSettings.email_username')
                ->missing('globalSettings.email_port')
                ->missing('globalSettings.email_driver')
                ->missing('globalSettings.email_encryption')
                ->missing('globalSettings.aws_secret_access_key')
                ->missing('globalSettings.wasabi_secret_key')
                ->missing('globalSettings.chatgptKey')
                ->where('globalSettings.chatgptKeySet', true)
                ->where('globalSettings.dateFormat', 'd/m/Y'));

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('globalSettings.email_host', 'smtp.real.test'));
    });

    test('client invoice page and list carry no full user rows or other clients', function () {
        [$owner, $ws, , , $client, $otherClient] = cliWorkspace();
        $invoice = cliInvoice(cliProject($ws, $owner, [], [$client]), $owner, ['client_id' => $client->id]);

        $this->actingAs($client)->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('invoice.client', fn (Assert $c) => $c->hasAll(['id', 'name', 'email', 'avatar']))
                ->has('invoice.creator', fn (Assert $c) => $c->hasAll(['id', 'name', 'email'])));

        $this->actingAs($client)->get(route('invoices.index'))
            ->assertInertia(fn (Assert $page) => $page->has('clients', 1)->where('clients.0.id', $client->id));
    });
});

// ═══ #6 Misc ═══

describe('#6 misc', function () {
    test('ChatGPT page and generator are closed to clients, open to the rest of the team', function () {
        [$owner, $ws, $manager, $member, $client] = cliWorkspace();

        $this->actingAs($client)->get(route('chatgpt'))->assertForbidden();
        $this->actingAs($client)->postJson(route('chatgpt.generate'), ['prompt' => 'hi'])->assertForbidden();
        $this->actingAs($client)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.permissions', fn ($p) => !collect($p)->contains('agent_use')));

        foreach ([$owner, $manager, $member] as $user) {
            $this->actingAs($user)->get(route('chatgpt'))->assertOk();
        }
    });

    test('the broken invoice-preview route is gone', function () {
        $this->get('/invoice-preview')->assertNotFound();
    });

    test('client cannot create, change or delete portfolios, and only sees their projects inside one', function () {
        [$owner, $ws, $manager, , $client] = cliWorkspace();
        $portfolio = Portfolio::create(['workspace_id' => $ws->id, 'name' => 'Pf', 'color' => '#000', 'created_by' => $owner->id]);
        $mine = cliProject($ws, $owner, [], [$client]);
        $notMine = cliProject($ws, $owner);
        Project::whereKey([$mine->id, $notMine->id])->update(['portfolio_id' => $portfolio->id]);

        $this->actingAs($client)->post(route('portfolios.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($client)->put(route('portfolios.update', $portfolio), ['name' => 'X'])->assertForbidden();
        $this->actingAs($client)->delete(route('portfolios.destroy', $portfolio))->assertForbidden();
        expect(Portfolio::count())->toBe(1)->and($portfolio->fresh()->name)->toBe('Pf');

        $this->actingAs($client)->get(route('portfolios.show', $portfolio))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('projects', 1)->where('projects.0.id', $mine->id));

        // Managers can still manage portfolios.
        $this->actingAs($manager)->post(route('portfolios.store'), ['name' => 'Managed'])->assertRedirect();
        expect(Portfolio::count())->toBe(2);
    });

    test('upcoming deadlines counts open tasks the client can see on the calendar', function () {
        [$owner, $ws, , $member, $client] = cliWorkspace();
        $open = cliStage($ws);
        $done = cliStage($ws, true);
        $shared = cliProject($ws, $owner, [], [$client], ['shared_settings' => ['task' => true]]);
        cliTask($shared, $open, $owner);                                   // counted
        cliTask($shared, $done, $owner, ['progress' => 100]);               // completed
        cliTask($shared, $open, $owner, ['end_date' => now()->addDays(20)->toDateString()]); // outside 7 days
        cliTask(cliProject($ws, $owner, [], [$client]), $open, $owner);     // project doesn't share tasks
        cliTask(cliProject($ws, $owner, [$member]), $open, $owner);         // not the client's project

        $this->actingAs($client)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboardData.cards', fn ($cards) => cliCard(collect($cards)->all(), 'Upcoming Deadlines') === 1));
    });
});

// ═══ Verified working - keep it that way ═══

describe('client regressions', function () {
    test('dashboard counts only the client\'s projects', function () {
        [$owner, $ws, , $member, $client] = cliWorkspace();
        cliProject($ws, $owner, [], [$client]);
        cliProject($ws, $owner, [], [$client]);
        cliProject($ws, $owner, [$member]);

        $this->actingAs($client)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboardData.cards', fn ($cards) => cliCard(collect($cards)->all(), 'Active Projects') === 2));
    });

    test('projects: list shows only the client\'s projects, others and create are not found', function () {
        [$owner, $ws, , $member, $client] = cliWorkspace();
        $a = cliProject($ws, $owner, [], [$client]);
        $b = cliProject($ws, $owner, [], [$client]);
        $other = cliProject($ws, $owner, [$member]);

        $this->actingAs($client)->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('projects.data', fn ($data) => collect($data)->pluck('id')->sort()->values()->all()
                    === collect([$a->id, $b->id])->sort()->values()->all()));

        $this->actingAs($client)->get(route('projects.show', $a))->assertOk();
        $this->actingAs($client)->get(route('projects.show', $other))->assertNotFound();
        $this->actingAs($client)->get('/projects/create')->assertNotFound();
    });

    test('invoices: only the client\'s sent invoices, view only', function () {
        [$owner, $ws, , , $client, $otherClient] = cliWorkspace();
        $project = cliProject($ws, $owner, [], [$client, $otherClient]);
        $one = cliInvoice($project, $owner, ['client_id' => $client->id, 'status' => 'paid']);
        $two = cliInvoice($project, $owner, ['client_id' => $client->id, 'status' => 'paid']);
        $theirs = cliInvoice($project, $owner, ['client_id' => $otherClient->id]);
        $draft = cliInvoice($project, $owner, ['client_id' => $client->id, 'status' => 'draft']);

        $this->actingAs($client)->get(route('invoices.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoices.data', fn ($data) => collect($data)->pluck('id')->sort()->values()->all()
                    === collect([$one->id, $two->id])->sort()->values()->all()));

        $this->actingAs($client)->get(route('invoices.show', $one))->assertOk();
        $this->actingAs($client)->get(route('invoices.show', $theirs))->assertForbidden();
        $this->actingAs($client)->get(route('invoices.show', $draft))->assertForbidden();
        $this->actingAs($client)->get(route('invoices.create'))->assertForbidden();
    });

    test('project reports: only the client\'s projects', function () {
        [$owner, $ws, , $member, $client] = cliWorkspace();
        $mine = cliProject($ws, $owner, [], [$client]);
        $other = cliProject($ws, $owner, [$member]);

        $this->actingAs($client)->get(route('project-reports.index'))
            ->assertInertia(fn (Assert $page) => $page->has('projects.data', 1)->where('projects.data.0.id', $mine->id));
        $this->actingAs($client)->get(route('project-reports.show', $mine))->assertOk();
        $this->actingAs($client)->get(route('project-reports.show', $other))->assertForbidden();
    });

    test('profile: name update works and a wrong current password is rejected', function () {
        [, , , , $client] = cliWorkspace();

        $this->actingAs($client)->patch(route('profile.update'), ['name' => 'Renamed Client', 'email' => $client->email])
            ->assertSessionHasNoErrors();
        expect($client->fresh()->name)->toBe('Renamed Client');

        $this->actingAs($client)->from('/settings/password')->put(route('password.update'), [
            'current_password' => 'wrong-password', 'password' => 'new-password', 'password_confirmation' => 'new-password',
        ])->assertSessionHasErrors(['current_password' => 'The password is incorrect.']);
        expect(Hash::check('new-password', $client->fresh()->password))->toBeFalse();
    });

    test('blocked modules return 403', function () {
        [$owner, $ws, , , $client] = cliWorkspace();
        $project = cliProject($ws, $owner, [], [$client]);
        $task = cliTask($project, cliStage($ws), $owner);
        $budget = ProjectBudget::create(['project_id' => $project->id, 'workspace_id' => $ws->id, 'total_budget' => 1000,
            'period_type' => 'monthly', 'start_date' => now()->startOfMonth(), 'end_date' => now()->endOfMonth(), 'created_by' => $owner->id]);

        foreach ([
            route('tasks.index'), route('tasks.show', $task), route('bugs.index'),
            route('budgets.index'), route('budgets.show', $budget), route('expenses.index'),
            route('team.index', $ws), route('settings'), route('settings.email'),
        ] as $url) {
            $this->actingAs($client)->get($url)->assertForbidden();
        }
    });
});
