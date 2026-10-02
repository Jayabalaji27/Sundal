<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StandupController extends Controller
{
    use HasPermissionChecks;

    public function index(Request $request)
    {
        $this->authorizePermission('project_view_any');

        $user      = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace) abort(404);

        $date     = $request->input('date', now()->toDateString());
        $prevDate = now()->parse($date)->subDay()->toDateString();

        // All members in workspace
        $members = DB::table('workspace_members as wm')
            ->join('users as u', 'u.id', '=', 'wm.user_id')
            ->where('wm.workspace_id', $workspace->id)
            ->select('u.id', 'u.name', 'u.avatar', 'u.type')
            ->get();

        $standups = $members->map(function ($member) use ($workspace, $date, $prevDate) {
            return [
                'user'     => $member,
                'data'     => $this->buildStandup($member->id, $workspace->id, $date, $prevDate),
            ];
        });

        $projects = Project::forWorkspace($workspace->id)
            ->select('id', 'title')
            ->orderBy('title')
            ->get();

        return Inertia::render('standup/index', [
            'standups'  => $standups,
            'date'      => $date,
            'projects'  => $projects,
        ]);
    }

    public function api(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorizePermission('project_view_any');

        $user      = auth()->user();
        $workspace = $user->currentWorkspace;
        if (!$workspace) abort(404);

        $date     = $request->input('date', now()->toDateString());
        $prevDate = now()->parse($date)->subDay()->toDateString();

        $members = DB::table('workspace_members as wm')
            ->join('users as u', 'u.id', '=', 'wm.user_id')
            ->where('wm.workspace_id', $workspace->id)
            ->select('u.id', 'u.name', 'u.avatar')
            ->get();

        $standups = $members->map(fn($m) => [
            'user' => $m,
            'data' => $this->buildStandup($m->id, $workspace->id, $date, $prevDate),
        ]);

        return response()->json(['standups' => $standups, 'date' => $date]);
    }

    private function buildStandup(int $userId, int $workspaceId, string $date, string $prevDate): array
    {
        // ── Yesterday: activities + timesheet entries ──────────────────────
        $activities = DB::table('project_activities as pa')
            ->join('projects as p', 'p.id', '=', 'pa.project_id')
            ->where('pa.user_id', $userId)
            ->where('p.workspace_id', $workspaceId)
            ->whereDate('pa.created_at', $prevDate)
            ->select('pa.description', 'p.title as project')
            ->orderBy('pa.created_at', 'desc')
            ->limit(10)
            ->get();

        $timesheets = DB::table('timesheet_entries as te')
            ->join('projects as p', 'p.id', '=', 'te.project_id')
            ->leftJoin('tasks as t', 't.id', '=', 'te.task_id')
            ->where('te.user_id', $userId)
            ->where('p.workspace_id', $workspaceId)
            ->whereDate('te.date', $prevDate)
            ->select('te.description', 'te.hours', 'p.title as project', 't.title as task')
            ->orderBy('te.date', 'desc')
            ->limit(10)
            ->get();

        $yesterday = collect();
        foreach ($timesheets as $ts) {
            $label = $ts->task ? "{$ts->task} ({$ts->hours}h)" : ($ts->description ?? 'Logged time');
            $yesterday->push(['project' => $ts->project, 'item' => $label]);
        }
        foreach ($activities as $act) {
            $yesterday->push(['project' => $act->project, 'item' => $act->description]);
        }

        // ── Today: assigned in-progress tasks ─────────────────────────────
        $today = DB::table('tasks as t')
            ->join('projects as p', 'p.id', '=', 't.project_id')
            ->where('t.assigned_to', $userId)
            ->where('p.workspace_id', $workspaceId)
            ->where('t.progress', '<', 100)
            ->whereNull('t.end_date') // not yet past due
            ->orWhere(function ($q) use ($userId, $workspaceId) {
                $q->where('t.assigned_to', $userId)
                  ->where('p.workspace_id', $workspaceId)
                  ->where('t.progress', '<', 100)
                  ->whereDate('t.end_date', '>=', now());
            })
            ->select('t.title', 'p.title as project', 't.priority', 't.progress', 't.end_date')
            ->orderByRaw("FIELD(t.priority,'critical','high','medium','low')")
            ->limit(8)
            ->get();

        // ── Blockers: overdue tasks + critical bugs ────────────────────────
        $overdueCount = DB::table('tasks as t')
            ->join('projects as p', 'p.id', '=', 't.project_id')
            ->where('t.assigned_to', $userId)
            ->where('p.workspace_id', $workspaceId)
            ->where('t.progress', '<', 100)
            ->whereNotNull('t.end_date')
            ->whereDate('t.end_date', '<', now())
            ->count();

        $criticalBugs = DB::table('bugs as b')
            ->join('projects as p', 'p.id', '=', 'b.project_id')
            ->where('b.assigned_to', $userId)
            ->where('p.workspace_id', $workspaceId)
            ->whereIn('b.severity', ['critical', 'blocker'])
            ->whereNull('b.resolved_by')
            ->count();

        $blockers = [];
        if ($overdueCount > 0) {
            $blockers[] = "{$overdueCount} overdue task" . ($overdueCount > 1 ? 's need' : ' needs') . " attention";
        }
        if ($criticalBugs > 0) {
            $blockers[] = "{$criticalBugs} critical bug" . ($criticalBugs > 1 ? 's' : '') . " unresolved";
        }

        return [
            'yesterday' => $yesterday->unique('item')->values()->take(6),
            'today'     => $today->take(6),
            'blockers'  => $blockers,
            'has_data'  => $yesterday->isNotEmpty() || $today->isNotEmpty(),
        ];
    }
}
