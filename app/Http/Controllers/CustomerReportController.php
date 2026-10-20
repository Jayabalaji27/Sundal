<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use App\Services\Reports\RenderedReport;
use App\Services\Reports\ReportDocument;
use App\Services\Reports\ReportRenderer;
use App\Traits\ExportsReports;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class CustomerReportController extends Controller
{
    use ExportsReports;

    public function index(Request $request)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        // Same visibility rule as the Projects page: the report shows a project's
        // budget, notes and activity, so only list projects the user can open.
        $projects = Project::forWorkspace($workspace->id)->visibleTo($user)->get(['id', 'title']);
        $members = User::whereHas('workspaces', function($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id)->where('status', 'active');
        })->get(['id', 'name']);

        return Inertia::render('timesheets/CustomerReport', [
            'projects' => $projects,
            'members' => $members
        ]);
    }

    public function generate(Request $request)
    {
        $validated = $this->validateFilters($request);
        $project = $this->loadProject($validated);

        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        return response()->json([
            'project' => $project,
            'summary' => $this->summarize($project),
            'filters' => $validated
        ]);
    }

    /** Download the report for the given project and filters as PDF, Excel or Word. */
    public function export(Request $request)
    {
        $format = $this->reportFormat($request);
        $filters = $this->validateFilters($request);
        $project = $this->loadProject($filters);
        abort_unless($project, 404);

        return $this->renderReport($project, $filters, $format)->download();
    }

    /** Email the report to the signed-in user or another address. */
    public function email(Request $request)
    {
        $filters = $this->validateFilters($request);
        $project = $this->loadProject($filters);
        abort_unless($project, 404);

        return $this->emailReport($request, __('Customer Report') . ' - ' . $project->title, fn (string $format) => $this->renderReport($project, $filters, $format));
    }

    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'project_id' => 'required|exists:projects,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'member_id' => 'nullable|exists:users,id'
        ]);
    }

    private function loadProject(array $validated): ?Project
    {
        $user = auth()->user();

        $project = Project::with([
            'tasks.taskStage',
            'tasks.comments.user',
            'users',
            'milestones.creator',
            'notes.creator',
            'activities.user',
            'budget.categories',
            'budget.expenses' => function($q) use ($validated) {
                if (isset($validated['start_date'])) {
                    $q->whereDate('expense_date', '>=', $validated['start_date']);
                }
                if (isset($validated['end_date'])) {
                    $q->whereDate('expense_date', '<=', $validated['end_date']);
                }
            },
            'timesheetEntries.user',
            'timesheetEntries' => function($q) use ($validated) {
                if (isset($validated['start_date'])) {
                    $q->whereDate('date', '>=', $validated['start_date']);
                }
                if (isset($validated['end_date'])) {
                    $q->whereDate('date', '<=', $validated['end_date']);
                }
                if (isset($validated['member_id'])) {
                    $q->where('user_id', $validated['member_id']);
                }
            }
        ])->forWorkspace($user->current_workspace_id)
          ->visibleTo($user)
          ->where('id', $validated['project_id'])
          ->first();

        if (!$project) {
            return null;
        }

        // Filter activities by date range
        if (isset($validated['start_date']) || isset($validated['end_date'])) {
            $activities = $project->activities()->with('user');

            if (isset($validated['start_date'])) {
                $activities->whereDate('created_at', '>=', $validated['start_date']);
            }
            if (isset($validated['end_date'])) {
                $activities->whereDate('created_at', '<=', $validated['end_date']);
            }

            $project->setRelation('activities', $activities->get());
        }

        return $project;
    }

    private function summarize(Project $project): array
    {
        // isCompleted() (progress 100 or a stage marked complete) rather than a
        // stage literally named "Done", so renamed or custom stages still count.
        $completedTasks = $project->tasks->filter(fn ($task) => $task->isCompleted())->count();
        // projects also has a plain `budget` amount column, which $project->budget
        // returns instead of the loaded ProjectBudget relation.
        $budget = $project->getRelation('budget');

        return [
            'timesheet' => [
                'total_hours' => (float) $project->timesheetEntries->sum('hours'),
                'billable_hours' => (float) $project->timesheetEntries->where('is_billable', true)->sum('hours'),
                'total_amount' => (float) $project->timesheetEntries->sum(function($entry) {
                    return $entry->hours * ($entry->hourly_rate ?? 0);
                })
            ],
            'tasks' => [
                'total_tasks' => $project->tasks->count(),
                'completed_tasks' => $completedTasks,
                'pending_tasks' => $project->tasks->count() - $completedTasks,
            ],
            'budget' => $budget ? [
                'total_budget' => (float) $budget->total_budget,
                'total_spent' => (float) $budget->total_spent,
                'remaining' => (float) $budget->remaining_budget,
                'utilization' => (float) $budget->utilization_percentage
            ] : null,
        ];
    }

    private function renderReport(Project $project, array $filters, string $format): RenderedReport
    {
        $user = auth()->user();
        $summary = $this->summarize($project);
        $currency = $this->reportCurrency();
        $start = $filters['start_date'] ?? null;
        $end = $filters['end_date'] ?? null;
        $period = match (true) {
            $start && $end => $this->reportDate($start) . ' - ' . $this->reportDate($end),
            (bool) $start => __('From') . ' ' . $this->reportDate($start),
            (bool) $end => __('Until') . ' ' . $this->reportDate($end),
            default => __('All time'),
        };
        $memberName = !empty($filters['member_id']) ? User::whereKey($filters['member_id'])->value('name') : null;
        $label = fn (?string $value) => $value ? ucfirst(str_replace('_', ' ', $value)) : null;

        $doc = (new ReportDocument(
            __('Customer Report'),
            'Customer Report - ' . $project->title . ' ' . now()->format('Y-m-d'),
            $project->title
        ))
            ->meta(__('Workspace'), $user->currentWorkspace?->name)
            ->meta(__('Period'), $period)
            ->meta(__('Team member'), $memberName ?? __('All members'))
            ->meta(__('Status'), $label($project->status))
            ->meta(__('Priority'), $label($project->priority))
            ->meta(__('Start date'), $this->reportDate($project->start_date))
            ->meta(__('Deadline'), $this->reportDate($project->deadline))
            ->meta(__('Generated by'), $user->name)
            ->summary(__('Progress'), ((int) $project->progress) . '%')
            ->summary(__('Tasks done'), $summary['tasks']['completed_tasks'] . '/' . $summary['tasks']['total_tasks'])
            ->summary(__('Total hours'), round($summary['timesheet']['total_hours'], 2))
            ->summary(__('Billable hours'), round($summary['timesheet']['billable_hours'], 2))
            ->summary(__('Amount') . " ($currency)", round($summary['timesheet']['total_amount'], 2));

        $doc->table(
            __('Tasks'),
            [__('Task'), __('Stage'), __('Priority'), __('Start date'), __('Due date'), __('Progress %')],
            $project->tasks->map(fn ($task) => [
                $task->title,
                $task->taskStage?->name ?? __('No Stage'),
                $label($task->priority),
                $this->reportDate($task->start_date),
                $this->reportDate($task->end_date),
                (int) $task->progress,
            ]),
            __('No tasks found.')
        );

        $doc->table(
            __('Team'),
            [__('Name'), __('Email'), __('Role')],
            $project->users->map(fn ($member) => [$member->name, $member->email, $label($member->pivot?->role) ?? __('Member')]),
            __('No team members assigned.')
        );

        $doc->table(
            __('Milestones'),
            [__('Milestone'), __('Status'), __('Due date'), __('Progress %')],
            $project->milestones->map(fn ($milestone) => [
                $milestone->title,
                $label($milestone->status),
                $this->reportDate($milestone->due_date),
                (int) $milestone->progress,
            ]),
            __('No milestones found.')
        );

        $doc->table(
            __('Timesheet Entries'),
            [__('Date'), __('Member'), __('Hours'), __('Billable'), __('Description')],
            $project->timesheetEntries->sortBy('date')->map(fn ($entry) => [
                $this->reportDate($entry->date),
                $entry->user?->name,
                round((float) $entry->hours, 2),
                (bool) $entry->is_billable,
                $entry->description,
            ]),
            __('No timesheet entries found.')
        );

        $budget = $summary['budget'];
        $doc->table(
            __('Budget'),
            [__('Total budget') . " ($currency)", __('Spent') . " ($currency)", __('Remaining') . " ($currency)", __('Utilization %')],
            $budget ? [[round($budget['total_budget'], 2), round($budget['total_spent'], 2), round($budget['remaining'], 2), round($budget['utilization'], 1)]] : [],
            __('No budget information available.')
        );

        $doc->table(
            __('Notes'),
            [__('Title'), __('Note'), __('By'), __('Date')],
            $project->notes->map(fn ($note) => [
                $note->title,
                trim(html_entity_decode(strip_tags((string) $note->content))),
                $note->creator?->name,
                $this->reportDate($note->created_at),
            ]),
            __('No notes found.')
        );

        $doc->table(
            __('Activity'),
            [__('Date'), __('User'), __('Activity')],
            $project->activities->map(fn ($activity) => [
                $activity->created_at ? formatDateTime($activity->created_at) : null,
                $activity->user?->name,
                $activity->description,
            ]),
            __('No recent activity.')
        );

        return app(ReportRenderer::class)->render($doc, $format);
    }
}
