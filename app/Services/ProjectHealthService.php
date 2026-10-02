<?php

namespace App\Services;

use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProjectHealthService
{
    /**
     * Calculate health score for a project.
     * Returns score (0-100), status, and risk factors.
     */
    public function calculate(Project $project): array
    {
        $score = 100;
        $factors = [];
        $metrics = [];

        // ── 1. Task completion ──────────────────────────────────────────────
        $taskStats = DB::table('tasks')
            ->where('project_id', $project->id)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN progress = 100 THEN 1 ELSE 0 END) as done')
            ->first();

        $totalTasks = (int) ($taskStats->total ?? 0);
        $doneTasks  = (int) ($taskStats->done  ?? 0);
        $completionRate = $totalTasks > 0 ? round(($doneTasks / $totalTasks) * 100) : 100;
        $metrics['task_completion'] = $completionRate;
        $metrics['total_tasks']     = $totalTasks;
        $metrics['done_tasks']      = $doneTasks;

        // ── 2. Overdue tasks ────────────────────────────────────────────────
        $overdueTasks = DB::table('tasks')
            ->where('project_id', $project->id)
            ->where('progress', '<', 100)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', now())
            ->count();

        $metrics['overdue_tasks'] = $overdueTasks;

        if ($overdueTasks >= 6) {
            $score -= 30;
            $factors[] = ['type' => 'critical', 'message' => "{$overdueTasks} tasks are overdue"];
        } elseif ($overdueTasks >= 3) {
            $score -= 20;
            $factors[] = ['type' => 'warning', 'message' => "{$overdueTasks} tasks are overdue"];
        } elseif ($overdueTasks >= 1) {
            $score -= 10;
            $factors[] = ['type' => 'warning', 'message' => "{$overdueTasks} task" . ($overdueTasks > 1 ? 's are' : ' is') . " overdue"];
        }

        // ── 2b. Blocked tasks ─────────────────────────────────────────────────
        // Task state has no dedicated status column — it's whatever stage name
        // a workspace defines (see TaskStage), so "blocked" is matched by name.
        $blockedTasks = DB::table('tasks')
            ->join('task_stages', 'tasks.task_stage_id', '=', 'task_stages.id')
            ->where('tasks.project_id', $project->id)
            ->where('tasks.progress', '<', 100)
            ->where('task_stages.name', 'like', '%block%')
            ->count();

        $metrics['blocked_tasks'] = $blockedTasks;

        if ($blockedTasks >= 3) {
            $score -= 25;
            $factors[] = ['type' => 'critical', 'message' => "{$blockedTasks} tasks are blocked"];
        } elseif ($blockedTasks >= 1) {
            $score -= 15;
            $factors[] = ['type' => 'critical', 'message' => "{$blockedTasks} task" . ($blockedTasks > 1 ? 's are' : ' is') . " blocked"];
        }

        // ── 3. Schedule adherence (if project has a deadline) ───────────────
        $metrics['schedule_gap'] = null;
        if ($project->start_date && $project->deadline) {
            $start    = Carbon::parse($project->start_date);
            $deadline = Carbon::parse($project->deadline);
            $today    = now();

            $totalDays   = max($start->diffInDays($deadline), 1);
            $elapsedDays = max(min($start->diffInDays($today), $totalDays), 0);
            $expectedProgress = round(($elapsedDays / $totalDays) * 100);
            $actualProgress   = (int) $project->progress;
            $gap = $expectedProgress - $actualProgress;

            $metrics['expected_progress'] = $expectedProgress;
            $metrics['actual_progress']   = $actualProgress;
            $metrics['schedule_gap']      = $gap;
            $metrics['days_remaining']    = max($deadline->diffInDays($today, false) * -1, 0);

            if ($gap > 40) {
                $score -= 25;
                $factors[] = ['type' => 'critical', 'message' => "Project is {$gap}% behind schedule"];
            } elseif ($gap > 20) {
                $score -= 15;
                $factors[] = ['type' => 'warning', 'message' => "Project is {$gap}% behind schedule"];
            } elseif ($gap > 10) {
                $score -= 8;
                $factors[] = ['type' => 'info', 'message' => "Slightly behind schedule ({$gap}% gap)"];
            }

            // Deadline passed and not completed
            if ($today->gt($deadline) && $actualProgress < 100) {
                $score -= 15;
                $factors[] = ['type' => 'critical', 'message' => 'Deadline has passed — project not completed'];
            }
        }

        // ── 4. Open critical / blocker bugs ─────────────────────────────────
        $criticalBugs = DB::table('bugs')
            ->where('project_id', $project->id)
            ->whereIn('severity', ['critical', 'blocker'])
            ->whereNull('resolved_by')
            ->count();

        $metrics['critical_bugs'] = $criticalBugs;

        if ($criticalBugs >= 3) {
            $score -= 20;
            $factors[] = ['type' => 'critical', 'message' => "{$criticalBugs} critical/blocker bugs unresolved"];
        } elseif ($criticalBugs >= 1) {
            $score -= 10;
            $factors[] = ['type' => 'warning', 'message' => "{$criticalBugs} critical bug" . ($criticalBugs > 1 ? 's' : '') . " unresolved"];
        }

        // ── 5. Task completion rate (if project has been running a while) ───
        if ($totalTasks > 0 && $completionRate < 30 && $metrics['schedule_gap'] !== null && $metrics['expected_progress'] > 30) {
            $score -= 10;
            $factors[] = ['type' => 'warning', 'message' => "Only {$completionRate}% of tasks completed"];
        }

        // ── 6. Budget overrun (if budget exists) ────────────────────────────
        $budget = DB::table('project_budgets')
            ->where('project_id', $project->id)
            ->where('status', 'active')
            ->value('total_budget');

        $metrics['budget'] = $budget;
        $metrics['spent']  = null;

        if ($budget) {
            $spent = DB::table('project_expenses')
                ->where('project_id', $project->id)
                ->sum('amount');

            $metrics['spent']           = round($spent, 2);
            $metrics['budget_used_pct'] = round(($spent / $budget) * 100);

            if ($spent > $budget) {
                $score -= 20;
                $factors[] = ['type' => 'critical', 'message' => 'Budget exceeded'];
            } elseif ($spent > $budget * 0.9) {
                $score -= 10;
                $factors[] = ['type' => 'warning', 'message' => 'Budget at ' . round(($spent / $budget) * 100) . '% utilization'];
            }
        }

        // ── Clamp score ──────────────────────────────────────────────────────
        $score = max(0, min(100, $score));

        // ── Positive factor if all good ──────────────────────────────────────
        if (empty($factors)) {
            $factors[] = ['type' => 'success', 'message' => 'All indicators are healthy'];
        }

        $status = match (true) {
            $score >= 80 => 'healthy',
            $score >= 50 => 'at_risk',
            default      => 'critical',
        };

        return [
            'score'   => $score,
            'status'  => $status,
            'factors' => $factors,
            'metrics' => $metrics,
        ];
    }
}
