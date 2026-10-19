<?php

namespace Database\Seeders;

use App\Models\BudgetCategory;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\ExpenseApproval;
use App\Models\GoogleMeeting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Note;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanOrder;
use App\Models\PlanRequest;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectClient;
use App\Models\ProjectExpense;
use App\Models\ProjectMember;
use App\Models\ProjectMilestone;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskComment;
use App\Models\TaskStage;
use App\Models\Tax;
use App\Models\Timesheet;
use App\Models\TimesheetApproval;
use App\Models\TimesheetEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\ZoomMeeting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Connected demo data for presenting Sundal to a client.
 *
 * One software agency, "Nimbus Digital Solutions" (the company@test.com
 * workspace): managers Priya and Neha run two client projects in flight
 * (BrightCart e-commerce, FinEdge mobile banking) with sprints, story points,
 * bugs, timesheets, invoices, meetings, contracts and budgets; a CRM project
 * waiting for its first sprint; a finished and a paused project; and an R&D
 * workspace. Members only appear on the projects they work on, clients see
 * their own projects, invoices and contracts. A few other companies give the
 * superadmin something to manage.
 *
 *   php artisan db:seed --class=DemoShowcaseSeeder
 *
 * Re-runnable. It first CLEARS all work data (projects, tasks, bugs, sprints,
 * timesheets, invoices, meetings, budgets, expenses, notes, contracts,
 * activity) in every workspace; users, roles, plans and settings stay.
 * Existing test logins get realistic names. Every demo login uses the shared
 * password from config/demo.php (DEMO_PASSWORD, default Demo@12345); the
 * accounts listed there also have their password reset to it, so the login
 * page panel (DEMO_LOGIN_PANEL) is always right. Dates are relative to the
 * day it runs.
 */
class DemoShowcaseSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'Demo@12345';

    /** Work data removed before seeding (all workspaces). */
    private const WORK_TABLES = [
        'projects', 'project_members', 'project_clients', 'project_milestones', 'project_notes', 'project_attachments',
        'project_activities', 'project_budgets', 'budget_categories', 'budget_revisions', 'budget_revision_approvals',
        'project_expenses', 'expense_approvals', 'expense_attachments', 'expense_recurring',
        'tasks', 'task_members', 'task_comments', 'task_checklists', 'task_attachments', 'sprints', 'sprint_tasks',
        'bugs', 'bug_comments', 'bug_attachments', 'timesheets', 'timesheet_entries', 'timesheet_approvals',
        'invoices', 'invoice_items', 'payments', 'zoom_meetings', 'zoom_meeting_members', 'google_meetings',
        'google_meeting_members', 'notes', 'contracts', 'contracts_attachments', 'contracts_comments', 'contracts_notes',
        'portfolios', 'notifications', 'activity_log', 'plan_orders', 'plan_requests',
    ];

    /** Accounts only the demo uses, and how long ago they "signed up" (days). */
    private const DEMO_ACCOUNT_AGES = [
        'neha.iyer@test.com' => 300, 'vikram.singh@test.com' => 250, 'ananya.rao@test.com' => 330, 'rahul.verma@test.com' => 200,
        'sarah.mitchell@test.com' => 45, 'maya.chen@test.com' => 62, 'lucas.fernandes@test.com' => 16,
    ];

    private const CLIENT_SHARING = [
        'overview' => true, 'member' => true, 'client' => false, 'milestone' => true, 'notes' => false, 'budget' => false,
        'expenses' => false, 'task' => true, 'recent_bugs' => true, 'timesheet' => false, 'files' => true, 'activity' => true,
    ];

    private Carbon $today;
    private string $demoHash;
    /** @var array<string, User> */
    private array $people = [];
    /** @var array<string, Task> */
    private array $tasks = [];
    /** @var array<string, Project> */
    private array $projects = [];
    /** @var array<string, ProjectMilestone> */
    private array $milestones = [];
    private Workspace $agency;
    private Workspace $lab;
    /** Workspace being filled; records are created as if the actor were working in it. */
    private ?int $seedingWorkspaceId = null;

    public function run(): void
    {
        // Seeding goes through the models (activity feed, numbering, progress),
        // never through the controllers that send mail, but stay safe anyway.
        config(['mail.default' => 'array']);
        $this->today = Carbon::today();
        $this->demoHash = Hash::make($this->demoPassword());

        try {
            $this->clearWorkData();
            $this->setUpPeopleAndWorkspaces();
            $this->seedBrightCart();
            $this->seedFinEdge();
            $this->seedCrmIntegration();
            $this->seedWebsiteRedesign();
            $this->seedHrPortal();
            $this->seedLab();
            $this->seedTimesheets();
            $this->seedMeetings();
            $this->seedNotes();
            $this->seedOtherCompanies();
            // The accounts on the login page panel must open with the password it shows.
            User::whereIn('email', array_column(config('demo.accounts', []), 'email'))->update(['password' => $this->demoHash]);
            foreach (self::DEMO_ACCOUNT_AGES as $email => $days) {
                User::where('email', $email)->update(['created_at' => $this->day(-$days, '10:30')]);
            }
            foreach ($this->projects as $project) {
                $this->seedingWorkspaceId = $project->workspace_id;
                $this->as($project->creator ?? $this->people['owner'], $this->today->copy()->setTime(8, 0), fn () => $project->refresh()->updateProgressFromMilestones());
            }
        } finally {
            Carbon::setTestNow();
            Auth::forgetUser();
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->printLogins();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** A date relative to the day the seeder runs. */
    private function day(int $offset, string $time = '10:00'): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));
        return $this->today->copy()->addDays($offset)->setTime($hour, $minute);
    }

    /**
     * Runs $fn as $user at $when, so created_at, the project activity feed
     * ("Priya created task …") and the history log read like real work.
     */
    private function as(User $user, Carbon $when, callable $fn): mixed
    {
        // Workspace-scoped models (projects, sprints…) only see the actor's current
        // workspace, so act inside the workspace being seeded.
        $actor = clone $user;
        $actor->current_workspace_id = $this->seedingWorkspaceId ?? $user->current_workspace_id;
        Auth::setUser($actor);
        Carbon::setTestNow($when);
        try {
            return $fn();
        } finally {
            Carbon::setTestNow();
        }
    }

    private function stage(int $workspaceId, string $name): TaskStage
    {
        $stages = TaskStage::withoutGlobalScopes()->where('workspace_id', $workspaceId)->orderBy('order')->get();
        return $stages->firstWhere('name', $name)
            ?? ($name === 'Done' ? ($stages->firstWhere('is_completed', true) ?? $stages->last()) : $stages->first());
    }

    // ── 1. Clear ─────────────────────────────────────────────────────────────

    private function clearWorkData(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (self::WORK_TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }
        Schema::enableForeignKeyConstraints();

        // Running timers pointed at deleted tasks.
        DB::table('users')->update([
            'timer_active' => 0, 'timer_project_id' => null, 'timer_task_id' => null, 'timer_entry_id' => null,
            'timer_started_at' => null, 'timer_description' => null, 'timer_elapsed_seconds' => 0,
        ]);
    }

    // ── 2. People and workspaces ─────────────────────────────────────────────

    /**
     * An existing login gets the demo name; a missing one is created with the
     * demo password. Staff need created_by (their company
     * owner) or the login form turns them away (LoginRequest::canUserLogin).
     */
    private function demoPassword(): string
    {
        return (string) config('demo.password', self::DEMO_PASSWORD);
    }

    private function person(string $email, string $name, string $type, ?string $role, ?int $workspaceId = null, ?int $createdBy = null): User
    {
        $user = User::where('email', $email)->first();
        if ($user) {
            $user->forceFill(['name' => $name] + ($createdBy && !$user->created_by ? ['created_by' => $createdBy] : []))->save();
        } else {
            $user = User::forceCreate([
                'name' => $name, 'email' => $email, 'password' => $this->demoHash, 'type' => $type,
                'email_verified_at' => now(), 'lang' => 'en', 'current_workspace_id' => $workspaceId, 'is_enable_login' => 1,
                'status' => 'active',
            ] + ($createdBy ? ['created_by' => $createdBy] : []));
        }
        if ($role && !$user->hasRole($role)) {
            $user->syncRoles([$role]);
        }
        return $user;
    }

    private function join(Workspace $workspace, User $user, string $role, ?User $invitedBy = null, int $joinedDaysAgo = 120): void
    {
        WorkspaceMember::updateOrCreate(
            ['workspace_id' => $workspace->id, 'user_id' => $user->id],
            ['role' => $role, 'status' => 'active', 'invited_by' => $invitedBy?->id, 'invited_at' => $this->day(-$joinedDaysAgo - 1), 'joined_at' => $this->day(-$joinedDaysAgo)]
        );
    }

    private function setUpPeopleAndWorkspaces(): void
    {
        $proPlan = Plan::where('name', 'Pro')->first() ?? Plan::orderByDesc('max_projects_per_workspace')->first();

        $owner = $this->person('company@test.com', 'Karthik Raman', 'company', 'company');
        // Enough room for the demo team and live "Add project" during the demo (Free allows 3 projects).
        $owner->forceFill(['plan_id' => $proPlan?->id, 'plan_is_active' => 1, 'plan_expire_date' => $this->day(275)])->save();

        $agency = Workspace::withoutGlobalScopes()->where('owner_id', $owner->id)->orderBy('id')->first();
        if (!$agency) {
            $agency = Workspace::forceCreate(['name' => 'Nimbus Digital Solutions', 'slug' => 'nimbus-digital-' . $owner->id, 'owner_id' => $owner->id]);
        }
        $agency->forceFill([
            'name' => 'Nimbus Digital Solutions',
            'description' => 'Web, mobile and integration projects for retail and fintech clients.',
            'is_active' => true, 'timesheet_enabled' => true, 'timesheet_approval_required' => 'manager',
        ])->save();
        $this->agency = $agency;
        $this->seedingWorkspaceId = $agency->id;
        $owner->forceFill(['current_workspace_id' => $agency->id])->save();
        $this->join($agency, $owner, 'owner', null, 400);

        $team = [
            'owner' => $owner,
            'priya' => $this->person('manager@test.com', 'Priya Sharma', 'manager', 'manager', $agency->id, $owner->id),
            'neha' => $this->person('neha.iyer@test.com', 'Neha Iyer', 'manager', 'manager', $agency->id, $owner->id),
            'arjun' => $this->person('member@test.com', 'Arjun Mehta', 'member', 'member', $agency->id, $owner->id),
            'vikram' => $this->person('vikram.singh@test.com', 'Vikram Singh', 'member', 'member', $agency->id, $owner->id),
            'ananya' => $this->person('ananya.rao@test.com', 'Ananya Rao', 'member', 'member', $agency->id, $owner->id),
            'rahul' => $this->person('rahul.verma@test.com', 'Rahul Verma', 'member', 'member', $agency->id, $owner->id),
            'daniel' => $this->person('client@test.com', 'Daniel Brooks', 'client', 'client', $agency->id, $owner->id),
            'sarah' => $this->person('sarah.mitchell@test.com', 'Sarah Mitchell', 'client', 'client', $agency->id, $owner->id),
        ];
        $this->people = $team;

        $roles = ['priya' => 'manager', 'neha' => 'manager', 'arjun' => 'member', 'vikram' => 'member', 'ananya' => 'member', 'rahul' => 'member', 'daniel' => 'client', 'sarah' => 'client'];
        $joined = ['priya' => 380, 'neha' => 300, 'arjun' => 360, 'vikram' => 250, 'ananya' => 330, 'rahul' => 200, 'daniel' => 70, 'sarah' => 45];
        foreach ($roles as $key => $role) {
            $this->join($agency, $team[$key], $role, $role === 'member' ? $team['priya'] : $owner, $joined[$key]);
            $team[$key]->forceFill(['current_workspace_id' => $agency->id])->save();
        }

        foreach (['superadmin@test.com' => 'Platform Admin', 'superadmin@example.com' => 'Super Admin'] as $email => $name) {
            User::where('email', $email)->update(['name' => $name]);
        }

        // A second workspace for the owner, to show switching workspaces.
        $lab = Workspace::withoutGlobalScopes()->where('owner_id', $owner->id)->where('name', 'Nimbus Labs (R&D)')->first()
            ?? Workspace::forceCreate([
                'name' => 'Nimbus Labs (R&D)', 'slug' => 'nimbus-labs-' . $owner->id, 'owner_id' => $owner->id,
                'description' => 'Internal experiments and proofs of concept.', 'is_active' => true,
            ]);
        $this->lab = $lab;
        $this->join($lab, $owner, 'owner', null, 90);
        $this->join($lab, $team['priya'], 'manager', $owner, 85);
        $this->join($lab, $team['arjun'], 'member', $owner, 85);
    }

    // ── 3. Projects ──────────────────────────────────────────────────────────

    /**
     * @param  array<string, string>  $members  person key => project role
     * @param  string[]  $clients  person keys
     */
    private function project(string $key, Workspace $workspace, string $creator, array $data, array $members, array $clients = []): Project
    {
        $creatorUser = $this->people[$creator];
        $created = $this->day($data['start'] - 4, '09:30');

        $project = $this->as($creatorUser, $created, fn () => Project::forceCreate([
            'workspace_id' => $workspace->id,
            'title' => $data['title'],
            'description' => $data['description'],
            'status' => $data['status'],
            'priority' => $data['priority'],
            'start_date' => $this->day($data['start'])->toDateString(),
            'deadline' => $this->day($data['deadline'])->toDateString(),
            'estimated_hours' => $data['hours'] ?? null,
            'budget' => $data['budget'] ?? null,
            'progress' => 0,
            'is_public' => false,
            'visibility' => $data['visibility'] ?? 'private',
            'shared_settings' => $clients ? self::CLIENT_SHARING : null,
            'created_by' => $creatorUser->id,
        ]));

        foreach ($members as $person => $role) {
            ProjectMember::forceCreate([
                'project_id' => $project->id, 'user_id' => $this->people[$person]->id, 'role' => $role,
                'assigned_at' => $created, 'assigned_by' => $creatorUser->id,
            ]);
        }
        foreach ($clients as $person) {
            ProjectClient::forceCreate([
                'project_id' => $project->id, 'user_id' => $this->people[$person]->id,
                'assigned_at' => $created, 'assigned_by' => $creatorUser->id,
            ]);
        }

        return $this->projects[$key] = $project;
    }

    /** @param  array<int, array{0:string,1:string,2:string,3:int}>  $rows  key, title, status, due offset */
    private function milestones(Project $project, string $creator, array $rows): void
    {
        foreach ($rows as $order => [$key, $title, $status, $due, $description]) {
            $this->milestones[$key] = ProjectMilestone::forceCreate([
                'project_id' => $project->id, 'title' => $title, 'description' => $description,
                'due_date' => $this->day($due)->toDateString(), 'status' => $status, 'progress' => 0, 'order' => $order + 1,
                'created_by' => $this->people[$creator]->id,
                'completed_at' => $status === 'completed' ? $this->day($due - 1, '17:00') : null,
                'completed_by' => $status === 'completed' ? $this->people[$creator]->id : null,
            ]);
        }
    }

    /**
     * Tasks: [key, title, assignee key|null, stage, points|null, priority, milestone key, start offset|null, due offset|null, description?].
     * Created by $creator shortly before the task starts.
     */
    private function tasks(Project $project, string $creator, array $rows): void
    {
        $workspaceId = $project->workspace_id;
        $progressByStage = ['To Do' => 0, 'In Progress' => 45, 'Review' => 85, 'Blocked' => 20, 'Done' => 100];

        foreach ($rows as [$key, $title, $assignee, $stage, $points, $priority, $milestone, $start, $due]) {
            $description = $this->taskDescriptions[$key] ?? null;
            $createdAt = $this->day(($start ?? -3) - 2, '11:00');
            $this->tasks[$key] = $this->as($this->people[$creator], $createdAt, fn () => Task::forceCreate([
                'project_id' => $project->id,
                'task_stage_id' => $this->stage($workspaceId, $stage)->id,
                'milestone_id' => $this->milestones[$milestone]->id ?? null,
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'story_points' => $points,
                'start_date' => $start !== null ? $this->day($start)->toDateString() : null,
                'end_date' => $due !== null ? $this->day($due)->toDateString() : null,
                'assigned_to' => $assignee ? $this->people[$assignee]->id : null,
                'created_by' => $this->people[$creator]->id,
                'progress' => $progressByStage[$stage] ?? 0,
            ]));
        }
    }

    /**
     * A sprint with its tasks. Completed sprints keep only the tasks they
     * finished (unfinished ones moved on) plus the frozen totals.
     *
     * @param  string[]  $taskKeys  tasks in the sprint now
     * @param  string[]  $carriedOver  unfinished tasks that moved to the next sprint
     */
    private function sprint(Project $project, string $creator, string $name, string $goal, int $start, int $end, string $status, array $taskKeys, array $carriedOver = []): Sprint
    {
        $creatorUser = $this->people[$creator];

        return $this->as($creatorUser, $this->day($start - 1, '16:00'), function () use ($project, $creatorUser, $name, $goal, $start, $end, $status, $taskKeys, $carriedOver) {
            $committed = collect([...$taskKeys, ...$carriedOver])->map(fn ($key) => $this->tasks[$key]);
            $finished = collect($taskKeys)->map(fn ($key) => $this->tasks[$key])->filter(fn (Task $task) => $task->isCompleted());

            $sprint = Sprint::forceCreate([
                'project_id' => $project->id, 'workspace_id' => $project->workspace_id, 'name' => $name, 'goal' => $goal,
                'start_date' => $this->day($start)->toDateString(), 'end_date' => $this->day($end)->toDateString(),
                'status' => $status, 'created_by' => $creatorUser->id,
                'committed_tasks' => $status === 'completed' ? $committed->count() : null,
                'committed_points' => $status === 'completed' ? (int) $committed->sum('story_points') : null,
                'completed_tasks' => $status === 'completed' ? $finished->count() : null,
                'completed_points' => $status === 'completed' ? (int) $finished->sum('story_points') : null,
            ]);
            foreach ($taskKeys as $key) {
                DB::table('sprint_tasks')->insert([
                    'sprint_id' => $sprint->id, 'task_id' => $this->tasks[$key]->id, 'created_by' => $creatorUser->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return $sprint;
        });
    }

    private function comment(string $taskKey, string $author, int $day, string $time, string $text): void
    {
        $this->as($this->people[$author], $this->day($day, $time), fn () => TaskComment::forceCreate([
            'task_id' => $this->tasks[$taskKey]->id, 'user_id' => $this->people[$author]->id, 'comment' => $text,
        ]));
    }

    private function checklist(string $taskKey, string $creator, array $items): void
    {
        foreach ($items as $order => [$title, $done]) {
            TaskChecklist::forceCreate([
                'task_id' => $this->tasks[$taskKey]->id, 'title' => $title, 'is_completed' => $done, 'order' => $order + 1,
                'assigned_to' => $this->tasks[$taskKey]->assigned_to, 'created_by' => $this->people[$creator]->id,
            ]);
        }
    }

    private function pair(string $taskKey, string $person, string $assignedBy): void
    {
        DB::table('task_members')->insert([
            'task_id' => $this->tasks[$taskKey]->id, 'user_id' => $this->people[$person]->id,
            'assigned_by' => $this->people[$assignedBy]->id, 'assigned_at' => $this->day(-5), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Bugs (tasks of type bug): [title, priority, severity, status, assignee, reporter, reported day offset,
     * environment, steps, expected, actual, resolution?]. The status is a bug word mapped onto a stage.
     */
    private function bugs(Project $project, string $milestone, array $rows): void
    {
        foreach ($rows as [$title, $priority, $severity, $status, $assignee, $reporter, $reportedOn, $environment, $steps, $expected, $actual, $resolution]) {
            $resolved = in_array($status, ['Resolved', 'Closed'], true);
            $stage = TaskStage::forBugStatus($project->workspace_id, $status) ?? $this->stage($project->workspace_id, 'To Do');
            $bug = $this->as($this->people[$reporter], $this->day($reportedOn, '15:20'), fn () => Task::forceCreate([
                'project_id' => $project->id,
                'type' => 'bug',
                'task_stage_id' => $stage->id,
                'progress' => $stage->is_completed ? 100 : 0,
                'milestone_id' => $this->milestones[$milestone]->id ?? null,
                'title' => $title, 'description' => $actual,
                'priority' => $priority, 'severity' => $severity,
                'steps_to_reproduce' => $steps, 'expected_behavior' => $expected, 'actual_behavior' => $actual,
                'environment' => $environment,
                'assigned_to' => $assignee ? $this->people[$assignee]->id : null,
                'created_by' => $this->people[$reporter]->id,
                'start_date' => $this->day($reportedOn)->toDateString(),
                'end_date' => $this->day($reportedOn + ($resolved ? 3 : 7))->toDateString(),
                'resolution_notes' => $resolution,
            ]));
            if ($assignee && !$resolved) {
                $this->as($this->people[$assignee], $this->day($reportedOn + 1, '10:15'), fn () => TaskComment::forceCreate([
                    'task_id' => $bug->id, 'user_id' => $this->people[$assignee]->id,
                    'comment' => $status === 'New' ? 'Reproduced on staging. Picking this up after the current task.' : 'Root cause found, fix is in progress.',
                ]));
            }
        }
    }

    /** @param  array<int, array{0:string,1:float,2:?string}>  $categories  name, amount, colour */
    private function budget(Project $project, string $creator, float $total, array $categories): array
    {
        $budget = $this->as($this->people[$creator], $this->day(-50, '12:00'), fn () => ProjectBudget::forceCreate([
            'project_id' => $project->id, 'workspace_id' => $project->workspace_id, 'total_budget' => $total, 'period_type' => 'project',
            'start_date' => $project->start_date, 'end_date' => $project->deadline,
            'description' => 'Approved delivery budget for ' . $project->title . '.', 'status' => 'active',
            'created_by' => $this->people[$creator]->id,
        ]));
        $ids = [];
        foreach ($categories as $order => [$name, $amount, $color, $description]) {
            $ids[$name] = BudgetCategory::forceCreate([
                'project_budget_id' => $budget->id, 'name' => $name, 'allocated_amount' => $amount, 'color' => $color,
                'description' => $description, 'sort_order' => $order + 1,
            ])->id;
        }
        return $ids;
    }

    /** Expenses: [title, category, amount, submitter, approver, status, day offset, vendor, description, note?]. */
    private function expenses(Project $project, array $categoryIds, array $rows): void
    {
        foreach ($rows as [$title, $category, $amount, $submitter, $approver, $status, $day, $vendor, $description, $note]) {
            $approved = $status === 'approved';
            $expense = $this->as($this->people[$submitter], $this->day($day, '13:00'), fn () => ProjectExpense::forceCreate([
                'project_id' => $project->id, 'budget_category_id' => $categoryIds[$category] ?? null,
                'submitted_by' => $this->people[$submitter]->id, 'amount' => $amount, 'approved_amount' => $approved ? $amount : null,
                'currency' => 'USD', 'expense_date' => $this->day($day)->toDateString(), 'title' => $title, 'description' => $description,
                'vendor' => $vendor, 'status' => $status, 'is_recurring' => false, 'receipt_required' => false,
                'approved_at' => $approved ? $this->day($day + 1, '11:00') : null,
                'approved_by' => $approved ? $this->people[$approver]->id : null,
            ]));
            ExpenseApproval::forceCreate([
                'project_expense_id' => $expense->id, 'approver_id' => $this->people[$approver]->id, 'status' => $status,
                'notes' => $note, 'approved_at' => $status === 'pending' ? null : $this->day($day + 1, '11:00'), 'approval_level' => 1,
            ]);
        }
    }

    /** @param  array<int, array{0:string,1:float,2:?string}>  $items  description, amount, task key */
    private function invoice(Project $project, string $client, string $creator, string $title, int $issued, int $due, string $status, float $taxRate, array $items, string $notes): Invoice
    {
        $subtotal = array_sum(array_column($items, 1));
        $tax = round($subtotal * $taxRate / 100, 2);
        $total = $subtotal + $tax;
        $paid = $status === 'paid';
        $clientUser = $this->people[$client];
        // Invoices store the applied taxes as a list (Invoice::calculateTotals), so use a workspace tax.
        $taxes = [];
        if ($taxRate > 0) {
            $gst = Tax::withoutGlobalScopes()->firstOrCreate(['workspace_id' => $project->workspace_id, 'name' => 'GST'], ['rate' => $taxRate]);
            $taxes = [['id' => $gst->id, 'name' => $gst->name, 'rate' => (float) $gst->rate]];
        }

        $invoice = $this->as($this->people[$creator], $this->day($issued, '12:00'), fn () => Invoice::forceCreate([
            'project_id' => $project->id, 'workspace_id' => $project->workspace_id, 'client_id' => $clientUser->id,
            'created_by' => $this->people[$creator]->id, 'title' => $title, 'description' => $title . ' — ' . $project->title,
            'invoice_date' => $this->day($issued)->toDateString(), 'due_date' => $this->day($due)->toDateString(),
            'subtotal' => $subtotal, 'tax_rate' => $taxes, 'tax_amount' => $tax, 'total_amount' => $total, 'status' => $status,
            'paid_amount' => $paid ? $total : 0,
            'sent_at' => $status === 'draft' ? null : $this->day($issued, '12:30'),
            'viewed_at' => in_array($status, ['viewed', 'paid', 'overdue'], true) ? $this->day($issued + 1, '09:10') : null,
            'paid_at' => $paid ? $this->day($due - 2, '14:00') : null,
            'payment_method' => $paid ? 'bank_transfer' : null,
            'client_details' => ['name' => $clientUser->name, 'email' => $clientUser->email],
            'notes' => $notes,
            'terms' => 'Payment due within 15 days by bank transfer. Late payments attract 1.5% interest per month.',
        ]));
        foreach ($items as $order => [$description, $amount, $taskKey]) {
            InvoiceItem::forceCreate([
                'invoice_id' => $invoice->id, 'task_id' => $taskKey ? $this->tasks[$taskKey]->id : null,
                'type' => $taskKey ? 'task' : 'custom', 'description' => $description, 'rate' => $amount, 'amount' => $amount, 'sort_order' => $order + 1,
            ]);
        }
        if ($paid) {
            Payment::forceCreate([
                'created_by' => $this->people[$creator]->id, 'invoice_id' => $invoice->id, 'workspace_id' => $project->workspace_id,
                'payment_method' => 'bank_transfer', 'amount' => $total, 'payment_date' => $this->day($due - 2)->toDateString(),
                'transaction_id' => 'NEFT' . $this->day($due - 2)->format('ymd') . str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT),
                'status' => 'completed', 'notes' => 'Received in full.',
            ]);
        }
        return $invoice;
    }

    private function contract(Project $project, string $client, string $creator, string $type, string $subject, float $value, string $status, int $start, int $end, array $assigned, string $description): void
    {
        $typeId = ContractType::withoutGlobalScopes()->where('workspace_id', $project->workspace_id)->where('name', $type)->value('id')
            ?? ContractType::withoutGlobalScopes()->where('workspace_id', $project->workspace_id)->value('id');
        if (!$typeId) {
            return;
        }
        $this->as($this->people[$creator], $this->day($start - 5, '11:00'), fn () => Contract::forceCreate([
            'subject' => $subject, 'description' => $description, 'contract_type_id' => $typeId, 'contract_value' => $value,
            'start_date' => $this->day($start)->toDateString(), 'end_date' => $this->day($end)->toDateString(), 'status' => $status,
            'client_id' => $this->people[$client]->id, 'project_id' => $project->id,
            'assigned_users' => array_map(fn ($key) => $this->people[$key]->id, $assigned),
            'terms_conditions' => "1. Scope as described in the statement of work.\n2. Changes are agreed in writing and billed at the agreed rates.\n3. Intellectual property transfers to the client on final payment.\n4. Either party may terminate with 30 days' written notice.",
            'notes' => null, 'currency' => 'USD', 'workspace_id' => $project->workspace_id, 'created_by' => $this->people[$creator]->id,
            'sent_at' => $status === 'pending' ? null : $this->day($start - 4, '12:00'),
            'accepted_at' => $status === 'accept' ? $this->day($start - 2, '18:30') : null,
            'signed_at' => $status === 'accept' ? $this->day($start - 2, '18:30') : null,
        ]));
    }

    // ── BrightCart (Priya) ───────────────────────────────────────────────────

    private array $taskDescriptions = [
        'b13' => 'Cart page and mini-cart: change quantities, remove items, show savings. Logged-out carts are kept in local storage and merged into the account cart on sign-in.',
        'b14' => 'Percentage and fixed-amount coupons with expiry dates, minimum order value and one use per customer. Coupons must not stack with sale prices unless marked "stackable".',
        'b15' => 'Two-step checkout: shipping address (with saved addresses) and delivery slot. Validate PIN codes against the courier API.',
        'b19' => 'Payment integration is blocked until BrightCart shares Razorpay sandbox API keys and the webhook secret.',
        'b20' => 'Cards, UPI and net banking through Razorpay Checkout. Handle payment webhooks idempotently and show clear failure messages.',
        'f02' => 'Verify customers with Aadhaar OTP and PAN through the FinEdge KYC gateway; store only the verification reference, never the documents.',
        'f07' => 'Transfers to saved beneficiaries over IMPS, NEFT and UPI with a cooling period for new beneficiaries and an MPIN step.',
        'c03' => 'Nightly job that pushes new customers and orders from the store to Salesforce and pulls back account owners. Retries with back-off; failures are listed on a dashboard.',
        'l02' => 'Retrieval-augmented answers over the help-centre articles using the existing knowledge base as the source of truth.',
    ];

    private function seedBrightCart(): void
    {
        $p = $this->project('brightcart', $this->agency, 'priya', [
            'title' => 'BrightCart E-commerce Revamp',
            'description' => 'Rebuild of BrightCart Retail\'s online store: new storefront, faster search, a cart and checkout that convert, and Razorpay payments. Launch planned before the holiday sale.',
            'status' => 'active', 'priority' => 'high', 'start' => -36, 'deadline' => 40, 'hours' => 960, 'budget' => 48000,
        ], ['priya' => 'manager', 'arjun' => 'member', 'ananya' => 'member', 'rahul' => 'member'], ['daniel']);

        $this->milestones($p, 'priya', [
            ['b-m1', 'Discovery & UX design', 'completed', -22, 'Workshops, information architecture, design system and wireframes signed off by BrightCart.'],
            ['b-m2', 'Catalog, cart & checkout', 'in_progress', 8, 'Product listing, product pages, search, cart and checkout on staging.'],
            ['b-m3', 'Payments, QA & launch', 'pending', 40, 'Razorpay payments, performance and accessibility audits, production launch.'],
        ]);

        $this->tasks($p, 'priya', [
            // Sprint 1 — discovery and setup
            ['b01', 'Stakeholder workshops & requirements sign-off', 'ananya', 'Done', 3, 'high', 'b-m1', -34, -31],
            ['b02', 'Information architecture & sitemap', 'ananya', 'Done', 3, 'medium', 'b-m1', -31, -28],
            ['b03', 'Design system: colours, typography, components', 'ananya', 'Done', 5, 'high', 'b-m1', -30, -25],
            ['b04', 'Wireframes: home, listing and product pages', 'ananya', 'Done', 5, 'high', 'b-m1', -28, -22],
            ['b05', 'Project setup: Laravel API + React storefront', 'arjun', 'Done', 3, 'high', 'b-m2', -34, -24],
            ['b06', 'CI pipeline with automated tests on every pull request', 'rahul', 'Done', 3, 'medium', 'b-m2', -33, -29],
            // Sprint 2 — catalog
            ['b07', 'Product listing page with filters & sorting', 'arjun', 'Done', 8, 'high', 'b-m2', -22, -13],
            ['b08', 'Product detail page with image gallery', 'arjun', 'Done', 5, 'high', 'b-m2', -18, -11],
            ['b09', 'Search with autocomplete suggestions', 'arjun', 'Done', 5, 'medium', 'b-m2', -14, -8],
            ['b10', 'Responsive header & mega menu', 'ananya', 'Done', 3, 'medium', 'b-m2', -19, -15],
            ['b11', 'Test plan for catalog features', 'rahul', 'Done', 2, 'medium', 'b-m2', -22, -12],
            ['b12', 'Wishlist (save for later)', 'arjun', 'Review', 3, 'low', 'b-m2', -12, 1],
            // Sprint 3 — cart and checkout (running)
            ['b13', 'Shopping cart with quantity updates', 'arjun', 'In Progress', 8, 'critical', 'b-m2', -6, 2],
            ['b14', 'Coupon & discount engine', 'arjun', 'In Progress', 5, 'high', 'b-m2', -6, -1],
            ['b15', 'Checkout: address & delivery steps', 'arjun', 'To Do', 8, 'high', 'b-m2', -2, 6],
            ['b16', 'Order confirmation email template', 'ananya', 'Done', 2, 'medium', 'b-m2', -9, -3],
            ['b17', 'Checkout UI polish & empty states', 'ananya', 'In Progress', 3, 'medium', 'b-m2', -4, 3],
            ['b18', 'Regression tests: cart & checkout', 'rahul', 'To Do', 5, 'high', 'b-m2', -1, 7],
            ['b19', 'Get Razorpay sandbox keys from BrightCart', 'priya', 'Blocked', 1, 'high', 'b-m3', -5, -2],
            // Sprint 4 — planned
            ['b20', 'Payment gateway integration (Razorpay)', 'arjun', 'To Do', 8, 'critical', 'b-m3', 8, 16],
            ['b21', 'Customer order history & invoice download', 'arjun', 'To Do', 5, 'medium', 'b-m3', 10, 18],
            ['b22', 'Performance audit: Core Web Vitals', 'rahul', 'To Do', 3, 'medium', 'b-m3', 15, 20],
            // Backlog
            ['b23', 'Product reviews & star ratings', null, 'To Do', 5, 'low', 'b-m3', null, null],
            ['b24', 'Gift cards', null, 'To Do', 8, 'low', 'b-m3', null, null],
            ['b25', 'Abandoned-cart reminder emails', null, 'To Do', 5, 'medium', 'b-m3', null, null],
            ['b26', 'Admin: bulk product import from CSV', 'arjun', 'To Do', 8, 'medium', 'b-m3', null, null],
            ['b27', 'Accessibility audit (WCAG 2.1 AA)', 'rahul', 'To Do', 3, 'medium', 'b-m3', null, null],
        ]);

        $this->sprint($p, 'priya', 'Sprint 1', 'Sign off the UX direction and get the project skeleton and CI running.', -34, -21, 'completed', ['b01', 'b02', 'b03', 'b04', 'b05', 'b06']);
        $this->sprint($p, 'priya', 'Sprint 2', 'Customers can browse, filter and search the full catalog on staging.', -20, -7, 'completed', ['b07', 'b08', 'b09', 'b10', 'b11'], ['b12']);
        $this->sprint($p, 'priya', 'Sprint 3', 'Cart to checkout works end to end on staging, with coupons.', -6, 7, 'active', ['b12', 'b13', 'b14', 'b15', 'b16', 'b17', 'b18', 'b19']);
        $this->sprint($p, 'priya', 'Sprint 4', 'Take real payments in the sandbox and show customers their orders.', 8, 21, 'planning', ['b20', 'b21', 'b22']);

        $this->checklist('b15', 'priya', [['Address form with PIN-code validation', true], ['Saved addresses for signed-in customers', true], ['Delivery slot picker', false], ['Unit tests for address validation', false]]);
        $this->checklist('b13', 'arjun', [['Quantity stepper and remove item', true], ['Merge guest cart on sign-in', true], ['Show savings and coupon line', false]]);
        $this->pair('b13', 'ananya', 'priya');
        $this->pair('b18', 'arjun', 'priya');

        $this->comment('b13', 'priya', -4, '11:20', 'BrightCart wants guest carts to survive sign-in. Can we merge them into the account cart instead of replacing it?');
        $this->comment('b13', 'arjun', -4, '12:05', 'Yes — merging on sign-in, server cart stays the source of truth. Added it to the checklist.');
        $this->comment('b14', 'rahul', -2, '16:40', 'Found an edge case: percentage coupons stack with sale prices. Logged it as a bug.');
        $this->comment('b14', 'arjun', -1, '10:10', 'Thanks — adding a "stackable" flag on coupons, defaulting to off. Will need one more day.');
        $this->comment('b19', 'priya', -3, '17:30', 'Chased Daniel on the call today. Their finance team is creating the Razorpay sandbox account; keys expected Monday.');
        $this->comment('b12', 'rahul', -1, '15:00', 'Tested on Chrome, Safari and Android. Looks good — moving to Done once Priya reviews the empty state copy.');

        $this->bugs($p, 'b-m2', [
            ['Cart total rounds tax incorrectly on multi-item orders', 'high', 'major', 'In Progress', 'arjun', 'rahul', -3, 'Staging · Chrome 129 · Windows 11',
                "1. Add 3 different items to the cart\n2. Apply GST at 18%\n3. Compare the order total with the sum of line totals", 'Order total equals the sum of rounded line totals.', 'Total is off by ₹0.01–₹0.03 because tax is rounded once on the subtotal.', null],
            ['Expired coupon codes are still accepted', 'critical', 'critical', 'New', 'arjun', 'priya', -1, 'Staging',
                "1. Use coupon DIWALI10 (expired yesterday)\n2. Click Apply", 'An "expired coupon" message, no discount.', 'The discount is applied.', null],
            ['Product images do not zoom on Safari (iOS 18)', 'medium', 'minor', 'New', 'ananya', 'rahul', -2, 'iPhone 15 · Safari · iOS 18.1',
                "1. Open any product page\n2. Pinch or double-tap the main image", 'Image zooms in.', 'Nothing happens.', null],
            ['Search shows out-of-stock products first', 'medium', 'major', 'Testing', 'arjun', 'rahul', -6, 'Staging · All browsers',
                "1. Search for \"running shoes\"", 'In-stock products first.', 'Out-of-stock items appear at the top.', null],
            ['Mega menu overlaps the hero banner at 1024px', 'low', 'minor', 'Resolved', 'ananya', 'rahul', -12, 'iPad landscape · Safari',
                "1. Open the home page at 1024px width\n2. Hover \"Men\"", 'Menu opens below the header.', 'Menu covers the banner text.', 'Raised the header z-index and constrained the panel width at the md breakpoint.'],
            ['Filter chips do not reset after browser back', 'low', 'minor', 'Closed', 'arjun', 'rahul', -15, 'Staging · Chrome',
                "1. Apply two filters\n2. Open a product\n3. Press Back", 'The selected filters are shown.', 'Chips show none selected while results stay filtered.', 'Filters are now read from the URL on every navigation.'],
        ]);

        $categories = $this->budget($p, 'priya', 48000, [
            ['Development', 30000, '#3b82f6', 'Engineering effort across sprints.'],
            ['UX & design', 8000, '#8b5cf6', 'Research, design system and UI design.'],
            ['QA & testing', 5000, '#f59e0b', 'Test planning, device lab and automation.'],
            ['Infrastructure & tools', 3000, '#10b981', 'Hosting, staging and tooling licences.'],
            ['Contingency', 2000, '#6b7280', 'Held back for change requests.'],
        ]);
        $this->expenses($p, $categories, [
            ['Figma Professional — 3 seats, 3 months', 'UX & design', 450, 'ananya', 'priya', 'approved', -30, 'Figma', 'Design seats for the BrightCart redesign.', 'Approved.'],
            ['Stock product photography licence', 'UX & design', 1200, 'ananya', 'priya', 'approved', -24, 'Shutterstock', 'Lifestyle images for category banners.', 'Approved — within the design budget.'],
            ['AWS staging environment — last month', 'Infrastructure & tools', 820, 'arjun', 'priya', 'approved', -12, 'Amazon Web Services', 'EC2, RDS and CloudFront for staging.', 'Approved.'],
            ['BrowserStack Automate — 1 parallel', 'QA & testing', 390, 'rahul', 'priya', 'pending', -2, 'BrowserStack', 'Cross-browser runs for the checkout regression suite.', null],
            ['k6 Cloud load-testing credits', 'QA & testing', 260, 'rahul', 'priya', 'requires_info', -4, 'Grafana Labs', 'Load test before the holiday sale.', 'Please attach the Grafana Labs invoice.'],
        ]);

        $this->invoice($p, 'daniel', 'priya', 'Milestone 1 — Discovery & UX design', -24, -9, 'paid', 0, [
            ['UX research, workshops and information architecture', 4000, null],
            ['Design system, wireframes and UI design', 8000, null],
        ], 'Thank you for the prompt payment.');
        $this->invoice($p, 'daniel', 'priya', 'Development — Sprints 1–2 (catalog)', -6, 9, 'viewed', 0, [
            ['Storefront setup and CI pipeline', 4000, 'b05'],
            ['Product listing with filters', 6000, 'b07'],
            ['Product detail pages and search', 6000, 'b08'],
        ], 'Covers the catalog delivered in sprints 1 and 2.');
        $this->invoice($p, 'daniel', 'priya', 'Development — Sprint 3 (cart & checkout)', 0, 15, 'draft', 0, [
            ['Shopping cart and coupons', 5500, 'b13'],
            ['Checkout flow', 3500, 'b15'],
        ], 'Draft — to be sent at the sprint 3 review.');

        $this->contract($p, 'daniel', 'priya', 'Service Agreement', 'BrightCart — Master Services Agreement', 48000, 'accept', -38, 330, ['priya'],
            'Design and development of the BrightCart online store, billed per milestone.');
    }

    // ── FinEdge (Neha) ───────────────────────────────────────────────────────

    private function seedFinEdge(): void
    {
        $p = $this->project('finedge', $this->agency, 'neha', [
            'title' => 'FinEdge Mobile Banking App',
            'description' => 'iOS and Android banking app for FinEdge Bank: digital onboarding with e-KYC, accounts, transfers, bill payments and card controls, built to RBI and OWASP MASVS guidelines.',
            'status' => 'active', 'priority' => 'urgent', 'start' => -29, 'deadline' => 70, 'hours' => 1400, 'budget' => 72000,
        ], ['neha' => 'manager', 'vikram' => 'member', 'ananya' => 'member', 'rahul' => 'member', 'arjun' => 'member'], ['sarah']);

        $this->milestones($p, 'neha', [
            ['f-m1', 'Onboarding & KYC', 'completed', -12, 'Sign-up, e-KYC and biometric login approved by FinEdge compliance.'],
            ['f-m2', 'Accounts, transfers & bill pay', 'in_progress', 28, 'Everyday banking: balances, history, transfers and bill payments.'],
            ['f-m3', 'Security audit & store release', 'pending', 70, 'Penetration test, fixes and App Store / Play Store release.'],
        ]);

        $this->tasks($p, 'neha', [
            ['f01', 'Splash, onboarding carousel & sign-up', 'vikram', 'Done', 5, 'high', 'f-m1', -27, -22],
            ['f02', 'e-KYC with Aadhaar & PAN verification', 'vikram', 'Done', 8, 'critical', 'f-m1', -26, -15],
            ['f03', 'Biometric login (Face ID & fingerprint)', 'vikram', 'Done', 5, 'high', 'f-m1', -22, -14],
            ['f04', 'App design: onboarding & KYC screens', 'ananya', 'Done', 3, 'high', 'f-m1', -27, -22],
            ['f05', 'Security requirements checklist', 'rahul', 'Done', 2, 'medium', 'f-m1', -26, -18],
            ['f06', 'Accounts dashboard with balance cards', 'vikram', 'In Progress', 5, 'high', 'f-m2', -12, 1],
            ['f07', 'Fund transfer via IMPS, NEFT & UPI', 'vikram', 'To Do', 8, 'critical', 'f-m2', -6, 3],
            ['f08', 'Transaction history with filters & search', 'arjun', 'In Progress', 5, 'high', 'f-m2', -12, 2],
            ['f09', 'Bill payments: electricity & mobile recharge', 'arjun', 'To Do', 5, 'medium', 'f-m2', -3, 3],
            ['f10', 'Push notifications for every transaction', 'vikram', 'Review', 3, 'medium', 'f-m2', -12, 1],
            ['f11', 'OWASP MASVS security test plan', 'rahul', 'In Progress', 3, 'high', 'f-m3', -12, 2],
            ['f12', 'App design: transfers & bill pay', 'ananya', 'Done', 3, 'high', 'f-m2', -16, -5],
            ['f13', 'Card controls: freeze card & set limits', null, 'To Do', 5, 'medium', 'f-m2', null, null],
            ['f14', 'Spending analytics charts', null, 'To Do', 5, 'low', 'f-m2', null, null],
            ['f15', 'Dark mode', 'ananya', 'To Do', 3, 'low', 'f-m2', null, null],
            ['f16', 'App Store & Play Store listing assets', 'ananya', 'To Do', 2, 'medium', 'f-m3', null, null],
            ['f17', 'Fixes from the penetration test', 'rahul', 'To Do', 8, 'high', 'f-m3', null, null],
        ]);

        $this->sprint($p, 'neha', 'Sprint 1', 'New customers can sign up, complete KYC and log in with biometrics.', -27, -14, 'completed', ['f01', 'f02', 'f03', 'f04', 'f05']);
        $this->sprint($p, 'neha', 'Sprint 2', 'See balances and history, and send money to a saved beneficiary.', -12, 3, 'active', ['f06', 'f07', 'f08', 'f09', 'f10', 'f11', 'f12']);

        $this->comment('f06', 'neha', -9, '12:30', 'Design review done — use the new card style from Ananya\'s Figma file, not the old gradient.');
        $this->comment('f06', 'vikram', -9, '14:05', 'Will do. Balances refresh on pull-to-refresh and every 60 seconds while the screen is open.');
        $this->comment('f07', 'sarah', -4, '18:10', 'Reminder from our compliance team: new beneficiaries need a 30-minute cooling period with a ₹25,000 cap.');
        $this->comment('f07', 'neha', -3, '09:40', 'Noted, Sarah — added to the acceptance criteria.');

        $this->bugs($p, 'f-m2', [
            ['Biometric login fails on Android 14 (Pixel 8)', 'critical', 'blocker', 'In Progress', 'vikram', 'rahul', -4, 'Pixel 8 · Android 14 · app 0.6.2',
                "1. Enable fingerprint login\n2. Kill the app\n3. Open it and touch the sensor", 'User is logged in.', 'The prompt closes and the PIN screen shows "Authentication failed".', null],
            ['OTP auto-read not working on Xiaomi devices', 'high', 'major', 'New', 'vikram', 'rahul', -2, 'Redmi Note 13 · MIUI 14',
                "1. Start sign-up\n2. Wait for the OTP SMS", 'The OTP fills in automatically.', 'User has to type it.', null],
            ['Balance not refreshed after a successful transfer', 'medium', 'major', 'Testing', 'arjun', 'rahul', -5, 'iOS 18 · TestFlight build 0.6.1',
                "1. Transfer ₹500\n2. Return to the dashboard", 'New balance shows straight away.', 'Old balance until the app is reopened.', null],
            ['KYC screen text cut off at the largest font size', 'low', 'minor', 'Resolved', 'ananya', 'rahul', -16, 'iPhone SE · Larger Text on',
                "1. Set the largest text size\n2. Open the KYC screen", 'Text wraps.', 'Labels are truncated.', 'Switched labels to multi-line and tested with Dynamic Type.'],
        ]);

        $categories = $this->budget($p, 'neha', 72000, [
            ['Development', 45000, '#3b82f6', 'iOS and Android engineering.'],
            ['UX & design', 7000, '#8b5cf6', 'App design and prototypes.'],
            ['Security & QA', 12000, '#ef4444', 'Security testing and penetration test.'],
            ['Devices & licences', 5000, '#10b981', 'Test devices, developer programs, tools.'],
            ['Contingency', 3000, '#6b7280', 'Held back for compliance changes.'],
        ]);
        $this->expenses($p, $categories, [
            ['Apple Developer Program — annual', 'Devices & licences', 99, 'vikram', 'neha', 'approved', -26, 'Apple', 'Needed for TestFlight builds.', 'Approved.'],
            ['Firebase Blaze plan — last month', 'Devices & licences', 140, 'vikram', 'neha', 'approved', -10, 'Google Cloud', 'Push notifications and crash reporting.', 'Approved.'],
            ['MobSF security scanner licence', 'Security & QA', 300, 'rahul', 'neha', 'approved', -18, 'MobSF', 'Static analysis for every release build.', 'Approved.'],
            ['Two Android test devices (Pixel 8, Redmi Note 13)', 'Devices & licences', 900, 'vikram', 'neha', 'pending', -1, 'Croma', 'Needed to reproduce the biometric and OTP bugs.', null],
        ]);

        $this->invoice($p, 'sarah', 'neha', 'Mobilisation advance (20%)', -29, -15, 'paid', 18, [
            ['Advance on the mobile banking app engagement', 14400, null],
        ], 'Advance as per clause 4 of the agreement.');
        $this->invoice($p, 'sarah', 'neha', 'Milestone 1 — Onboarding & KYC', -18, -4, 'overdue', 18, [
            ['Sign-up and onboarding flow', 5000, 'f01'],
            ['e-KYC integration (Aadhaar & PAN)', 9000, 'f02'],
            ['Biometric login', 4000, 'f03'],
        ], 'Milestone 1 accepted by FinEdge compliance.');

        $this->contract($p, 'sarah', 'neha', 'NDA', 'FinEdge — Mutual NDA', 0, 'accept', -40, 690, ['neha'],
            'Confidentiality for banking systems, customer data and security findings.');
        $this->contract($p, 'sarah', 'neha', 'Service Agreement', 'FinEdge — Mobile App Development Agreement', 72000, 'sent', -1, 120, ['neha', 'owner'],
            'Fixed-price delivery of the FinEdge mobile banking app in three milestones. Sent for signature.');
    }

    // ── CRM integration (Priya) — waiting for its first sprint ───────────────

    private function seedCrmIntegration(): void
    {
        $p = $this->project('crm', $this->agency, 'priya', [
            'title' => 'Salesforce CRM Integration',
            'description' => 'Connect the new BrightCart store to BrightCart\'s Salesforce org: customers, orders and account owners kept in sync every night.',
            'status' => 'planning', 'priority' => 'medium', 'start' => 5, 'deadline' => 60, 'hours' => 240, 'budget' => 18000,
        ], ['priya' => 'manager', 'arjun' => 'member', 'rahul' => 'member'], ['daniel']);

        $this->milestones($p, 'priya', [
            ['c-m1', 'Integration design sign-off', 'pending', 12, 'Field mapping and sync rules agreed with BrightCart\'s Salesforce admin.'],
            ['c-m2', 'Sync engine & go-live', 'pending', 60, 'Nightly sync in production with monitoring.'],
        ]);
        $this->tasks($p, 'priya', [
            ['c01', 'Map Salesforce objects to store data', 'arjun', 'To Do', 3, 'high', 'c-m1', 5, 8],
            ['c02', 'OAuth 2.0 connection to Salesforce', 'arjun', 'To Do', 5, 'high', 'c-m2', 8, 14],
            ['c03', 'Nightly sync: customers & orders', 'arjun', 'To Do', 8, 'high', 'c-m2', 14, 28],
            ['c04', 'Failed-sync dashboard & alerts', null, 'To Do', 5, 'medium', 'c-m2', null, null],
            ['c05', 'Integration tests against the Salesforce sandbox', 'rahul', 'To Do', 3, 'medium', 'c-m2', 20, 30],
        ]);
        $this->contract($p, 'daniel', 'priya', 'Retainer', 'BrightCart — CRM Integration Statement of Work', 18000, 'pending', 5, 95, ['priya'],
            'Statement of work for the Salesforce integration. Draft for internal review.');
    }

    // ── Finished and paused projects ─────────────────────────────────────────

    private function seedWebsiteRedesign(): void
    {
        $p = $this->project('website', $this->agency, 'owner', [
            'title' => 'Nimbus Website Redesign',
            'description' => 'New nimbusdigital.in: refreshed brand, case studies and a faster marketing site.',
            'status' => 'completed', 'priority' => 'medium', 'start' => -120, 'deadline' => -45, 'hours' => 320,
        ], ['priya' => 'manager', 'ananya' => 'member', 'arjun' => 'member']);

        $this->milestones($p, 'priya', [
            ['w-m1', 'Brand & design', 'completed', -95, 'Brand guidelines and page designs.'],
            ['w-m2', 'Build & launch', 'completed', -45, 'Site built, content migrated, launched.'],
        ]);
        $this->tasks($p, 'priya', [
            ['w01', 'Brand guidelines & homepage design', 'ananya', 'Done', 5, 'high', 'w-m1', -118, -104],
            ['w02', 'Case studies section design', 'ananya', 'Done', 3, 'medium', 'w-m1', -105, -96],
            ['w03', 'Build the marketing site in Next.js', 'arjun', 'Done', 8, 'high', 'w-m2', -95, -70],
            ['w04', 'Blog migration from WordPress', 'arjun', 'Done', 5, 'medium', 'w-m2', -72, -58],
            ['w05', 'SEO metadata, sitemap & redirects', 'arjun', 'Done', 3, 'medium', 'w-m2', -60, -50],
            ['w06', 'Launch checklist & go-live', 'priya', 'Done', 2, 'high', 'w-m2', -50, -46],
        ]);
    }

    private function seedHrPortal(): void
    {
        $p = $this->project('hr', $this->agency, 'neha', [
            'title' => 'HR Portal Maintenance',
            'description' => 'Upgrades and fixes for an internal HR portal. On hold while the client renews the support contract.',
            'status' => 'on_hold', 'priority' => 'low', 'start' => -60, 'deadline' => 25, 'hours' => 120,
        ], ['neha' => 'manager', 'vikram' => 'member', 'rahul' => 'member']);

        $this->milestones($p, 'neha', [
            ['h-m1', 'Q4 maintenance release', 'in_progress', 25, 'Framework upgrade and the open payroll fixes.'],
        ]);
        $this->tasks($p, 'neha', [
            ['h01', 'Upgrade from Laravel 10 to 12', 'vikram', 'Done', 5, 'high', 'h-m1', -55, -45],
            ['h02', 'Fix leave balance for part-time employees', 'vikram', 'In Progress', 3, 'medium', 'h-m1', -40, 12],
            ['h03', 'Payslip PDF formatting issues', null, 'To Do', 2, 'low', 'h-m1', null, null],
            ['h04', 'Regression pass after the upgrade', 'rahul', 'To Do', 3, 'medium', 'h-m1', 5, 15],
        ]);
        $this->bugs($p, 'h-m1', [
            ['Leave balance wrong for employees who joined mid-month', 'medium', 'major', 'New', 'vikram', 'neha', -20, 'Production',
                "1. Open the leave page for an employee who joined on the 18th", 'Pro-rated balance.', 'Full month credited.', null],
        ]);
    }

    private function seedLab(): void
    {
        $this->seedingWorkspaceId = $this->lab->id;
        $p = $this->project('lab', $this->lab, 'priya', [
            'title' => 'AI Support Assistant (proof of concept)',
            'description' => 'Can an AI assistant answer the top support questions from our help-centre content? Two-week experiment.',
            'status' => 'active', 'priority' => 'medium', 'start' => -8, 'deadline' => 10, 'hours' => 60, 'visibility' => 'workspace',
        ], ['priya' => 'manager', 'arjun' => 'member']);

        $this->milestones($p, 'priya', [['l-m1', 'Demo to leadership', 'in_progress', 10, 'Share results and a go / no-go recommendation.']]);
        $this->tasks($p, 'priya', [
            ['l01', 'Collect the top 50 support questions', 'priya', 'Done', 2, 'medium', 'l-m1', -8, -6],
            ['l02', 'Prototype answers from help-centre articles', 'arjun', 'In Progress', 5, 'high', 'l-m1', -5, 4],
            ['l03', 'Score answer quality with the support team', null, 'To Do', 3, 'medium', 'l-m1', 5, 9],
        ]);
        $this->seedingWorkspaceId = $this->agency->id;
    }

    // ── Timesheets ───────────────────────────────────────────────────────────

    /**
     * Four weeks of time per team member, Monday to Friday (this week up to
     * yesterday): two older weeks approved, last week waiting for approval
     * (or approved/rejected), this week still a draft.
     */
    private function seedTimesheets(): void
    {
        $plans = [
            // person => [approver, hourly rate, weeks ago => [status, [[task, hours], [task, hours]]]]
            'arjun' => ['priya', 40, [
                3 => ['approved', [['b05', 3.5], ['b07', 4]]],
                2 => ['approved', [['b07', 4], ['b08', 3.5]]],
                1 => ['submitted', [['b09', 4], ['f08', 3.5]]],
                0 => ['draft', [['b13', 4], ['f08', 3.5]]],
            ]],
            'vikram' => ['neha', 42, [
                3 => ['approved', [['f02', 4], ['f03', 3.5]]],
                2 => ['approved', [['f03', 4], ['f02', 3.5]]],
                1 => ['submitted', [['f06', 4], ['f10', 3.5]]],
                0 => ['draft', [['f07', 4], ['f06', 3.5]]],
            ]],
            'ananya' => ['priya', 35, [
                3 => ['approved', [['b04', 4], ['f04', 3.5]]],
                2 => ['approved', [['b10', 4], ['f12', 3.5]]],
                1 => ['approved', [['f12', 4], ['b16', 3.5]]],
                0 => ['draft', [['b17', 4], ['b16', 2.5]]],
            ]],
            'rahul' => ['priya', 30, [
                3 => ['approved', [['f05', 4], ['b11', 3.5]]],
                2 => ['rejected', [['b11', 4], ['f05', 3.5]]],
                1 => ['submitted', [['f11', 4], ['b11', 3]]],
                0 => ['draft', [['b18', 3.5], ['f11', 4]]],
            ]],
        ];

        $thisMonday = $this->today->copy()->startOfWeek(Carbon::MONDAY);
        foreach ($plans as $person => [$approverKey, $rate, $weeks]) {
            $user = $this->people[$person];
            $approver = $this->people[$approverKey];
            foreach ($weeks as $weeksAgo => [$status, $split]) {
                $monday = $thisMonday->copy()->subWeeks($weeksAgo);
                $days = collect(range(0, 4))->map(fn ($i) => $monday->copy()->addDays($i))->filter(fn (Carbon $d) => $d->lt($this->today));
                if ($days->isEmpty()) {
                    continue;
                }

                $friday = $monday->copy()->addDays(4)->setTime(18, 0);
                $timesheet = $this->as($user, $monday->copy()->setTime(9, 15), fn () => Timesheet::forceCreate([
                    'user_id' => $user->id, 'workspace_id' => $this->agency->id,
                    'start_date' => $monday->toDateString(), 'end_date' => $monday->copy()->addDays(6)->toDateString(),
                    'status' => 'draft', 'total_hours' => 0, 'billable_hours' => 0,
                ]));

                $total = 0;
                foreach ($days as $date) {
                    $clock = $date->copy()->setTime(9, 30);
                    foreach ($split as $i => [$taskKey, $hours]) {
                        $task = $this->tasks[$taskKey];
                        $start = $clock->copy();
                        $end = $start->copy()->addMinutes((int) round($hours * 60));
                        TimesheetEntry::forceCreate([
                            'timesheet_id' => $timesheet->id, 'project_id' => $task->project_id, 'task_id' => $task->id, 'user_id' => $user->id,
                            'date' => $date->toDateString(), 'start_time' => $start->format('H:i:s'), 'end_time' => $end->format('H:i:s'),
                            'hours' => $hours, 'description' => $task->title, 'is_billable' => true, 'hourly_rate' => $rate,
                        ]);
                        $total += $hours;
                        $clock = $end->copy()->addMinutes($i === 0 ? 45 : 15); // lunch after the first block
                    }
                }

                $reviewed = in_array($status, ['approved', 'rejected'], true);
                $timesheet->forceFill([
                    'status' => $status, 'total_hours' => $total, 'billable_hours' => $total,
                    'submitted_at' => $status === 'draft' ? null : $friday,
                    'approved_at' => $status === 'approved' ? $friday->copy()->addDays(3)->setTime(10, 30) : null,
                    'approved_by' => $status === 'approved' ? $approver->id : null,
                    'notes' => $status === 'draft' ? null : 'Week of ' . $monday->format('j M'),
                ])->saveQuietly();

                if ($status !== 'draft') {
                    TimesheetApproval::forceCreate([
                        'timesheet_id' => $timesheet->id, 'approver_id' => $approver->id,
                        'status' => $status === 'submitted' ? 'pending' : $status,
                        'comments' => $status === 'rejected' ? 'Please split the regression testing hours by test run so we can bill them to the right project.' : ($status === 'approved' ? 'Thanks — approved.' : null),
                        'approved_at' => $reviewed ? $friday->copy()->addDays(3)->setTime(10, 30) : null,
                    ]);
                }
            }
        }
    }

    // ── Meetings ─────────────────────────────────────────────────────────────

    private function seedMeetings(): void
    {
        $zoom = [
            ['brightcart', 'priya', 'BrightCart · Sprint 3 planning', 'Pick the sprint 3 scope and split the checkout work.', -6, '10:00', 60, 'ended', 'scheduled', ['priya', 'arjun', 'ananya', 'rahul']],
            ['brightcart', 'priya', 'BrightCart · Sprint 3 review & demo', 'Demo cart, coupons and checkout to BrightCart and agree the sprint 4 scope.', 7, '15:00', 45, 'scheduled', 'scheduled', ['priya', 'arjun', 'ananya', 'rahul', 'daniel']],
            ['finedge', 'neha', 'FinEdge · Weekly status call', 'Progress, risks and decisions needed from FinEdge.', 3, '11:00', 30, 'scheduled', 'recurring', ['neha', 'vikram', 'sarah']],
            ['finedge', 'neha', 'FinEdge · Sprint 2 planning', 'Scope transfers and bill pay.', -12, '10:30', 60, 'ended', 'scheduled', ['neha', 'vikram', 'arjun', 'ananya', 'rahul']],
        ];
        foreach ($zoom as $i => [$projectKey, $host, $title, $description, $day, $time, $minutes, $status, $type, $attendees]) {
            $start = $this->day($day, $time);
            $meeting = $this->as($this->people[$host], $this->day(min($day, 0) - 2, '17:00'), fn () => ZoomMeeting::forceCreate([
                'zoom_meeting_id' => (string) (84000000000 + $i * 1111111), 'title' => $title, 'description' => $description,
                'start_time' => $start, 'end_time' => $start->copy()->addMinutes($minutes), 'timezone' => config('app.timezone', 'UTC'),
                'duration' => $minutes, 'join_url' => 'https://zoom.us/j/' . (84000000000 + $i * 1111111), 'start_url' => null,
                'password' => 'nimbus' . ($i + 1), 'attendees' => array_map(fn ($key) => $this->people[$key]->id, $attendees),
                'status' => $status, 'type' => $type, 'user_id' => $this->people[$host]->id, 'workspace_id' => $this->agency->id,
                'project_id' => $this->projects[$projectKey]->id,
            ]));
            foreach ($attendees as $key) {
                DB::table('zoom_meeting_members')->insert(['zoom_meeting_id' => $meeting->id, 'user_id' => $this->people[$key]->id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        $google = [
            ['brightcart', 'priya', 'Checkout UX walkthrough with BrightCart', 'Walk Daniel through the checkout prototype and collect feedback.', -3, '16:00', 45, 'ended', ['priya', 'ananya', 'arjun', 'daniel']],
            ['finedge', 'neha', 'FinEdge · Security review', 'Review the MASVS test plan with FinEdge\'s security team.', 2, '11:30', 60, 'scheduled', ['neha', 'rahul', 'vikram', 'sarah']],
            [null, 'owner', 'Nimbus monthly all-hands', 'Company update, project highlights and Q4 priorities.', 5, '17:00', 45, 'scheduled', ['owner', 'priya', 'neha', 'arjun', 'vikram', 'ananya', 'rahul']],
        ];
        foreach ($google as $i => [$projectKey, $host, $title, $description, $day, $time, $minutes, $status, $attendees]) {
            $start = $this->day($day, $time);
            $meeting = $this->as($this->people[$host], $this->day(min($day, 0) - 2, '17:30'), fn () => GoogleMeeting::forceCreate([
                'title' => $title, 'description' => $description, 'start_time' => $start, 'end_time' => $start->copy()->addMinutes($minutes),
                'duration' => $minutes, 'join_url' => 'https://meet.google.com/' . ['nbx-qwer-tyu', 'fin-sect-rev', 'all-hand-nmb'][$i],
                'status' => $status, 'type' => 'scheduled', 'user_id' => $this->people[$host]->id, 'workspace_id' => $this->agency->id,
                'project_id' => $projectKey ? $this->projects[$projectKey]->id : null,
            ]));
            foreach ($attendees as $key) {
                DB::table('google_meeting_members')->insert(['google_meeting_id' => $meeting->id, 'user_id' => $this->people[$key]->id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    // ── Notes ────────────────────────────────────────────────────────────────

    private function seedNotes(): void
    {
        $ids = fn (array $keys) => implode(',', array_map(fn ($key) => $this->people[$key]->id, $keys));
        $notes = [
            ['owner', 'Q4 priorities', "1. Launch BrightCart before the holiday sale.\n2. Pass the FinEdge security audit first time.\n3. Start the Salesforce integration on schedule.\n4. Hire one more mobile developer.", '#FFEAA7', 'shared', ['priya', 'neha']],
            ['priya', 'Sprint 3 focus — BrightCart', "Goal: cart → checkout end to end on staging by Friday.\n• Coupons must not stack with sale prices\n• Razorpay keys still pending from BrightCart\n• Regression suite before the review", '#4ECDC4', 'shared', ['arjun', 'ananya', 'rahul']],
            ['neha', 'FinEdge release checklist', "□ Penetration test booked\n□ Store listing copy approved by FinEdge marketing\n□ Crash-free sessions above 99.5% in TestFlight\n□ RBI data-localisation sign-off", '#BB8FCE', 'personal', []],
            ['arjun', 'Coupon edge cases', "• Percentage + sale price stacking\n• Expiry in the store's time zone, saved in UTC\n• One use per customer, also for guest checkout (by email)", '#85C1E9', 'personal', []],
            ['ananya', 'Design QA — checkout', "• Error states for every field\n• Delivery slot picker on small screens\n• Focus order for keyboard users", '#F7DC6F', 'personal', []],
        ];
        foreach ($notes as $i => [$author, $title, $text, $color, $type, $sharedWith]) {
            $this->as($this->people[$author], $this->day(-6 + $i, '18:00'), fn () => Note::forceCreate([
                'title' => $title, 'text' => $text, 'color' => $color, 'type' => $type,
                'assign_to' => $sharedWith ? $ids($sharedWith) : null, 'workspace' => $this->agency->id, 'created_by' => $this->people[$author]->id,
            ]));
        }
    }

    // ── Other companies (superadmin view) ────────────────────────────────────

    private function seedOtherCompanies(): void
    {
        $superadmin = User::where('type', 'superadmin')->orderBy('id')->first();
        $plans = Plan::all()->keyBy('name');

        $companies = [
            ['maya.chen@test.com', 'Maya Chen', 'Orbit Logistics', 'Starter', [
                ['Fleet Tracking Dashboard', 'active', 'high', ['Live vehicle map', 'Geofence alerts', 'Driver hours report', 'Fuel usage trends']],
                ['Driver Mobile App', 'planning', 'medium', ['Proof-of-delivery photos', 'Offline route sync']],
            ]],
            ['lucas.fernandes@test.com', 'Lucas Fernandes', 'GreenLeaf Foods', 'Free', [
                ['Online Ordering Portal', 'active', 'high', ['Menu management', 'Table reservations', 'Order tracking page']],
            ]],
        ];

        foreach ($companies as [$email, $name, $company, $planName, $projects]) {
            $plan = $plans[$planName] ?? Plan::where('is_default', true)->first();
            $owner = User::where('email', $email)->first();
            if (!$owner) {
                $owner = User::forceCreate([
                    'name' => $name, 'email' => $email, 'password' => $this->demoHash, 'type' => 'company', 'email_verified_at' => now(),
                    'lang' => 'en', 'plan_id' => $plan?->id, 'plan_is_active' => 1, 'is_enable_login' => 1,
                ]);
                defaultRoleAndSetting($owner);
                $owner->refresh();
            }
            $owner->forceFill(['name' => $name, 'plan_id' => $plan?->id, 'plan_is_active' => 1, 'plan_expire_date' => $this->day(200)])->save();
            $workspace = Workspace::withoutGlobalScopes()->where('owner_id', $owner->id)->orderBy('id')->first();
            if (!$workspace) {
                continue;
            }
            $workspace->forceFill(['name' => $company])->save();
            if (!$owner->current_workspace_id) {
                $owner->forceFill(['current_workspace_id' => $workspace->id])->save();
            }
            $this->seedSmallProjects($owner, $workspace, $projects);
        }

        // WorkDo, the seeded demo company, gets a little work too.
        $workdo = User::where('email', 'company@example.com')->first();
        $workdoSpace = $workdo ? Workspace::withoutGlobalScopes()->where('owner_id', $workdo->id)->orderBy('id')->first() : null;
        if ($workdo && $workdoSpace) {
            $this->seedSmallProjects($workdo, $workdoSpace, [['Brand Refresh 2026', 'planning', 'medium', ['Logo exploration', 'Website style guide']]]);
        }

        // Billing history for the superadmin's Plan Orders and Plan Requests.
        $orders = [
            ['company@test.com', 'Pro', 'yearly', -90, 'approved'],
            ['maya.chen@test.com', 'Starter', 'monthly', -40, 'approved'],
            ['lucas.fernandes@test.com', 'Starter', 'monthly', -2, 'pending'],
        ];
        foreach ($orders as $i => [$email, $planName, $cycle, $day, $status]) {
            $user = User::where('email', $email)->first();
            $plan = $plans[$planName] ?? null;
            if (!$user || !$plan) {
                continue;
            }
            $price = $cycle === 'yearly' ? (float) ($plan->yearly_price ?: $plan->price * 10) : (float) $plan->price;
            PlanOrder::forceCreate([
                'user_id' => $user->id, 'plan_id' => $plan->id, 'billing_cycle' => $cycle,
                'order_number' => 'ORD-' . $this->day($day)->format('Ymd') . '-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'original_price' => $price, 'discount_amount' => 0, 'final_price' => $price, 'payment_method' => 'bank',
                'status' => $status, 'ordered_at' => $this->day($day, '11:00'),
                'processed_at' => $status === 'approved' ? $this->day($day + 1, '10:00') : null,
                'processed_by' => $status === 'approved' ? $superadmin?->id : null,
                'notes' => $status === 'pending' ? 'Bank transfer receipt uploaded, waiting for confirmation.' : null,
            ]);
        }
        $lucas = User::where('email', 'lucas.fernandes@test.com')->first();
        if ($lucas && isset($plans['Starter'])) {
            PlanRequest::forceCreate([
                'user_id' => $lucas->id, 'plan_id' => $plans['Starter']->id, 'duration' => 'monthly', 'status' => 'pending',
                'message' => 'We are opening two more outlets and need more team members.', 'created_at' => $this->day(-2, '11:05'),
            ]);
        }
    }

    /** @param  array<int, array{0:string,1:string,2:string,3:string[]}>  $projects */
    private function seedSmallProjects(User $owner, Workspace $workspace, array $projects): void
    {
        $previousWorkspace = $this->seedingWorkspaceId;
        $this->seedingWorkspaceId = $workspace->id;
        $todo = $this->stage($workspace->id, 'To Do');
        $doing = $this->stage($workspace->id, 'In Progress');
        $done = $this->stage($workspace->id, 'Done');

        foreach ($projects as $p => [$title, $status, $priority, $tasks]) {
            $project = $this->as($owner, $this->day(-30 + $p * 5, '10:00'), fn () => Project::forceCreate([
                'workspace_id' => $workspace->id, 'title' => $title, 'description' => $title . ' for ' . $workspace->name . '.',
                'status' => $status, 'priority' => $priority, 'start_date' => $this->day(-28 + $p * 5)->toDateString(),
                'deadline' => $this->day(45 + $p * 10)->toDateString(), 'progress' => 0, 'is_public' => false,
                'visibility' => 'workspace', 'created_by' => $owner->id,
            ]));
            ProjectMember::forceCreate(['project_id' => $project->id, 'user_id' => $owner->id, 'role' => 'owner', 'assigned_at' => $this->day(-30), 'assigned_by' => $owner->id]);
            foreach ($tasks as $t => $taskTitle) {
                $stage = $status === 'planning' ? $todo : ($t === 0 ? $done : ($t === 1 ? $doing : $todo));
                $this->as($owner, $this->day(-25 + $t, '11:00'), fn () => Task::forceCreate([
                    'project_id' => $project->id, 'task_stage_id' => $stage->id, 'title' => $taskTitle, 'priority' => $priority,
                    'story_points' => [3, 5, 2, 3][$t % 4], 'start_date' => $this->day(-20 + $t * 4)->toDateString(),
                    'end_date' => $this->day(5 + $t * 6)->toDateString(), 'assigned_to' => $owner->id, 'created_by' => $owner->id,
                    'progress' => $stage->is_completed ? 100 : ($stage->id === $doing->id ? 50 : 0),
                ]));
            }
            $this->projects['company-' . $project->id] = $project;
        }
        $this->seedingWorkspaceId = $previousWorkspace;
    }

    // ── Output ───────────────────────────────────────────────────────────────

    private function printLogins(): void
    {
        if (!$this->command) {
            return;
        }
        $this->command->info('Demo data ready: Nimbus Digital Solutions (' . count($this->projects) . ' projects, ' . count($this->tasks) . ' agency tasks).');
        $onPanel = array_column(config('demo.accounts', []), 'role', 'email');
        $rows = User::whereIn('email', [...array_keys($onPanel), 'neha.iyer@test.com', 'ananya.rao@test.com', 'rahul.verma@test.com', 'sarah.mitchell@test.com'])
            ->get(['name', 'email', 'type'])
            ->map(fn (User $user) => [$onPanel[$user->email] ?? ucfirst($user->type), $user->name, $user->email, isset($onPanel[$user->email]) ? 'yes' : ''])
            ->all();
        $this->command->table(['Role', 'Name', 'Email', 'On login page'], $rows);
        $this->command->info('Password for every demo login: ' . $this->demoPassword()
            . (config('demo.login_panel') ? '' : '  (login page panel is off: set DEMO_LOGIN_PANEL=true)'));
    }
}
