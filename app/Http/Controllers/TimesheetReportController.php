<?php

namespace App\Http\Controllers;

use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class TimesheetReportController extends Controller
{
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

        $projects = Project::forWorkspace($workspace->id)->get();

        // Set default date range (last 30 days)
        $endDate = Carbon::now()->format('Y-m-d');
        if(isDemo())
        {
            $startDate = Carbon::now()->subDays(100)->format('Y-m-d');
        } else {
            $startDate = Carbon::now()->subDays(30)->format('Y-m-d');
        }

        // Generate default latest report (summary for last 30 days)
        $defaultQuery = TimesheetEntry::with(['timesheet', 'project', 'task', 'user'])
            ->whereHas('timesheet', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
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
                'project_id' => 'all'
            ],
            'defaultReportData' => $defaultReportData
        ]);
    }

    public function generate(Request $request)
    {
        // after_or_equal (not after): a single-day report has start_date === end_date,
        // which the old 'after' rule rejected with a 422 that the frontend swallowed
        // (it never checked response.ok), making "Generate Report" appear to just do
        // nothing whenever both dates matched.
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'user_id' => 'nullable|string',
            'project_id' => 'nullable|string'
        ]);

        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        $query = TimesheetEntry::with(['timesheet', 'project', 'task', 'user'])
            ->whereHas('timesheet', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })
            ->whereBetween('date', [$validated['start_date'], $validated['end_date']]);

        if (isset($validated['user_id']) && $validated['user_id'] !== 'all') {
            $query->where('user_id', $validated['user_id']);
        }

        if (isset($validated['project_id']) && $validated['project_id'] !== 'all') {
            $query->where('project_id', $validated['project_id']);
        }

        $entries = $query->get();

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
            $completedTasks = $tasks->where('taskStage.name', 'Done')->count();
            $totalTasks = $tasks->count();
            
            return [
                'project_name' => $project ? $project->title : 'Unknown Project',
                'progress' => $project ? $project->progress : 0,
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
                $tasks = $project ? $project->tasks()->where('assigned_to', $projectEntries->first()->user_id)->with('taskStage')->get() : collect();
                
                return [
                    'project_name' => $project ? $project->title : 'Unknown Project',
                    'hours' => $projectEntries->sum('hours'),
                    'billable_hours' => $projectEntries->where('is_billable', true)->sum('hours'),
                    'tasks' => $tasks->map(function($task) {
                        return [
                            'title' => $task->title,
                            'status' => $task->taskStage ? $task->taskStage->name : 'No Stage',
                            'priority' => $task->priority,
                            'due_date' => $task->end_date
                        ];
                    }),
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