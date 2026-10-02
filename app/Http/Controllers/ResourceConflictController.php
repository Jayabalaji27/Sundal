<?php

namespace App\Http\Controllers;

use App\Traits\HasPermissionChecks;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ResourceConflictController extends Controller
{
    use HasPermissionChecks;

    public function index(): \Inertia\Response
    {
        $this->authorizePermission('project_view_any');
        return Inertia::render('resource-conflicts/index', [
            'conflicts' => $this->getConflicts(),
        ]);
    }

    public function api(): JsonResponse
    {
        $this->authorizePermission('project_view_any');
        return response()->json($this->getConflicts());
    }

    private function getConflicts(): array
    {
        $user      = auth()->user();
        $workspace = $user->currentWorkspace;
        if (!$workspace) return [];

        // Users with more than 3 concurrent active tasks (overlapping end dates within 7 days)
        $overloaded = DB::table('tasks as t')
            ->join('projects as p', 'p.id', '=', 't.project_id')
            ->join('users as u', 'u.id', '=', 't.assigned_to')
            ->where('p.workspace_id', $workspace->id)
            ->where('t.progress', '<', 100)
            ->whereNotNull('t.assigned_to')
            ->whereDate('t.end_date', '>=', now())
            ->whereDate('t.end_date', '<=', now()->addDays(14))
            ->select(
                'u.id as user_id',
                'u.name as user_name',
                'u.avatar',
                DB::raw('COUNT(t.id) as task_count'),
                DB::raw('SUM(CASE WHEN t.priority IN ("critical","high") THEN 1 ELSE 0 END) as high_priority_count'),
                DB::raw('MIN(t.end_date) as earliest_deadline')
            )
            ->groupBy('u.id', 'u.name', 'u.avatar')
            ->having('task_count', '>=', 3)
            ->orderBy('task_count', 'desc')
            ->get();

        $conflicts = [];
        foreach ($overloaded as $row) {
            // Get their tasks
            $tasks = DB::table('tasks as t')
                ->join('projects as p', 'p.id', '=', 't.project_id')
                ->where('t.assigned_to', $row->user_id)
                ->where('p.workspace_id', $workspace->id)
                ->where('t.progress', '<', 100)
                ->whereDate('t.end_date', '>=', now())
                ->whereDate('t.end_date', '<=', now()->addDays(14))
                ->select('t.id', 't.title', 't.priority', 't.end_date', 't.progress', 'p.title as project')
                ->orderByRaw("FIELD(t.priority,'critical','high','medium','low')")
                ->get();

            $severity = match(true) {
                $row->task_count >= 7 || $row->high_priority_count >= 3 => 'critical',
                $row->task_count >= 5 || $row->high_priority_count >= 2 => 'high',
                default => 'medium',
            };

            $conflicts[] = [
                'user'               => ['id' => $row->user_id, 'name' => $row->user_name, 'avatar' => $row->avatar],
                'task_count'         => $row->task_count,
                'high_priority_count'=> $row->high_priority_count,
                'earliest_deadline'  => $row->earliest_deadline,
                'severity'           => $severity,
                'tasks'              => $tasks,
                'recommendation'    => $this->recommend($row->task_count, $row->high_priority_count, $tasks),
            ];
        }

        return $conflicts;
    }

    private function recommend(int $total, int $high, $tasks): string
    {
        if ($total >= 7) {
            return "Critically overloaded — reassign at least " . ($total - 4) . " tasks immediately.";
        }
        if ($high >= 3) {
            return "Too many high-priority tasks. Consider pushing {$high} lower-priority items.";
        }
        return "Approaching capacity. Review workload before assigning new tasks.";
    }
}
