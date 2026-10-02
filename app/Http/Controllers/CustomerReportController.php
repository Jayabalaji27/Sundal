<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class CustomerReportController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        $projects = Project::forWorkspace($workspace->id)->get(['id', 'title']);
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
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'member_id' => 'nullable|exists:users,id'
        ]);

        $user = auth()->user();
        
        $query = Project::with([
            'tasks.stage',
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
        ])->where('workspace_id', $user->current_workspace_id)
          ->where('id', $validated['project_id']);

        $project = $query->first();

        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
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

        // Calculate summary data
        $timesheetSummary = [
            'total_hours' => $project->timesheetEntries->sum('hours'),
            'billable_hours' => $project->timesheetEntries->where('is_billable', true)->sum('hours'),
            'total_amount' => $project->timesheetEntries->sum(function($entry) {
                return $entry->hours * ($entry->hourly_rate ?? 0);
            })
        ];

        $taskSummary = [
            'total_tasks' => $project->tasks->count(),
            'completed_tasks' => $project->tasks->where('stage.name', 'Done')->count(),
            'pending_tasks' => $project->tasks->where('stage.name', '!=', 'Done')->count()
        ];

        $budgetSummary = $project->budget ? [
            'total_budget' => $project->budget->total_budget,
            'total_spent' => $project->budget->total_spent,
            'remaining' => $project->budget->remaining_budget,
            'utilization' => $project->budget->utilization_percentage
        ] : null;

        return response()->json([
            'project' => $project,
            'summary' => [
                'timesheet' => $timesheetSummary,
                'tasks' => $taskSummary,
                'budget' => $budgetSummary
            ],
            'filters' => $validated
        ]);
    }
}