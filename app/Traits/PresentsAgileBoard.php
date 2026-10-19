<?php

namespace App\Traits;

use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskStage;
use Illuminate\Support\Collection;

/**
 * Shared shapes for the project Backlog and Board pages: a compact task card,
 * a sprint summary with task/point totals, and the workspace's task stages.
 */
trait PresentsAgileBoard
{
    /** Relations a task card needs; eager-load these before calling taskCard(). */
    protected function taskCardRelations(): array
    {
        return ['taskStage:id,name,color,is_completed', 'assignedTo:id,name,avatar', 'epic:id,name,color'];
    }

    /** 404 unless the project is in the current workspace and the user can see it. */
    protected function abortUnlessProjectVisible(Project $project): void
    {
        $user = auth()->user();
        abort_if($project->workspace_id !== $user->current_workspace_id, 404);
        abort_if(!Project::visibleTo($user)->whereKey($project->id)->exists(), 404);
    }

    protected function taskCard(Task $task): array
    {
        $assignee = $task->assignedTo;

        return [
            'id' => $task->id,
            'title' => $task->title,
            // 'task' or 'bug'; bugs also show their severity on the card.
            'type' => $task->type ?? 'task',
            'severity' => $task->isBug() ? $task->severity : null,
            'priority' => $task->priority,
            'story_points' => $task->story_points,
            'end_date' => $task->end_date?->toDateString(),
            'is_completed' => $task->isCompleted(),
            'task_stage_id' => $task->task_stage_id,
            'stage' => $task->taskStage ? $task->taskStage->only('id', 'name', 'color') : null,
            'epic' => $task->epic ? $task->epic->only('id', 'name', 'color') : null,
            'assignee' => $assignee ? [
                'id' => $assignee->id,
                'name' => $assignee->name,
                'avatar' => check_file($assignee->avatar) ? get_file($assignee->avatar) : null,
            ] : null,
        ];
    }

    /** @param  Collection<int, Task>|null  $tasks  the sprint's tasks, loaded with taskCardRelations() */
    protected function sprintSummary(Sprint $sprint, ?Collection $tasks = null): array
    {
        $tasks ??= $sprint->tasks;
        $done = $tasks->filter(fn (Task $task) => $task->isCompleted());
        // A completed sprint reports its frozen totals (its unfinished tasks moved on).
        $snapshot = $sprint->status === 'completed' && $sprint->committed_tasks !== null;

        return [
            'id' => $sprint->id,
            'name' => $sprint->name,
            'goal' => $sprint->goal,
            'status' => $sprint->status,
            'start_date' => $sprint->start_date?->toDateString(),
            'end_date' => $sprint->end_date?->toDateString(),
            // Whole days left until the end date, 0 on the last day, negative once overdue.
            'days_left' => $sprint->status === 'active' && $sprint->end_date
                ? (int) now()->startOfDay()->diffInDays($sprint->end_date->copy()->startOfDay(), false)
                : null,
            'task_count' => $snapshot ? $sprint->committed_tasks : $tasks->count(),
            'done_count' => $snapshot ? $sprint->completed_tasks : $done->count(),
            'points' => $snapshot ? (int) $sprint->committed_points : (int) $tasks->sum('story_points'),
            'done_points' => $snapshot ? (int) $sprint->completed_points : (int) $done->sum('story_points'),
            'carried_over' => $snapshot ? $sprint->committed_tasks - $sprint->completed_tasks : 0,
        ];
    }

    /**
     * What the task form (TaskFormModal) needs to add a task to this project
     * from inside it: the project with its team and milestones. Null when the
     * user may not create tasks.
     */
    protected function taskFormData(Project $project): ?array
    {
        if (!auth()->user()->hasWorkspacePermission('task_create')) {
            return null;
        }

        $team = $project->members()->with('user:id,name,email,avatar,type')->get()->pluck('user')->filter()->values();

        return [
            'projects' => [[
                'id' => $project->id,
                'title' => $project->title,
                'members' => $team->map(fn ($user) => ['user' => $user])->values(),
                'milestones' => $project->milestones()->get(['id', 'project_id', 'title']),
                // Open epics an item can be filed under.
                'epics' => $project->epics()->where('status', 'open')->orderBy('name')->get(['id', 'project_id', 'name', 'color']),
            ]],
            'members' => $team->reject(fn ($user) => $user->type === 'client')->values(),
        ];
    }

    protected function boardStages(): Collection
    {
        return TaskStage::forWorkspace(auth()->user()->current_workspace_id)->ordered()
            ->get(['id', 'name', 'color', 'is_completed']);
    }
}
