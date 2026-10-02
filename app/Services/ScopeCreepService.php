<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\DB;

class ScopeCreepService
{
    /**
     * Detect scope creep by comparing tasks added in first 20% of project life
     * vs tasks added afterward.
     */
    public function detect(Project $project): array
    {
        $start    = $project->created_at;
        $deadline = $project->deadline ? \Carbon\Carbon::parse($project->deadline) : now()->addDays(90);
        $totalDays = max($start->diffInDays($deadline), 1);
        $baselineDays = max((int) ($totalDays * 0.2), 3); // first 20% or 3 days
        $baselineEnd  = $start->copy()->addDays($baselineDays);

        $baselineTasks = DB::table('tasks')
            ->where('project_id', $project->id)
            ->where('created_at', '<=', $baselineEnd)
            ->count();

        $totalTasks = DB::table('tasks')
            ->where('project_id', $project->id)
            ->count();

        $addedAfterBaseline = $totalTasks - $baselineTasks;
        $creepRate = $baselineTasks > 0
            ? round((($totalTasks - $baselineTasks) / $baselineTasks) * 100)
            : 0;

        $status = match(true) {
            $creepRate >= 100 => 'severe',
            $creepRate >= 50  => 'high',
            $creepRate >= 25  => 'moderate',
            default           => 'none',
        };

        // Recent additions (last 7 days) — these are the "suspect" tasks
        $recentTasks = DB::table('tasks as t')
            ->join('users as u', 'u.id', '=', 't.created_by')
            ->where('t.project_id', $project->id)
            ->where('t.created_at', '>=', now()->subDays(7))
            ->select('t.id', 't.title', 't.priority', 't.created_at', 'u.name as added_by')
            ->orderBy('t.created_at', 'desc')
            ->limit(10)
            ->get();

        return [
            'baseline_tasks'       => $baselineTasks,
            'total_tasks'          => $totalTasks,
            'added_after_baseline' => $addedAfterBaseline,
            'creep_rate'           => $creepRate,
            'status'               => $status,
            'recent_additions'     => $recentTasks,
            'message'              => $this->message($status, $creepRate, $addedAfterBaseline),
        ];
    }

    private function message(string $status, int $rate, int $added): string
    {
        return match($status) {
            'severe'   => "Scope has grown {$rate}% ({$added} tasks added beyond baseline). Project is at serious risk of delay.",
            'high'     => "Scope grew by {$rate}% ({$added} tasks added post-baseline). Review and reprioritize.",
            'moderate' => "Moderate scope growth detected ({$rate}%, {$added} tasks). Monitor closely.",
            default    => 'Scope is under control.',
        };
    }
}
