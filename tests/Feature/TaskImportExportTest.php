<?php
/**
 * Tasks page Export / Import and Contracts page Export.
 */

use App\Events\TaskAssigned;
use App\Events\TaskCreated;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\TaskStage;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

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

/** Owner, workspace, two stages and two projects. */
function tieSetup(): array
{
    $plan = Plan::where('is_default', true)->first();
    $owner = User::factory()->create(['type' => 'company', 'plan_id' => $plan?->id, 'plan_is_active' => 1]);
    $ws = Workspace::create(['name' => 'TIE', 'slug' => 'tie-' . uniqid(), 'owner_id' => $owner->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    $owner->update(['current_workspace_id' => $ws->id]);
    $owner->assignRole('company');

    // A new workspace gets the default stages (To Do … Done) from WorkspaceObserver.
    $alpha = tieProject($ws, $owner, 'Alpha');
    $beta = tieProject($ws, $owner, 'Beta');

    return [$owner, $ws, $alpha, $beta];
}

function tieProject(Workspace $ws, User $creator, string $title): Project
{
    return Project::create(['workspace_id' => $ws->id, 'title' => $title, 'status' => 'active', 'priority' => 'medium',
        'progress' => 0, 'created_by' => $creator->id, 'visibility' => 'private']);
}

function tieMember(Workspace $ws, string $role, array $attrs = []): User
{
    $user = User::factory()->create(['type' => 'company', 'current_workspace_id' => $ws->id] + $attrs);
    WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active']);
    $user->assignRole($role);

    return $user;
}

function tieTask(Project $project, User $creator, string $title, array $attrs = []): Task
{
    return Task::create(array_merge(['project_id' => $project->id, 'title' => $title, 'priority' => 'medium', 'progress' => 0,
        'created_by' => $creator->id, 'task_stage_id' => TaskStage::where('workspace_id', $project->workspace_id)->orderBy('order')->value('id')], $attrs));
}

function tieSheet(string $path): array
{
    return \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet()->toArray();
}

/** The import step after the file was read: rows in the session, mapping posted. */
function tieImport($test, User $user, array $headers, array $rows, array $mapping, array $extra = [])
{
    return $test->actingAs($user)
        ->withSession(['file_header' => $headers, 'file_data' => $rows, 'import_table' => 'tasks'])
        ->postJson(route('tasks.import'), ['data' => $mapping] + $extra);
}

/** Rows of an Excel download (Excel::download answers with a file). */
function tieDownloadRows($response): array
{
    return tieSheet($response->baseResponse->getFile()->getPathname());
}

// ═══ Export ═══

test('tasks export the page list: its filters, and only what a member may see', function () {
    [$owner, $ws, $alpha, $beta] = tieSetup();
    $member = tieMember($ws, 'member', ['email' => 'mia@acme.io']);
    tieTask($alpha, $owner, 'Wireframes', ['priority' => 'high', 'assigned_to' => $member->id, 'end_date' => '2026-11-03', 'story_points' => 0]);
    tieTask($alpha, $owner, 'Copy review', ['priority' => 'low']);
    tieTask($beta, $owner, 'API spec', ['priority' => 'high']);

    $rows = tieDownloadRows($this->actingAs($owner)->get(route('tasks.export', ['project_id' => $alpha->id, 'priority' => 'high']))->assertOk());
    expect($rows[0])->toContain('Project', 'Title', 'Assignee', 'Due Date', 'Progress %')
        ->and(count($rows))->toBe(2)
        ->and(array_slice($rows[1], 1, 3))->toBe(['Alpha', 'Wireframes', null])
        ->and($rows[1][8])->toBe('mia@acme.io')
        ->and($rows[1][10])->toBe('2026-11-03')
        ->and($rows[1][11])->not->toBeNull()->toEqual(0); // zero is kept, not left blank

    // A member only exports their own tasks (the Tasks page rule).
    ProjectMember::create(['project_id' => $alpha->id, 'user_id' => $member->id, 'role' => 'member', 'assigned_by' => $owner->id]);
    $rows = tieDownloadRows($this->actingAs($member)->get(route('tasks.export'))->assertOk());
    expect(array_column(array_slice($rows, 1), 2))->toBe(['Wireframes']);
});

test('contracts export the page list: its filters, and a client gets only their own', function () {
    [$owner, $ws, $alpha] = tieSetup();
    $client = tieMember($ws, 'client');
    $other = tieMember($ws, 'client');
    $type = ContractType::firstOrCreate(['workspace_id' => $ws->id, 'name' => 'MSA'], ['is_active' => true, 'created_by' => $owner->id]);
    $make = fn (string $subject, string $status, User $for) => Contract::create([
        'subject' => $subject, 'contract_type_id' => $type->id, 'contract_value' => 1200, 'start_date' => now(), 'end_date' => now()->addMonth(),
        'status' => $status, 'client_id' => $for->id, 'project_id' => $alpha->id, 'workspace_id' => $ws->id, 'created_by' => $owner->id,
    ]);
    $make('Signed MSA', 'accept', $client);
    $make('Draft SOW', 'pending', $client);
    $make('Other client MSA', 'accept', $other);

    $rows = tieDownloadRows($this->actingAs($owner)->get(route('contracts.export', ['status' => 'accept']))->assertOk());
    expect(array_column(array_slice($rows, 1), 1))->toEqualCanonicalizing(['Signed MSA', 'Other client MSA'])
        ->and($rows[1][5])->toBe('Alpha');

    $rows = tieDownloadRows($this->actingAs($client)->get(route('contracts.export'))->assertOk());
    expect(array_column(array_slice($rows, 1), 1))->toEqualCanonicalizing(['Signed MSA', 'Draft SOW']);
});

// ═══ Import ═══

test('the import recognises columns by their names', function () {
    [$owner] = tieSetup();

    $this->actingAs($owner)
        ->withSession(['file_header' => ['Task', 'Project', 'Deadline', 'Owner', 'Points', 'Notes about it'], 'file_data' => [], 'import_table' => 'tasks'])
        ->getJson(route('csv.import.modal', ['table' => 'tasks']))
        ->assertOk()
        ->assertJsonPath('suggested', ['project' => 1, 'title' => 0, 'assignee' => 3, 'due_date' => 2, 'story_points' => 4]);
});

test('the upload step reads a CSV for the task import', function () {
    [$owner] = tieSetup();
    $csv = UploadedFile::fake()->createWithContent('tasks.csv', "title,project\nWireframes,Alpha\n");

    $this->actingAs($owner)->post(route('csv.import'), ['file' => $csv, 'table' => 'tasks'], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('error', '')->assertJsonPath('fields.0', 'project');
});

test('importing tasks fills project, stage, people and dates, skips duplicates and notifies nobody', function () {
    Event::fake([TaskCreated::class, TaskAssigned::class]);
    [$owner, $ws, $alpha, $beta] = tieSetup();
    $dev = tieMember($ws, 'member', ['name' => 'Arjun Mehta', 'email' => 'arjun@acme.io']);
    ProjectMilestone::create(['project_id' => $alpha->id, 'title' => 'Launch', 'status' => 'pending', 'progress' => 0, 'created_by' => $owner->id]);
    tieTask($alpha, $owner, 'Already here');

    $headers = ['Project', 'Title', 'Priority', 'Stage', 'Assignee', 'Start', 'Due', 'Type', 'Milestone', 'Points'];
    $rows = [
        ['alpha', 'Design header', 'P1', 'done', 'arjun@acme.io', '2026-10-01', 46310, 'Task', 'Launch', '5'],
        ['Alpha', 'Checkout bug', 'low', '', 'Arjun Mehta', '', '', 'bug', '', ''],
        ['Alpha', 'already here', '', '', '', '', '', '', '', ''],          // in the project already
        ['Alpha', 'Design header', '', '', '', '', '', '', '', ''],         // repeated in the file
        ['Gamma', 'Lost row', '', '', '', '', '', '', '', ''],              // no such project
        ['Beta', '', '', '', '', '', '', '', '', ''],                       // no title
        ['Beta', 'Spec review', 'whenever', 'Someday', 'Nobody Known', '2026-12-01', '2026-11-01', '', 'Phase 9', 'many'],
        ['', '', '', '', '', '', '', '', '', ''],                           // blank, ignored
    ];
    $mapping = ['project' => 0, 'title' => 1, 'priority' => 2, 'stage' => 3, 'assignee' => 4, 'start_date' => 5, 'due_date' => 6, 'type' => 7, 'milestone' => 8, 'story_points' => 9];

    $response = tieImport($this, $owner, $headers, $rows, $mapping)->assertOk()
        ->assertJsonPath('data', ['imported_count' => 3, 'skipped_count' => 2, 'failed_count' => 2])
        ->assertJsonPath('message', '3 rows imported, 2 duplicates skipped, 2 rows failed');
    expect($response->json('errors'))->toBe(['Row 6: "Gamma" is not a project you can see', 'Row 7: No title'])
        ->and($response->json('warnings'))->toBe([
            'Row 8: The due date is before the start date; the start date is left empty',
            'Row 8: "whenever" is not a known priority; Medium is used',
            'Row 8: "Someday" is not a stage; the task starts in To Do',
            'Row 8: "Nobody Known" is not in this workspace; left unassigned',
            'Row 8: "many" is not a story point number (0-100); left empty',
            'Row 8: "Phase 9" is not a milestone of Beta; left empty',
        ]);

    $design = Task::where('title', 'Design header')->sole();
    expect($design->project_id)->toBe($alpha->id)
        ->and($design->priority)->toBe('critical')
        ->and($design->taskStage->name)->toBe('Done')
        ->and($design->assigned_to)->toBe($dev->id)
        ->and($design->start_date->toDateString())->toBe('2026-10-01')
        ->and($design->end_date->toDateString())->toBe('2026-10-15') // Excel day number 46310
        ->and($design->milestone->title)->toBe('Launch')
        ->and($design->story_points)->toBe(5)
        ->and($design->created_by)->toBe($owner->id);
    expect(Task::where('title', 'Checkout bug')->sole()->only(['type', 'severity', 'assigned_to']))
        ->toBe(['type' => 'bug', 'severity' => 'major', 'assigned_to' => $dev->id]);
    expect(Task::where('title', 'Spec review')->sole()->only(['project_id', 'priority', 'assigned_to', 'start_date']))
        ->toBe(['project_id' => $beta->id, 'priority' => 'medium', 'assigned_to' => null, 'start_date' => null]);

    Event::assertNotDispatched(TaskCreated::class);
    Event::assertNotDispatched(TaskAssigned::class);
});

test('rows without a project go into the project the Tasks page is filtered by', function () {
    [$owner, , $alpha] = tieSetup();

    tieImport($this, $owner, ['Title'], [['One'], ['Two']], ['title' => 0], ['project_id' => $alpha->id])
        ->assertOk()->assertJsonPath('data.imported_count', 2);
    expect(Task::where('project_id', $alpha->id)->pluck('title')->sort()->values()->all())->toBe(['One', 'Two']);

    tieImport($this, $owner, ['Title'], [['Three']], ['title' => 0])
        ->assertOk()->assertJsonPath('errors.0', 'Row 2: No project: add a Project column, or filter the Tasks page by a project before importing');
});

test('a member imports only into projects they can see, and cannot assign people', function () {
    [$owner, $ws, $alpha, $beta] = tieSetup();
    $member = tieMember($ws, 'member');
    ProjectMember::create(['project_id' => $alpha->id, 'user_id' => $member->id, 'role' => 'member', 'assigned_by' => $owner->id]);

    $response = tieImport($this, $member, ['Project', 'Title', 'Assignee'], [
        ['Alpha', 'Mine', $owner->email],
        ['Beta', 'Not mine', ''],
    ], ['project' => 0, 'title' => 1, 'assignee' => 2])->assertOk();

    expect($response->json('errors'))->toBe(['Row 3: "Beta" is not a project you can see'])
        ->and($response->json('warnings'))->toBe(['Row 2: You may not assign people; left unassigned'])
        ->and(Task::where('title', 'Mine')->sole()->assigned_to)->toBeNull()
        ->and(Task::where('title', 'Not mine')->exists())->toBeFalse();
});

test('a file uploaded for another import cannot be imported as tasks', function () {
    [$owner] = tieSetup();

    $this->actingAs($owner)
        ->withSession(['file_header' => ['name', 'email'], 'file_data' => [['Eve', 'eve@evil.test']], 'import_table' => 'companies'])
        ->postJson(route('tasks.import'), ['data' => ['name' => 0, 'email' => 1]])
        ->assertStatus(400);
    expect(User::where('email', 'eve@evil.test')->exists())->toBeFalse();
});

test('the platform admin can upload a file for the Companies import', function () {
    $admin = User::factory()->create(['type' => 'superadmin']);
    $admin->assignRole('superadmin');
    $csv = UploadedFile::fake()->createWithContent('companies.csv', "Name,Email\nAcme,owner@acme.example\n");

    $this->actingAs($admin)->post(route('csv.import'), ['file' => $csv, 'table' => 'companies'], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('error', '');
    $this->actingAs($admin)->getJson(route('csv.import.modal', ['table' => 'companies']))
        ->assertOk()->assertJsonPath('suggested', ['name' => 0, 'email' => 1]);
});

test('the task template lists the import columns with an example row', function () {
    [$owner] = tieSetup();

    $csv = $this->actingAs($owner)->get(route('tasks.template'))->assertOk()->streamedContent();
    $lines = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));
    expect($lines[0])->toBe(['project', 'title', 'description', 'type', 'priority', 'severity', 'stage', 'assignee', 'start_date', 'due_date', 'story_points', 'milestone'])
        ->and(count($lines))->toBe(2);
});
