<?php

namespace App\Services\Ai\Tools;

use App\Models\Bug;
use App\Models\TimesheetEntry;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\ProjectHealthService;

/**
 * A status report for one project, assembled from the same data as the
 * Project Reports screen: progress, health, tasks by stage, overdue work,
 * open bugs, hours logged and budget.
 */
class GetProjectReport extends AiTool
{
    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly ProjectHealthService $health,
        private readonly BudgetService $budgets,
    ) {}

    public function name(): string
    {
        return 'get_project_report';
    }

    public function description(): string
    {
        return 'A status report for one project: progress, health score, tasks by stage, overdue tasks, open bugs, hours logged in a period, and budget. Use it to write weekly or monthly project reports.';
    }

    public function permissions(): array
    {
        return ['project_report_view_any', 'project_report_view'];
    }

    public function parameters(): array
    {
        return [
            'project' => ['type' => 'string', 'required' => true, 'description' => 'Project title or id.'],
            'from' => ['type' => 'string', 'description' => 'Start of the period for hours logged, YYYY-MM-DD. Defaults to 7 days ago.'],
            'to' => ['type' => 'string', 'description' => 'End of the period, YYYY-MM-DD. Defaults to today.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $project = $this->resolver->project($user, (string) ($args['project'] ?? ''));
        $from = $this->resolver->date($args['from'] ?? null, __('From')) ?? now()->subDays(7)->toDateString();
        $to = $this->resolver->date($args['to'] ?? null, __('To')) ?? now()->toDateString();
        $today = now()->toDateString();

        $tasks = $project->tasks()->with('taskStage:id,name,is_completed', 'assignedTo:id,name')->get();
        $open = $tasks->filter(fn ($t) => !$t->taskStage?->is_completed);
        $overdue = $open->filter(fn ($t) => $t->end_date && $t->end_date->format('Y-m-d') < $today);

        $bugs = Bug::where('project_id', $project->id)->with('bugStatus:id,name')->get();
        $openBugs = $bugs->reject(fn ($b) => in_array($b->bugStatus?->name, ['Resolved', 'Closed'], true));

        $hours = TimesheetEntry::where('project_id', $project->id)->whereBetween('date', [$from, $to]);
        $health = $this->health->calculate($project);
        $budget = $this->budgets->getProjectBudgetSummary($project->id);

        return [
            'project' => $project->title,
            'status' => $project->status,
            'progress_percent' => (int) $project->progress,
            'deadline' => $project->deadline?->format('Y-m-d'),
            'health' => ['score' => $health['score'] ?? null, 'status' => $health['status'] ?? null],
            'tasks' => [
                'total' => $tasks->count(),
                'by_stage' => $tasks->groupBy(fn ($t) => $t->taskStage?->name ?? 'No stage')->map->count()->all(),
                'completed' => $tasks->count() - $open->count(),
                'overdue' => $overdue->map(fn ($t) => [
                    'title' => $t->title,
                    'due_date' => $t->end_date->format('Y-m-d'),
                    'assignee' => $t->assignedTo?->name,
                ])->values()->take(20)->all(),
            ],
            'bugs' => [
                'total' => $bugs->count(),
                'open' => $openBugs->count(),
                'open_by_severity' => $openBugs->groupBy('severity')->map->count()->all(),
            ],
            'hours_logged' => [
                'from' => $from,
                'to' => $to,
                'total' => round((float) (clone $hours)->sum('hours'), 2),
                'billable' => round((float) (clone $hours)->where('is_billable', true)->sum('hours'), 2),
            ],
            'budget' => $budget ? [
                'total' => (float) $budget['total_budget'],
                'spent' => (float) $budget['total_spent'],
                'utilization_percent' => round((float) $budget['utilization_percentage'], 1),
                'status' => $budget['status'],
                'currency' => $budget['currency'],
            ] : null,
            'link' => route('project-reports.show', $project->id, false),
        ];
    }
}
