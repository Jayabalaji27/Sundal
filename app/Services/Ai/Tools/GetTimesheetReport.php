<?php

namespace App\Services\Ai\Tools;

use App\Models\TimesheetEntry;
use App\Models\User;

/**
 * The Reports page (timesheet report): hours and amounts logged in a period,
 * by project and by person, with the page's rules: the owner sees all time,
 * a manager the time on their projects, and only submitted and approved
 * time counts unless asked otherwise (TimesheetReportController).
 */
class GetTimesheetReport extends AiTool
{
    private const STATUSES = [
        'counted' => ['submitted', 'approved'],
        'approved' => ['approved'],
        'all' => null,
    ];

    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'get_timesheet_report';
    }

    public function description(): string
    {
        return 'Timesheet report for a period: total, billable hours and amount, split by project and by person. Use it for "how many hours did the team log this month?", "hours on Mobile App last week", "what did Ravi work on?".';
    }

    public function permissions(): array
    {
        return ['report_timesheet'];
    }

    public function parameters(): array
    {
        return [
            'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD. Defaults to the first day of this month.'],
            'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD. Defaults to today.'],
            'project' => ['type' => 'string', 'description' => 'Project title or id.'],
            'person' => ['type' => 'string', 'description' => '"me" or a person\'s name or email.'],
            'status' => ['type' => 'enum', 'options' => array_keys(self::STATUSES), 'description' => 'counted (submitted and approved, the default), approved only, or all (drafts and rejected too).'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $from = $this->resolver->date($args['from'] ?? null, __('From')) ?? now()->startOfMonth()->toDateString();
        $to = $this->resolver->date($args['to'] ?? null, __('To')) ?? now()->toDateString();
        if ($to < $from) {
            throw new ToolInputException(__('The end of the period cannot be before its start.'));
        }
        $project = !empty($args['project']) ? $this->resolver->project($user, (string) $args['project']) : null;
        $person = !empty($args['person']) ? $this->resolver->member($user, (string) $args['person']) : null;
        $status = array_key_exists($args['status'] ?? '', self::STATUSES) ? $args['status'] : 'counted';
        $statuses = self::STATUSES[$status];

        $entries = TimesheetEntry::with(['project:id,title', 'task:id,title', 'user:id,name'])
            ->when(!$user->currentWorkspace?->isOwner($user), fn ($q) => $q->whereHas('project', fn ($p) => $p->visibleTo($user)))
            ->whereHas('timesheet', fn ($q) => $q->where('workspace_id', $user->current_workspace_id)
                ->when($statuses, fn ($q) => $q->whereIn('status', $statuses)))
            ->whereBetween('date', [$from, $to])
            ->when($project, fn ($q) => $q->where('project_id', $project->id))
            ->when($person, fn ($q) => $q->where('user_id', $person->id))
            ->get();

        $totals = fn ($group) => [
            'hours' => round((float) $group->sum('hours'), 2),
            'billable_hours' => round((float) $group->where('is_billable', true)->sum('hours'), 2),
            'amount' => round((float) $group->sum(fn ($e) => $e->hours * ($e->hourly_rate ?? 0)), 2),
        ];

        return [
            'period' => ['from' => $from, 'to' => $to],
            'counted' => $status,
            'total' => $totals($entries) + ['entries' => $entries->count()],
            'by_project' => $entries->groupBy('project_id')
                ->map(fn ($group) => ['project' => $group->first()->project?->title] + $totals($group))
                ->sortByDesc('hours')->values()->take(20)->all(),
            'by_person' => $entries->groupBy('user_id')
                ->map(fn ($group) => [
                    'person' => $group->first()->user?->name,
                    ...$totals($group),
                    'top_tasks' => $group->whereNotNull('task_id')->groupBy('task_id')
                        ->map(fn ($t) => ['task' => $t->first()->task?->title, 'hours' => round((float) $t->sum('hours'), 2)])
                        ->sortByDesc('hours')->values()->take(5)->all(),
                ])
                ->sortByDesc('hours')->values()->take(20)->all(),
            'link' => route('timesheet-reports.index', [], false),
        ];
    }
}
