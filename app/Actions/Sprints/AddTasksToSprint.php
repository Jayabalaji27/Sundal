<?php

namespace App\Actions\Sprints;

use App\Actions\ActionException;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Adds tasks to a sprint, recording who added each one (sprint_tasks.created_by).
 * Shared by SprintController and the AI assistant. Only tasks of the sprint's
 * own project can join it, and a completed sprint takes no new tasks.
 */
class AddTasksToSprint
{
    /** @param  Collection<int, Task>|Task[]  $tasks */
    public function handle(User $actor, Sprint $sprint, iterable $tasks): int
    {
        if ($sprint->status === 'completed') {
            throw new ActionException(__('Sprint ":name" is completed; tasks cannot be added.', ['name' => $sprint->name]));
        }

        $added = 0;
        foreach ($tasks as $task) {
            if ((int) $task->project_id !== (int) $sprint->project_id) {
                throw new ActionException(__('Task ":title" belongs to another project than sprint ":name".', ['title' => $task->title, 'name' => $sprint->name]));
            }
            if (!$sprint->tasks()->whereKey($task->id)->exists()) {
                $sprint->tasks()->attach($task->id, ['created_by' => $actor->id]);
                $added++;
            }
        }

        return $added;
    }
}
