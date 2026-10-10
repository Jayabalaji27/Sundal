<?php

namespace App\Actions\Tasks;

use App\Events\TaskStageUpdated;
use App\Models\Task;
use App\Models\TaskStage;
use App\Models\User;

/**
 * Moves a task to another stage. Shared by TaskController and the AI
 * assistant. Permission and workspace checks stay with the caller.
 */
class ChangeTaskStage
{
    public function handle(User $actor, Task $task, TaskStage $stage): Task
    {
        $oldStage = $task->taskStage->name ?? 'Unknown';

        $task->update(['task_stage_id' => $stage->id]);

        if (!config('app.is_demo', true)) {
            event(new TaskStageUpdated($task, $oldStage, $stage->name));
        }

        return $task;
    }
}
