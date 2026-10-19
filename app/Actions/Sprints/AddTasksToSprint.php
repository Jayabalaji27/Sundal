<?php

namespace App\Actions\Sprints;

use App\Actions\ActionException;
use App\Models\Sprint;
use App\Models\SprintTask;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Adds tasks to a sprint, recording who added each one (sprint_tasks.created_by).
 * Shared by SprintController and the AI assistant. Only tasks of the sprint's
 * own project can join it, and a completed sprint takes no new tasks. A task is
 * in one open sprint at a time, so adding it here moves it out of any other
 * planned or active sprint (completed sprints keep their history).
 */
class AddTasksToSprint
{
    /** @param  Collection<int, Task>|Task[]  $tasks */
    public function handle(User $actor, Sprint $sprint, iterable $tasks): int
    {
        if ($sprint->status === 'completed') {
            throw new ActionException(__('Sprint ":name" is completed; tasks cannot be added.', ['name' => $sprint->name]));
        }

        $otherOpenSprints = Sprint::withoutGlobalScopes()
            ->where('project_id', $sprint->project_id)
            ->whereIn('status', ['planning', 'active'])
            ->whereKeyNot($sprint->id)
            ->pluck('id');

        $added = 0;
        foreach ($tasks as $task) {
            if ((int) $task->project_id !== (int) $sprint->project_id) {
                throw new ActionException(__('Task ":title" belongs to another project than sprint ":name".', ['title' => $task->title, 'name' => $sprint->name]));
            }
            // One by one, so the move out of the other sprint is in the task's history.
            SprintTask::whereIn('sprint_id', $otherOpenSprints)->where('task_id', $task->id)->get()->each->delete();
            if (!$sprint->tasks()->whereKey($task->id)->exists()) {
                $sprint->tasks()->attach($task->id, ['created_by' => $actor->id]);
                $added++;
            }
        }

        return $added;
    }
}
