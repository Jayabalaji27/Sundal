<?php
/**
 * Regression tests for the 2026-10-04 manager-role QA report (production-readiness pass).
 */

use App\Models\Form;
use App\Models\FormField;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskStage;
use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(PlanSeeder::class);
    // CheckModuleAccess stays on: the Pro Add-on gate is part of what's tested here.
    $this->withoutMiddleware([
        \App\Http\Middleware\CheckInstallation::class,
        \App\Http\Middleware\ShareGlobalSettings::class,
        \App\Http\Middleware\DemoModeMiddleware::class,
        \App\Http\Middleware\CheckPlanAccess::class,
        \App\Http\Middleware\CheckPlanLimits::class,
        \App\Http\Middleware\EnsureEmailIsVerified::class,
    ]);
    Cache::flush();
});

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Owner + workspace + manager, owner on the default plan without the Pro Add-on. */
function mgrWorkspace(): array
{
    $plan = Plan::where('is_default', true)->first();
    $owner = User::factory()->create(['type' => 'company', 'plan_id' => $plan?->id, 'plan_is_active' => 1]);
    $workspace = Workspace::create(['name' => 'QA WS', 'slug' => 'qa-' . uniqid(), 'owner_id' => $owner->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    $owner->update(['current_workspace_id' => $workspace->id]);
    $owner->assignRole('company');

    $manager = User::factory()->create(['type' => 'company', 'current_workspace_id' => $workspace->id]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $manager->id, 'role' => 'manager', 'status' => 'active']);
    $manager->assignRole('manager');

    return [$owner, $workspace, $manager];
}

function mgrProject(Workspace $workspace, User $creator): Project
{
    return Project::create([
        'workspace_id' => $workspace->id, 'title' => 'P' . uniqid(), 'status' => 'active',
        'priority' => 'medium', 'progress' => 0, 'created_by' => $creator->id,
    ]);
}

function mgrTimesheet(Workspace $workspace, Project $project, User $user, string $status): Timesheet
{
    $timesheet = Timesheet::create(['user_id' => $user->id, 'workspace_id' => $workspace->id, 'start_date' => now()->startOfWeek(),
        'end_date' => now()->endOfWeek(), 'status' => $status, 'total_hours' => 8, 'billable_hours' => 8]);
    TimesheetEntry::create(['timesheet_id' => $timesheet->id, 'project_id' => $project->id, 'user_id' => $user->id,
        'date' => now()->startOfWeek()->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        'hours' => 8, 'is_billable' => true, 'hourly_rate' => 0]);

    return $timesheet;
}

/** The update payload the timesheet form sends: same entry, end time moved to 18:00. */
function mgrTimesheetEdit(Timesheet $timesheet): array
{
    $entry = $timesheet->entries()->first();
    $date = now()->startOfWeek()->toDateString();

    return [
        'start_date' => $date,
        'notes' => null,
        'entries' => [[
            'id' => $entry->id, 'project_id' => $entry->project_id, 'task_id' => null,
            'start_time' => "{$date}T09:00:00", 'end_time' => "{$date}T18:00:00", 'description' => null,
        ]],
    ];
}

// ═══ P1 ═══

describe('BUG-01 checklist progress', function () {
    test('adding, toggling and deleting checklist items keeps task progress in sync', function () {
        [$owner, $ws, $manager] = mgrWorkspace();
        $project = mgrProject($ws, $manager);
        $inProgress = TaskStage::create(['workspace_id' => $ws->id, 'name' => 'In Progress', 'color' => '#00f', 'order' => 1]);
        $task = Task::create(['project_id' => $project->id, 'task_stage_id' => $inProgress->id, 'title' => 'T',
            'priority' => 'medium', 'progress' => 0, 'created_by' => $manager->id]);
        $item = ['assigned_to' => null, 'due_date' => null];

        $this->actingAs($manager)->post(route('task-checklists.store', $task), ['title' => 'A'] + $item);
        $a = TaskChecklist::where('task_id', $task->id)->where('title', 'A')->first();
        $this->actingAs($manager)->post(route('task-checklists.toggle', $a));
        expect($task->fresh()->progress)->toBe(100);

        $this->actingAs($manager)->post(route('task-checklists.store', $task), ['title' => 'B'] + $item);
        expect($task->fresh()->progress)->toBe(50)
            ->and($project->fresh()->progress)->toBe(50);

        $b = TaskChecklist::where('task_id', $task->id)->where('title', 'B')->first();
        $this->actingAs($manager)->delete(route('task-checklists.destroy', $b));
        expect($task->fresh()->progress)->toBe(100);
    });

    test('project health counts done tasks by completed stage, not by progress', function () {
        [$owner, $ws, $manager] = mgrWorkspace();
        $project = mgrProject($ws, $manager);
        $inProgress = TaskStage::create(['workspace_id' => $ws->id, 'name' => 'In Progress', 'color' => '#00f', 'order' => 1]);
        $done = TaskStage::create(['workspace_id' => $ws->id, 'name' => 'Done', 'color' => '#0f0', 'order' => 2]);
        foreach ([$inProgress, $done] as $stage) {
            Task::create(['project_id' => $project->id, 'task_stage_id' => $stage->id, 'title' => 'T' . uniqid(),
                'priority' => 'medium', 'progress' => 100, 'created_by' => $manager->id]);
        }

        $this->actingAs($manager)->getJson(route('projects.health', $project))
            ->assertOk()
            ->assertJsonPath('metrics.total_tasks', 2)
            ->assertJsonPath('metrics.done_tasks', 1);
    });
});

describe('BUG-02 submitted timesheets are locked', function () {
    test('a submitted or approved timesheet cannot be updated or deleted', function (string $status) {
        [$owner, $ws, $manager] = mgrWorkspace();
        $timesheet = mgrTimesheet($ws, mgrProject($ws, $owner), $manager, $status);

        $this->actingAs($manager)->put(route('timesheets.update', $timesheet), mgrTimesheetEdit($timesheet))
            ->assertSessionHasErrors('message');
        $this->actingAs($manager)->putJson(route('timesheets.update', $timesheet), mgrTimesheetEdit($timesheet))
            ->assertUnprocessable();
        $this->actingAs($manager)->delete(route('timesheets.destroy', $timesheet))
            ->assertSessionHasErrors('message');

        $timesheet->refresh();
        expect($timesheet->exists)->toBeTrue()
            ->and((float) $timesheet->entries()->sum('hours'))->toEqual(8.0)
            ->and($timesheet->status)->toBe($status);
    })->with(['submitted', 'approved']);

    test('entries of a submitted timesheet cannot be changed through the entry routes', function () {
        [$owner, $ws, $manager] = mgrWorkspace();
        $project = mgrProject($ws, $owner);
        $timesheet = mgrTimesheet($ws, $project, $manager, 'submitted');
        $entry = $timesheet->entries()->first();

        $this->actingAs($manager)->delete(route('timesheet-entries.destroy', $entry))->assertSessionHas('error');
        $this->actingAs($manager)->post(route('timesheet-entries.store'), [
            'project_id' => $project->id, 'date' => now()->startOfWeek()->toDateString(), 'hours' => 1,
        ])->assertSessionHas('error');

        expect($timesheet->entries()->count())->toBe(1);
    });

    test('a rejected timesheet can still be edited before resubmitting', function () {
        [$owner, $ws, $manager] = mgrWorkspace();
        $timesheet = mgrTimesheet($ws, mgrProject($ws, $owner), $manager, 'rejected');

        $this->actingAs($manager)->put(route('timesheets.update', $timesheet), mgrTimesheetEdit($timesheet))
            ->assertSessionHasNoErrors();

        expect((float) $timesheet->fresh()->total_hours)->toEqual(9.0);
    });
});

describe('BUG-03 AI endpoints are plan-gated', function () {
    test('chatbot and ChatGPT endpoints need the Pro Add-on', function (string $route) {
        config(['app.is_saas' => true]);
        [$owner, $ws, $manager] = mgrWorkspace();
        $owner->update(['plan_id' => null, 'addon_plan_id' => null, 'addon_is_active' => 0]);

        $this->actingAs($manager)->postJson(route($route), ['prompt' => 'Hi', 'message' => 'Hi', 'question' => 'Hi'])
            ->assertStatus(402)
            ->assertJsonPath('error', __('This feature requires the Pro Add-on plan (AI, Knowledge Base, Chat & Meetings). Please upgrade to continue.'));
    })->with(['chatbot.ask', 'chatgpt.generate']);
});

// ═══ P2 ═══

describe('BUG-04/05 expense approvals', function () {
    test('stats only count expenses from projects the manager can see in the queue', function () {
        [$owner, $ws, $manager] = mgrWorkspace();
        $ownerProject = mgrProject($ws, $owner);
        $managerProject = mgrProject($ws, $manager);
        ProjectExpense::create(['project_id' => $ownerProject->id, 'submitted_by' => $owner->id, 'amount' => 2500,
            'expense_date' => now()->toDateString(), 'title' => 'Owner expense', 'status' => 'approved']);
        ProjectExpense::create(['project_id' => $managerProject->id, 'submitted_by' => $manager->id, 'amount' => 250,
            'expense_date' => now()->toDateString(), 'title' => 'Manager expense', 'status' => 'pending']);

        $this->actingAs($manager)->getJson(route('expense-approvals.stats'))
            ->assertOk()
            ->assertJson(['pending_count' => 1, 'approved_today' => 0])
            ->assertJsonPath('pending_amount', fn ($v) => (float) $v === 250.0)
            ->assertJsonPath('total_approved_amount', fn ($v) => (float) $v === 0.0);

        $this->actingAs($manager)->get(route('expense-approvals.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('expenses.data', 1)
                ->where('stats.pending_count', 1)
                ->where('stats.approved_today', 0)
                // Own expense stays visible, but the manager can't review it
                ->where('permissions.review_own', false));

        $this->actingAs($owner)->getJson(route('expense-approvals.stats'))
            ->assertJson(['pending_count' => 1, 'approved_today' => 1]);
        $this->actingAs($owner)->get(route('expense-approvals.index'))
            ->assertInertia(fn (Assert $page) => $page->where('permissions.review_own', true));
    });
});

describe('BUG-07 public form validation', function () {
    test('required-field errors use the field label, not its internal key', function () {
        [$owner, $ws] = mgrWorkspace();
        $form = Form::create(['workspace_id' => $ws->id, 'created_by' => $owner->id, 'title' => 'QA-Feedback', 'is_active' => true]);
        $field = FormField::create(['form_id' => $form->id, 'type' => 'text', 'label' => 'QA-Your name', 'required' => true, 'sort_order' => 0]);

        $this->post(route('forms.submit', $form->token), [])
            ->assertSessionHasErrors(['field_' . $field->id => 'QA-Your name is required.']);
    });
});

// ═══ P3 ═══

describe('BUG-14 invoices', function () {
    test('a task already on a sent or paid invoice cannot be billed again', function () {
        [$owner, $ws] = mgrWorkspace();
        $project = mgrProject($ws, $owner);
        $stage = TaskStage::create(['workspace_id' => $ws->id, 'name' => 'To Do', 'color' => '#000', 'order' => 1]);
        $billed = Task::create(['project_id' => $project->id, 'task_stage_id' => $stage->id, 'title' => 'Billed',
            'priority' => 'medium', 'progress' => 0, 'created_by' => $owner->id]);
        $drafted = Task::create(['project_id' => $project->id, 'task_stage_id' => $stage->id, 'title' => 'Drafted',
            'priority' => 'medium', 'progress' => 0, 'created_by' => $owner->id]);
        $payload = ['project_id' => $project->id, 'title' => 'Inv', 'invoice_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString()];

        $this->actingAs($owner)->post(route('invoices.store'), $payload + ['items' => [['type' => 'task', 'amount' => 100, 'task_id' => $billed->id]]])
            ->assertSessionHasNoErrors();
        $paid = \App\Models\Invoice::latest('id')->first();
        $paid->update(['status' => 'paid']);
        // A draft hasn't billed anyone, so its task stays available
        $this->actingAs($owner)->post(route('invoices.store'), $payload + ['items' => [['type' => 'task', 'amount' => 100, 'task_id' => $drafted->id]]])
            ->assertSessionHasNoErrors();

        $this->actingAs($owner)->post(route('invoices.store'), $payload + ['items' => [
            ['type' => 'task', 'amount' => 100, 'task_id' => $drafted->id],
            ['type' => 'task', 'amount' => 100, 'task_id' => $billed->id],
        ]])->assertSessionHasErrors('items.1.task_id')->assertSessionDoesntHaveErrors('items.0.task_id');

        // The paid invoice can still be edited with its own task
        $this->actingAs($owner)->put(route('invoices.update', $paid), $payload + ['items' => [
            ['type' => 'task', 'description' => 'Billed', 'amount' => 150, 'task_id' => $billed->id],
        ]])->assertSessionHasNoErrors();

        $this->actingAs($owner)->getJson(route('api.projects.invoice-data', $project))
            ->assertJsonCount(1, 'tasks')->assertJsonPath('tasks.0.id', $drafted->id);
        $this->actingAs($owner)->getJson(route('api.projects.invoice-data', ['project' => $project->id, 'invoice' => $paid->id]))
            ->assertJsonCount(2, 'tasks');
    });
});

describe('BUG-21 dashboard task stats', function () {
    test('dashboard task totals use the same visibility as the Tasks page', function () {
        [$owner, $ws, $manager] = mgrWorkspace();
        $private = mgrProject($ws, $owner);
        $private->update(['visibility' => 'private']);
        $shared = mgrProject($ws, $owner);
        $shared->update(['visibility' => 'workspace']);
        $done = TaskStage::create(['workspace_id' => $ws->id, 'name' => 'Done', 'color' => '#0f0', 'order' => 1]);
        foreach ([$private, $shared] as $project) {
            Task::create(['project_id' => $project->id, 'task_stage_id' => $done->id, 'title' => 'T' . uniqid(),
                'priority' => 'medium', 'progress' => 100, 'created_by' => $owner->id, 'assigned_to' => $manager->id]);
        }

        $this->actingAs($manager)->get(route('tasks.index'))
            ->assertInertia(fn (Assert $page) => $page->has('tasks', 1));
        $this->actingAs($manager)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboardData.tasks.total', 1)
                ->where('dashboardData.tasks.completed', 1));
    });
});
