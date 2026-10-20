<?php

namespace App\Http\Controllers;

use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use App\Models\Project;
use App\Models\User;
use App\Services\Reports\RenderedReport;
use App\Services\Reports\ReportDocument;
use App\Services\Reports\ReportRenderer;
use App\Traits\ExportsReports;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class TimesheetReportController extends Controller
{
    use ExportsReports;

    /**
     * Timesheet statuses each report filter includes. "counted" is the default
     * and matches the project page: rejected and draft time is not logged time.
     */
    private const STATUS_FILTERS = [
        'counted' => ['submitted', 'approved'],
        'approved' => ['approved'],
        'all' => null,
    ];

    public function index(Request $request)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        $members = User::whereHas('workspaces', function($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id)->where('status', 'active');
        })->get();

        $members->each(function ($member) {
            $member->avatar = check_file($member->avatar) ? get_file($member->avatar) : get_file('avatars/avatar.png');
        });

        $projects = Project::forWorkspace($workspace->id)->visibleTo($user)->get();

        // Set default date range (last 30 days)
        $endDate = Carbon::now()->format('Y-m-d');
        if(isDemo())
        {
            $startDate = Carbon::now()->subDays(100)->format('Y-m-d');
        } else {
            $startDate = Carbon::now()->subDays(30)->format('Y-m-d');
        }

        // Generate default latest report (summary for last 30 days)
        $defaultQuery = $this->visibleEntries($user)
            ->whereHas('timesheet', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id)->whereIn('status', self::STATUS_FILTERS['counted']);
            })
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'desc');

        $defaultEntries = $defaultQuery->get();
        $defaultReportData = $defaultEntries->count() > 0 ? [
            'summary' => $this->generateSummaryReport($defaultEntries),
            'detailed' => $this->generateDetailedReport($defaultEntries),
            'projects' => $this->generateProjectReport($defaultEntries),
            'members' => $this->generateMemberReport($defaultEntries)
        ] : null;

        return Inertia::render('timesheets/Reports', [
            'members' => $members,
            'projects' => $projects,
            'defaultFilters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'user_id' => 'all',
                'project_id' => 'all',
                'status' => 'counted',
            ],
            'defaultReportData' => $defaultReportData
        ]);
    }

    public function generate(Request $request)
    {
        $entries = $this->filteredEntries($this->validateFilters($request));

        if ($entries->count() === 0) {
            return response()->json(null);
        }

        return response()->json([
            'summary' => $this->generateSummaryReport($entries),
            'detailed' => $this->generateDetailedReport($entries),
            'projects' => $this->generateProjectReport($entries),
            'members' => $this->generateMemberReport($entries)
        ]);
    }

    /** Download the report for the given filters as PDF, Excel or Word. */
    public function export(Request $request)
    {
        $format = $this->reportFormat($request);

        return $this->renderReport($this->validateFilters($request), $format)->download();
    }

    /** Email the report for the given filters to the signed-in user or another address. */
    public function email(Request $request)
    {
        $filters = $this->validateFilters($request);

        return $this->emailReport($request, __('Timesheet Report'), fn (string $format) => $this->renderReport($filters, $format));
    }

    private function validateFilters(Request $request): array
    {
        // after_or_equal (not after): a single-day report has start_date === end_date,
        // which the old 'after' rule rejected with a 422 that the frontend swallowed
        // (it never checked response.ok), making "Generate Report" appear to just do
        // nothing whenever both dates matched.
        return $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'user_id' => 'nullable|string',
            'project_id' => 'nullable|string',
            'status' => 'nullable|in:' . implode(',', array_keys(self::STATUS_FILTERS)),
        ]);
    }

    private function filteredEntries(array $filters)
    {
        $statuses = self::STATUS_FILTERS[$filters['status'] ?? 'counted'];

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        $query = $this->visibleEntries($user)
            ->whereHas('timesheet', function($q) use ($workspace, $statuses) {
                $q->where('workspace_id', $workspace->id)
                    ->when($statuses, fn ($q) => $q->whereIn('status', $statuses));
            })
            ->whereBetween('date', [$filters['start_date'], $filters['end_date']]);

        if (isset($filters['user_id']) && $filters['user_id'] !== 'all') {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['project_id']) && $filters['project_id'] !== 'all') {
            $query->where('project_id', $filters['project_id']);
        }

        return $query->get();
    }

    private function renderReport(array $filters, string $format): RenderedReport
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        $entries = $this->filteredEntries($filters)->sortBy('date')->values();
        $currency = $this->reportCurrency();
        $amount = fn ($entry) => (float) $entry->hours * (float) ($entry->hourly_rate ?? 0);

        $projectName = isset($filters['project_id']) && $filters['project_id'] !== 'all'
            ? Project::forWorkspace($workspace->id)->whereKey($filters['project_id'])->value('title')
            : null;
        $memberName = isset($filters['user_id']) && $filters['user_id'] !== 'all'
            ? User::whereKey($filters['user_id'])->value('name')
            : null;
        $statusLabels = ['counted' => __('Submitted & approved'), 'approved' => __('Approved only'), 'all' => __('All (incl. draft & rejected)')];

        $doc = (new ReportDocument(
            __('Timesheet Report'),
            'Timesheet Report ' . $filters['start_date'] . ' to ' . $filters['end_date'],
            $workspace->name
        ))
            ->meta(__('Period'), $this->reportDate($filters['start_date']) . ' - ' . $this->reportDate($filters['end_date']))
            ->meta(__('Project'), $projectName ?? __('All projects'))
            ->meta(__('Member'), $memberName ?? __('All members'))
            ->meta(__('Timesheet status'), $statusLabels[$filters['status'] ?? 'counted'])
            ->meta(__('Generated by'), $user->name)
            ->summary(__('Total hours'), round((float) $entries->sum('hours'), 2))
            ->summary(__('Billable hours'), round((float) $entries->where('is_billable', true)->sum('hours'), 2))
            ->summary(__('Total amount') . " ($currency)", round($entries->sum($amount), 2))
            ->summary(__('Entries'), $entries->count());

        $doc->table(
            __('Projects'),
            [__('Project'), __('Status'), __('Progress %'), __('Total hours'), __('Billable hours'), __('Amount') . " ($currency)"],
            $entries->groupBy('project_id')->map(fn ($rows) => [
                $rows->first()->project?->title ?? __('Unknown Project'),
                ucfirst(str_replace('_', ' ', (string) $rows->first()->project?->status)),
                (int) ($rows->first()->project?->progress ?? 0),
                round((float) $rows->sum('hours'), 2),
                round((float) $rows->where('is_billable', true)->sum('hours'), 2),
                round($rows->sum($amount), 2),
            ])->values()
        );

        $doc->table(
            __('Team Members'),
            [__('Member'), __('Total hours'), __('Billable hours'), __('Amount') . " ($currency)"],
            $entries->groupBy('user_id')->map(fn ($rows) => [
                $rows->first()->user?->name ?? __('Unknown User'),
                round((float) $rows->sum('hours'), 2),
                round((float) $rows->where('is_billable', true)->sum('hours'), 2),
                round($rows->sum($amount), 2),
            ])->sortByDesc(1)->values()
        );

        $doc->table(
            __('Time Entries'),
            [__('Date'), __('Member'), __('Project'), __('Task'), __('Description'), __('Hours'), __('Billable'), __('Amount') . " ($currency)"],
            $entries->map(fn ($entry) => [
                $this->reportDate($entry->date),
                $entry->user?->name ?? __('Unknown User'),
                $entry->project?->title ?? __('Unknown Project'),
                $entry->task?->title,
                $entry->description,
                round((float) $entry->hours, 2),
                (bool) $entry->is_billable,
                round($amount($entry), 2),
            ]),
            __('No timesheet entries found for the selected filters.')
        );

        return app(ReportRenderer::class)->render($doc, $format);
    }



    public function dashboardWidgets(Request $request)
    {
        $user = auth()->user();
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        $weeklyHours = TimesheetEntry::whereHas('timesheet', function($q) use ($user) {
            $q->where('workspace_id', $user->current_workspace_id);
        })
        ->whereBetween('date', [$startOfWeek, $endOfWeek])
        ->sum('hours');

        $billableHours = TimesheetEntry::whereHas('timesheet', function($q) use ($user) {
            $q->where('workspace_id', $user->current_workspace_id);
        })
        ->whereBetween('date', [$startOfWeek, $endOfWeek])
        ->billable()
        ->sum('hours');

        $pendingApprovals = Timesheet::where('workspace_id', $user->current_workspace_id)
            ->where('status', 'submitted')
            ->count();

        return response()->json([
            'weekly_hours' => $weeklyHours,
            'billable_hours' => $billableHours,
            'pending_approvals' => $pendingApprovals
        ]);
    }

    /**
     * Time entries on projects the user can see - the same rule as the Projects
     * page - so a private project's hours and amounts never reach someone who
     * isn't on it. Owners see every project.
     */
    private function visibleEntries(User $user)
    {
        return TimesheetEntry::with(['timesheet', 'project', 'task', 'user'])
            ->when(!$user->currentWorkspace?->isOwner($user), fn ($q) => $q->whereHas('project', fn ($p) => $p->visibleTo($user)));
    }

    private function generateSummaryReport($entries)
    {
        return [
            'total_hours' => $entries->sum('hours'),
            'billable_hours' => $entries->where('is_billable', true)->sum('hours'),
            'total_amount' => $entries->sum(fn($entry) => $entry->hours * ($entry->hourly_rate ?? 0)),
            'entries_count' => $entries->count()
        ];
    }

    private function generateDetailedReport($entries)
    {
        return [
            'entries' => $entries->map(function($entry) {
                return [
                    'date' => $entry->date,
                    'user' => $entry->user ? $entry->user->name : 'Unknown User',
                    'project' => $entry->project ? $entry->project->title : 'Unknown Project',
                    'task' => $entry->task?->title,
                    'hours' => $entry->hours,
                    'description' => $entry->description,
                    'is_billable' => $entry->is_billable,
                    'amount' => $entry->hours * ($entry->hourly_rate ?? 0)
                ];
            })
        ];
    }

    private function generateProjectReport($entries)
    {
        if ($entries->isEmpty()) {
            return [];
        }
        
        return $entries->groupBy('project_id')->map(function($projectEntries) {
            $project = $projectEntries->first()->project;
            $tasks = $project ? $project->tasks()->with('taskStage')->get() : collect();
            $completedTasks = $tasks->filter(fn ($task) => $task->isCompleted())->count();
            $totalTasks = $tasks->count();
            
            return [
                'project_name' => $project ? $project->title : 'Unknown Project',
                'progress' => $project ? $project->calculateProgress() : 0,
                'status' => $project ? $project->status : 'unknown',
                'total_hours' => $projectEntries->sum('hours'),
                'billable_hours' => $projectEntries->where('is_billable', true)->sum('hours'),
                'total_amount' => $projectEntries->sum(fn($entry) => $entry->hours * ($entry->hourly_rate ?? 0)),
                'tasks_completed' => $completedTasks,
                'tasks_total' => $totalTasks,
                'task_progress' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0
            ];
        })->values();
    }

    private function generateMemberReport($entries)
    {
        return $entries->groupBy('user_id')->map(function($userEntries) {
            $user = $userEntries->first()->user;
            $projectData = $userEntries->groupBy('project_id')->map(function($projectEntries) {
                $project = $projectEntries->first()->project;
                // The tasks this member actually logged time against in the selected
                // range - not every task currently assigned to them on the project.
                $tasks = $projectEntries->groupBy('task_id')
                    ->filter(fn ($taskEntries, $taskId) => $taskId && $taskEntries->first()->task)
                    ->map(function ($taskEntries) {
                        $task = $taskEntries->first()->task;
                        return [
                            'title' => $task->title,
                            'status' => $task->taskStage ? $task->taskStage->name : 'No Stage',
                            'priority' => $task->priority,
                            'due_date' => $task->end_date,
                            'hours' => $taskEntries->sum('hours'),
                        ];
                    })
                    ->values();
                
                return [
                    'project_name' => $project ? $project->title : 'Unknown Project',
                    'hours' => $projectEntries->sum('hours'),
                    'billable_hours' => $projectEntries->where('is_billable', true)->sum('hours'),
                    'tasks' => $tasks,
                    'entries' => $projectEntries->map(function($entry) {
                        return [
                            'date' => $entry->date,
                            'hours' => $entry->hours,
                            'description' => $entry->description,
                            'is_billable' => $entry->is_billable
                        ];
                    }),
                ];
            });
            
            return [
                'member_name' => $user ? $user->name : 'Unknown User',
                'total_hours' => $userEntries->sum('hours'),
                'billable_hours' => $userEntries->where('is_billable', true)->sum('hours'),
                'total_amount' => $userEntries->sum(fn($entry) => $entry->hours * ($entry->hourly_rate ?? 0)),
                'projects' => $projectData->values(),
                'avatar' => $user ? (check_file($user->avatar) ? get_file($user->avatar) : get_file('avatars/avatar.png')) : get_file('avatars/avatar.png')
            ];
        })->values();
    }
}