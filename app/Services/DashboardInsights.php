<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PlanOrder;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\TaskStage;
use App\Models\TimesheetEntry;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The extra figures on each role's dashboard: trends, workload, what's coming
 * up. Every query follows the visibility rule of the page it links to (the
 * Tasks page's Task::visibleTo, Projects' visibleTo, the Meetings pages'
 * MeetingDirectory), so a dashboard never shows more than those pages do.
 */
class DashboardInsights
{
    /** A standard working week; there is no per-workspace setting for it yet. */
    public const WEEKLY_TARGET_HOURS = 40;

    // ── Owner and manager ────────────────────────────────────────────────────

    /** Paid invoices by the month they were paid: the "Received" figure over time. */
    public function revenueByMonth(Workspace $workspace, int $months = 6): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $invoices = Invoice::whereHas('project', fn ($q) => $q->where('workspace_id', $workspace->id))
            ->where('status', 'paid')
            ->where(fn ($q) => $q->where('paid_at', '>=', $start)
                ->orWhere(fn ($q) => $q->whereNull('paid_at')->where('updated_at', '>=', $start)))
            ->get(['total_amount', 'paid_at', 'updated_at']);

        return $this->byMonth($invoices, $start, $months, fn ($invoice) => $invoice->paid_at ?? $invoice->updated_at, fn ($invoice) => (float) $invoice->total_amount);
    }

    /**
     * Open tasks per stage, in workflow order, in the projects this person sees,
     * plus how many were finished this week.
     *
     * @return array{stages: array<int, array{id: int, name: string, count: int}>, done_this_week: int}
     */
    public function tasksByStage(Workspace $workspace, User $user): array
    {
        $visible = fn () => Task::visibleTo($user, false);
        $counts = $visible()->open()->groupBy('task_stage_id')
            ->selectRaw('task_stage_id, count(*) as total')->pluck('total', 'task_stage_id');

        $stages = TaskStage::forWorkspace($workspace->id)->ordered()->where('is_completed', false)->get(['id', 'name'])
            ->map(fn ($stage) => ['id' => $stage->id, 'name' => $stage->name, 'count' => (int) ($counts[$stage->id] ?? 0)])
            ->values()->all();

        return [
            'stages' => $stages,
            'done_this_week' => $this->completed($visible())->where('tasks.updated_at', '>=', now()->startOfWeek())->count(),
        ];
    }

    /**
     * People with open work in the projects this person sees: open and overdue
     * tasks and hours logged this week, busiest first.
     *
     * @return array<int, array{id: int, name: string, avatar: ?string, open: int, overdue: int, hours: float}>
     */
    public function teamWorkload(Workspace $workspace, User $user, int $limit = 8): array
    {
        $tasks = Task::visibleTo($user, false)->open()->whereNotNull('assigned_to')
            ->selectRaw('assigned_to, count(*) as open_count, sum(case when end_date < ? then 1 else 0 end) as overdue_count', [today()->toDateString()])
            ->groupBy('assigned_to')
            ->get()
            ->keyBy('assigned_to');

        $hours = $this->weekEntries($workspace, $user)
            ->selectRaw('user_id, sum(hours) as total')->groupBy('user_id')->pluck('total', 'user_id');

        $ids = $tasks->keys()->merge($hours->keys())->unique()->values();
        $people = User::whereIn('id', $ids)->get(['id', 'name', 'avatar'])->keyBy('id');

        return $ids->filter(fn ($id) => $people->has($id))
            ->map(fn ($id) => [
                'id' => (int) $id,
                'name' => $people[$id]->name,
                'avatar' => check_file($people[$id]->avatar) ? get_file($people[$id]->avatar) : null,
                'open' => (int) ($tasks[$id]->open_count ?? 0),
                'overdue' => (int) ($tasks[$id]->overdue_count ?? 0),
                'hours' => round((float) ($hours[$id] ?? 0), 1),
            ])
            ->sortByDesc(fn ($person) => [$person['open'], $person['hours']])
            ->take($limit)
            ->values()
            ->all();
    }

    /** Hours the team logged this week on the projects this person sees. */
    public function teamHoursThisWeek(Workspace $workspace, User $user): float
    {
        return round((float) $this->weekEntries($workspace, $user)->sum('hours'), 1);
    }

    /**
     * Open tasks due in the next $days days, soonest first.
     *
     * @return array<int, array{id: int, title: string, type: string, due: string, project: ?array, assignee: ?string}>
     */
    public function dueSoon(User $user, int $days = 7, int $limit = 6): array
    {
        return Task::visibleTo($user, false)->open()
            ->whereBetween('end_date', [today()->toDateString(), today()->addDays($days)->toDateString()])
            ->with(['project:id,title', 'assignedTo:id,name'])
            ->orderBy('end_date')
            ->limit($limit)
            ->get()
            ->map(fn ($task) => [
                'id' => $task->id,
                'title' => $task->title,
                'type' => $task->type,
                'due' => $task->end_date?->toDateString(),
                'project' => $task->project?->only('id', 'title'),
                'assignee' => $task->assignedTo?->name,
            ])
            ->all();
    }

    /**
     * Active projects with a budget: spent against the total, fullest first.
     *
     * @return array<int, array{project: array, total: float, spent: float, used: float}>
     */
    public function budgetBurn(Workspace $workspace, int $limit = 5): array
    {
        return ProjectBudget::where('workspace_id', $workspace->id)
            ->where('total_budget', '>', 0)
            ->whereHas('project', fn ($q) => $q->where('workspace_id', $workspace->id)->whereNotIn('status', ['completed', 'cancelled']))
            ->with('project:id,title')
            ->get()
            ->map(fn ($budget) => [
                'project' => $budget->project->only('id', 'title'),
                'total' => (float) $budget->total_budget,
                'spent' => round((float) $budget->total_spent, 2),
                'used' => round((float) $budget->utilization_percentage, 1),
            ])
            ->sortByDesc('used')
            ->take($limit)
            ->values()
            ->all();
    }

    // ── Everyone in a workspace ──────────────────────────────────────────────

    /**
     * Meetings starting in the next $days days that this person may see.
     *
     * @return array<int, array<string, mixed>>
     */
    public function upcomingMeetings(User $user, int $days = 7, int $limit = 5): array
    {
        $until = now()->addDays($days);

        return app(MeetingDirectory::class)->forUser($user)
            ->filter(fn ($meeting) => $meeting['start_time'] && $meeting['status'] !== 'ended'
                // Still listed while it's running (up to its length after it started).
                && Carbon::parse($meeting['start_time'])->addMinutes(max(30, $meeting['duration']))->isFuture()
                && Carbon::parse($meeting['start_time'])->lte($until))
            ->take($limit)
            ->map(fn ($meeting) => collect($meeting)->only(['key', 'provider', 'title', 'project', 'start_time', 'duration', 'join_url', 'show_url'])->all())
            ->values()
            ->all();
    }

    // ── Member ───────────────────────────────────────────────────────────────

    /**
     * This person's hours per day this week (Monday to Sunday).
     *
     * @return array{days: array<int, array{date: string, label: string, hours: float, today: bool}>, total: float, target: int}
     */
    public function myWeek(Workspace $workspace, User $user): array
    {
        $start = now()->startOfWeek();
        $hours = TimesheetEntry::where('user_id', $user->id)
            ->whereBetween('date', [$start->toDateString(), now()->endOfWeek()->toDateString()])
            ->whereHas('timesheet', fn ($q) => $q->where('workspace_id', $workspace->id))
            ->get(['date', 'hours'])
            ->groupBy(fn ($entry) => Carbon::parse($entry->date)->toDateString())
            ->map(fn ($entries) => (float) $entries->sum('hours'));

        $days = collect(range(0, 6))->map(function ($offset) use ($start, $hours) {
            $day = $start->copy()->addDays($offset);

            return [
                'date' => $day->toDateString(),
                'label' => $day->translatedFormat('D'),
                'hours' => round($hours[$day->toDateString()] ?? 0, 1),
                'today' => $day->isToday(),
            ];
        })->all();

        return ['days' => $days, 'total' => round($hours->sum(), 1), 'target' => self::WEEKLY_TARGET_HOURS];
    }

    /** Tasks assigned to this person that were finished this week. */
    public function myCompletedThisWeek(User $user): int
    {
        return $this->completed(Task::visibleTo($user))
            ->where('assigned_to', $user->id)
            ->where('tasks.updated_at', '>=', now()->startOfWeek())
            ->count();
    }

    // ── Client ───────────────────────────────────────────────────────────────

    /**
     * Unfinished milestones of the client's projects, soonest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function clientMilestones(Workspace $workspace, User $client, int $limit = 6): array
    {
        return ProjectMilestone::whereHas('project', fn ($q) => $q->where('workspace_id', $workspace->id)
                ->whereHas('clients', fn ($c) => $c->where('user_id', $client->id)))
            ->where('status', '!=', 'completed')
            ->with('project:id,title')
            ->orderByRaw('due_date IS NULL, due_date asc')
            ->limit($limit)
            ->get()
            ->map(fn ($milestone) => [
                'id' => $milestone->id,
                'title' => $milestone->title,
                'project' => $milestone->project?->only('id', 'title'),
                'due' => $milestone->due_date?->toDateString(),
                'is_overdue' => $milestone->due_date && $milestone->due_date->isPast() && !$milestone->due_date->isToday(),
                'progress' => (int) $milestone->progress,
            ])
            ->all();
    }

    /**
     * Contracts sent to this client and waiting for their answer.
     *
     * @return array<int, array<string, mixed>>
     */
    public function contractsToSign(Workspace $workspace, User $client): array
    {
        return Contract::where('workspace_id', $workspace->id)
            ->where('client_id', $client->id)
            ->where('status', 'sent')
            ->orderByDesc('sent_at')
            ->get(['id', 'contract_id', 'subject', 'contract_value', 'currency', 'end_date', 'sent_at'])
            ->map(fn ($contract) => [
                'id' => $contract->id,
                'reference' => $contract->contract_id,
                'subject' => $contract->subject,
                'value' => $contract->contract_value !== null ? (float) $contract->contract_value : null,
                'currency' => $contract->currency,
                'sent' => $contract->sent_at ? Carbon::parse($contract->sent_at)->toDateString() : null,
            ])
            ->all();
    }

    // ── Platform admin ───────────────────────────────────────────────────────

    /** Paid plan orders by month. */
    public function platformRevenueByMonth(int $months = 6): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $orders = PlanOrder::whereIn('status', ['approved', 'completed', 'paid'])
            ->where('created_at', '>=', $start)
            ->get(['final_price', 'created_at']);

        return $this->byMonth($orders, $start, $months, fn ($order) => $order->created_at, fn ($order) => (float) $order->final_price);
    }

    /** New company accounts by month. */
    public function signupsByMonth(int $months = 6): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $companies = $this->tenants()->where('created_at', '>=', $start)->get(['created_at']);

        return $this->byMonth($companies, $start, $months, fn ($company) => $company->created_at, fn () => 1);
    }

    /**
     * Companies with an active plan, per plan, most used first.
     *
     * @return array<int, array{name: string, count: int}>
     */
    public function planMix(): array
    {
        $counts = $this->tenants()->where('plan_is_active', 1)
            ->selectRaw('plan_id, count(*) as total')->groupBy('plan_id')->pluck('total', 'plan_id');
        $names = Plan::whereIn('id', $counts->keys()->filter())->pluck('name', 'id');

        return $counts->map(fn ($total, $planId) => ['name' => $names[$planId] ?? __('No plan'), 'count' => (int) $total])
            ->sortByDesc('count')->values()->all();
    }

    /**
     * Trials ending and paid plans renewing in the next $days days, soonest first.
     *
     * @return array<int, array{id: int, name: string, email: string, plan: ?string, kind: string, date: string}>
     */
    public function expiringSoon(int $days = 14, int $limit = 6): array
    {
        $window = [now(), now()->addDays($days)];
        $companies = $this->tenants()
            ->where(fn ($q) => $q->where(fn ($t) => $t->where('is_trial', 1)->whereBetween('trial_expire_date', $window))
                ->orWhereBetween('plan_expire_date', $window))
            ->with('plan:id,name')
            ->get(['id', 'name', 'email', 'plan_id', 'is_trial', 'trial_expire_date', 'plan_expire_date']);

        return $companies->map(function ($company) {
            $trial = $company->is_trial && $company->trial_expire_date;
            $date = Carbon::parse($trial ? $company->trial_expire_date : $company->plan_expire_date);

            return [
                'id' => $company->id,
                'name' => $company->name,
                'email' => $company->email,
                'plan' => $company->plan?->name,
                'kind' => $trial ? 'trial' : 'renewal',
                'date' => $date->toDateString(),
            ];
        })->sortBy('date')->take($limit)->values()->all();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Workspace-owning companies. users.type is 'company' for every non-superadmin
     * user (managers, members and clients too), so go by the Spatie 'company' role,
     * as DashboardController::getTotalCompanies() does.
     */
    private function tenants()
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', 'company'));
    }

    /** Finished tasks: a completed stage or 100% progress (Task::isCompleted). */
    private function completed($query)
    {
        return $query->where(fn ($q) => $q->where('progress', '>=', 100)
            ->orWhereIn('task_stage_id', TaskStage::withoutGlobalScopes()->where('is_completed', true)->select('id')));
    }

    /** This week's time entries on the projects this person sees, in this workspace. */
    private function weekEntries(Workspace $workspace, User $user)
    {
        return TimesheetEntry::whereBetween('date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])
            ->whereHas('timesheet', fn ($q) => $q->where('workspace_id', $workspace->id))
            ->when(!$workspace->isOwner($user), fn ($q) => $q->whereHas('project', fn ($p) => $p->visibleTo($user)));
    }

    /**
     * Sums rows into one bucket per month, oldest first, empty months included.
     *
     * @return array<int, array{key: string, label: string, full: string, value: float}>
     */
    private function byMonth(Collection $rows, Carbon $start, int $months, callable $date, callable $value): array
    {
        $buckets = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $buckets[$month->format('Y-m')] = [
                'key' => $month->format('Y-m'),
                'label' => $month->translatedFormat('M'),
                'full' => $month->translatedFormat('F Y'),
                'value' => 0.0,
            ];
        }
        foreach ($rows as $row) {
            $key = Carbon::parse($date($row))->format('Y-m');
            if (isset($buckets[$key])) {
                $buckets[$key]['value'] += $value($row);
            }
        }

        return array_values(array_map(fn ($bucket) => ['value' => round($bucket['value'], 2)] + $bucket, $buckets));
    }
}
