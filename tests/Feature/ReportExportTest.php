<?php
/**
 * Reports: Customer Report generation, PDF / Excel / Word downloads and
 * emailing a report to yourself or another address.
 */

use App\Mail\ReportMail;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectMember;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TaskStage;
use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

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

/** Owner + workspace with one project that has a completed task, a budget and a week of logged time. */
function rxSetup(): array
{
    $plan = Plan::where('is_default', true)->first();
    $owner = User::factory()->create(['type' => 'company', 'plan_id' => $plan?->id, 'plan_is_active' => 1, 'email' => 'owner@acme.io']);
    $workspace = Workspace::create(['name' => 'Acme', 'slug' => 'acme-' . uniqid(), 'owner_id' => $owner->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    $owner->update(['current_workspace_id' => $workspace->id]);
    $owner->assignRole('company');

    $project = rxProject($workspace, $owner, ['title' => 'Storefront & Checkout', 'budget' => 48000]);
    $shipped = TaskStage::create(['workspace_id' => $workspace->id, 'name' => 'Shipped', 'color' => '#10B77F', 'order' => 1, 'is_completed' => true]);
    $todo = TaskStage::create(['workspace_id' => $workspace->id, 'name' => 'To Do', 'color' => '#6b7280', 'order' => 0]);
    $task = Task::create(['project_id' => $project->id, 'task_stage_id' => $shipped->id, 'title' => 'Cart & payments', 'priority' => 'high', 'created_by' => $owner->id, 'progress' => 100]);
    Task::create(['project_id' => $project->id, 'task_stage_id' => $todo->id, 'title' => 'Order emails', 'priority' => 'low', 'created_by' => $owner->id, 'progress' => 0]);
    ProjectBudget::create(['project_id' => $project->id, 'workspace_id' => $workspace->id, 'total_budget' => 12000, 'period_type' => 'project',
        'start_date' => now()->subMonth()->toDateString(), 'end_date' => now()->addMonth()->toDateString(), 'status' => 'active', 'created_by' => $owner->id]);

    $timesheet = Timesheet::create(['user_id' => $owner->id, 'workspace_id' => $workspace->id, 'start_date' => now()->startOfWeek(),
        'end_date' => now()->endOfWeek(), 'status' => 'approved', 'total_hours' => 6.5, 'billable_hours' => 6.5]);
    TimesheetEntry::create(['timesheet_id' => $timesheet->id, 'project_id' => $project->id, 'task_id' => $task->id, 'user_id' => $owner->id,
        'date' => now()->startOfWeek()->toDateString(), 'hours' => 6.5, 'is_billable' => true, 'hourly_rate' => 40, 'description' => 'Checkout <b>&</b> "tests"']);

    return [$owner, $workspace, $project];
}

function rxProject(Workspace $workspace, User $creator, array $attrs = []): Project
{
    return Project::create(array_merge([
        'workspace_id' => $workspace->id, 'title' => 'P' . uniqid(), 'status' => 'active',
        'priority' => 'medium', 'progress' => 0, 'created_by' => $creator->id, 'visibility' => 'private',
    ], $attrs));
}

function rxMember(Workspace $workspace, string $role): User
{
    $user = User::factory()->create(['type' => 'company', 'current_workspace_id' => $workspace->id]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active']);
    $user->assignRole($role);

    return $user;
}

/** Real-looking SMTP settings for the workspace, so the report can be sent. */
function rxConfigureMail(Workspace $workspace): void
{
    foreach ([
        'email_driver' => 'smtp', 'email_host' => 'smtp.mailgun.org', 'email_port' => '587', 'email_username' => 'postmaster',
        'email_password' => 'secret', 'email_encryption' => 'tls', 'email_from_address' => 'reports@acme.io', 'email_from_name' => 'Acme',
    ] as $key => $value) {
        Setting::create(['user_id' => $workspace->owner_id, 'workspace_id' => $workspace->id, 'key' => $key, 'value' => $value]);
    }
}

function rxRange(): array
{
    return ['start_date' => now()->subWeek()->toDateString(), 'end_date' => now()->addWeek()->toDateString(), 'status' => 'counted'];
}

// ═══ Customer Report generation ═══

test('the customer report generates with task stages, budget and completion counts', function () {
    [$owner, , $project] = rxSetup();

    $response = $this->actingAs($owner)->postJson(route('customer-reports.generate'), ['project_id' => $project->id])->assertOk();

    expect($response->json('summary.tasks'))->toBe(['total_tasks' => 2, 'completed_tasks' => 1, 'pending_tasks' => 1])
        ->and((float) $response->json('summary.budget.total_budget'))->toBe(12000.0)
        ->and((float) $response->json('summary.timesheet.total_hours'))->toBe(6.5)
        ->and(collect($response->json('project.tasks'))->pluck('task_stage.name')->sort()->values()->all())->toBe(['Shipped', 'To Do']);
});

test('the customer report leaves out private projects the manager is not on', function () {
    [$owner, $ws, $project] = rxSetup();
    $manager = rxMember($ws, 'manager');
    $mine = rxProject($ws, $owner, ['title' => 'Mine']);
    ProjectMember::create(['project_id' => $mine->id, 'user_id' => $manager->id, 'role' => 'manager', 'assigned_by' => $owner->id]);

    $this->actingAs($manager)->get(route('customer-reports.index'))
        ->assertInertia(fn (Assert $page) => $page->where('projects', fn ($projects) => collect($projects)->pluck('title')->all() === ['Mine']));
    $this->actingAs($manager)->postJson(route('customer-reports.generate'), ['project_id' => $project->id])->assertNotFound();
    $this->actingAs($manager)->get(route('customer-reports.export', ['project_id' => $project->id, 'format' => 'xlsx']))->assertNotFound();
});

// ═══ Downloads ═══

test('the timesheet report downloads as PDF, Excel and Word', function () {
    [$owner] = rxSetup();

    $pdf = $this->actingAs($owner)->get(route('timesheet-reports.export', rxRange() + ['format' => 'pdf']))->assertOk();
    expect($pdf->headers->get('Content-Type'))->toBe('application/pdf')
        ->and(str_starts_with($pdf->getContent(), '%PDF'))->toBeTrue();

    $xlsx = $this->actingAs($owner)->get(route('timesheet-reports.export', rxRange() + ['format' => 'xlsx']))->assertOk();
    expect($xlsx->headers->get('Content-Disposition'))->toContain('.xlsx');
    $path = tempnam(sys_get_temp_dir(), 'rx') . '.xlsx';
    file_put_contents($path, $xlsx->getContent());
    $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
    @unlink($path);
    expect($book->getSheetNames())->toBe(['Overview', 'Projects', 'Team Members', 'Time Entries'])
        ->and($book->getSheetByName('Time Entries')->getCell('F2')->getValue())->toEqual(6.5)
        ->and($book->getSheetByName('Time Entries')->getCell('E2')->getValue())->toBe('Checkout <b>&</b> "tests"');

    $docx = $this->actingAs($owner)->get(route('timesheet-reports.export', rxRange() + ['format' => 'docx']))->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'rx');
    file_put_contents($path, $docx->getContent());
    $zip = new ZipArchive();
    $zip->open($path);
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    @unlink($path);
    // Escaped exactly once, or Word refuses to open the file.
    expect($xml)->toContain('Checkout &lt;b&gt;&amp;&lt;/b&gt; &quot;tests&quot;')
        ->and(simplexml_load_string($xml))->not->toBeFalse();
});

test('the customer and project reports download in every format', function (string $format, string $mime) {
    [$owner, , $project] = rxSetup();

    $this->actingAs($owner)->get(route('customer-reports.export', ['project_id' => $project->id, 'format' => $format]))
        ->assertOk()->assertHeader('Content-Type', $mime);
    $this->actingAs($owner)->get(route('project-reports.export', ['project' => $project->id, 'format' => $format]))
        ->assertOk()->assertHeader('Content-Type', $mime);
})->with([
    'pdf' => ['pdf', 'application/pdf'],
    'xlsx' => ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'docx' => ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
]);

test('PDF and Word trim very long tables and point to Excel, which keeps every row', function () {
    $doc = (new \App\Services\Reports\ReportDocument('Big', 'big'))
        ->table('Short', ['A'], [['one'], ['two']])
        ->table('Long', ['A'], array_fill(0, 2000, ['row']));
    $renderer = new \App\Services\Reports\ReportRenderer();

    $path = tempnam(sys_get_temp_dir(), 'rx');
    file_put_contents($path, $renderer->render($doc, 'docx')->content);
    $zip = new ZipArchive();
    $zip->open($path);
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    expect($xml)->toContain('Showing the first 1,498 of 2,000 rows. Download the Excel version for the full list.')
        ->and(substr_count($xml, '>one<') + substr_count($xml, '>two<'))->toBe(2);

    file_put_contents($path, $renderer->render($doc, 'xlsx')->content);
    rename($path, $path . '.xlsx');
    expect(\PhpOffice\PhpSpreadsheet\IOFactory::load($path . '.xlsx')->getSheetByName('Long')->getHighestRow())->toBe(2001);
    @unlink($path . '.xlsx');

    // The original document is left untouched for the next format.
    expect($doc->sections[1]['rows'])->toHaveCount(2000);
});

test('an unknown download format is rejected', function () {
    [$owner] = rxSetup();

    $this->actingAs($owner)->getJson(route('timesheet-reports.export', rxRange() + ['format' => 'exe']))
        ->assertUnprocessable()->assertJsonValidationErrors('format');
});

// ═══ Email ═══

test('a report can be emailed to yourself', function () {
    Mail::fake();
    [$owner, $ws] = rxSetup();
    rxConfigureMail($ws);

    $this->actingAs($owner)->postJson(route('timesheet-reports.email'), rxRange() + ['format' => 'xlsx', 'recipient' => 'me'])
        ->assertOk()->assertJson(['email' => 'owner@acme.io']);

    Mail::assertSent(ReportMail::class, fn (ReportMail $mail) => $mail->hasTo('owner@acme.io')
        && str_ends_with($mail->file->filename, '.xlsx')
        && str_starts_with($mail->file->content, 'PK'));
});

test('a report can be emailed to another address with a note', function () {
    Mail::fake();
    [$owner, $ws, $project] = rxSetup();
    rxConfigureMail($ws);

    $this->actingAs($owner)->postJson(route('customer-reports.email'), [
        'project_id' => $project->id, 'format' => 'pdf', 'recipient' => 'other', 'email' => 'client@brightcart.in', 'message' => 'Weekly update',
    ])->assertOk();
    $this->actingAs($owner)->postJson(route('project-reports.email', $project), ['format' => 'docx', 'recipient' => 'other', 'email' => 'cfo@brightcart.in'])
        ->assertOk();

    Mail::assertSent(ReportMail::class, fn (ReportMail $mail) => $mail->hasTo('client@brightcart.in')
        && $mail->note === 'Weekly update' && str_starts_with($mail->file->content, '%PDF'));
    Mail::assertSent(ReportMail::class, fn (ReportMail $mail) => $mail->hasTo('cfo@brightcart.in') && str_ends_with($mail->file->filename, '.docx'));
});

test('emailing needs a valid address when sending to someone else', function () {
    Mail::fake();
    [$owner, $ws] = rxSetup();
    rxConfigureMail($ws);

    $this->actingAs($owner)->postJson(route('timesheet-reports.email'), rxRange() + ['format' => 'pdf', 'recipient' => 'other'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->actingAs($owner)->postJson(route('timesheet-reports.email'), rxRange() + ['format' => 'pdf', 'recipient' => 'other', 'email' => 'not-an-email'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    Mail::assertNothingSent();
});

test('emailing explains when the workspace has no mail server set up', function () {
    Mail::fake();
    [$owner] = rxSetup();

    $this->actingAs($owner)->postJson(route('timesheet-reports.email'), rxRange() + ['format' => 'pdf', 'recipient' => 'me'])
        ->assertUnprocessable()->assertJson(['message' => "Email isn't set up for this workspace yet. Add your mail server in Settings > Email Settings, then try again."]);

    Mail::assertNothingSent();
});

test('a project report cannot be emailed by someone who cannot see the project', function () {
    Mail::fake();
    [$owner, $ws, $project] = rxSetup();
    rxConfigureMail($ws);
    $member = rxMember($ws, 'member');

    $this->actingAs($member)->postJson(route('project-reports.email', $project), ['format' => 'pdf', 'recipient' => 'other', 'email' => 'x@evil.test'])
        ->assertForbidden();

    Mail::assertNothingSent();
});
