<?php

namespace App\Actions\Tasks;

use App\Events\TaskAssigned;
use App\Models\Task;
use App\Models\User;

/**
 * Assigns a task to a workspace member, optionally setting its due date,
 * and notifies the new assignee.
 */
class AssignTask
{
    public function handle(User $actor, Task $task, User $assignee, ?string $dueDate = null): Task
    {
        $changes = ['assigned_to' => $assignee->id];
        if ($dueDate !== null) {
            $changes['end_date'] = $dueDate;
        }

        $previous = $task->assigned_to;
        $task->update($changes);

        if ((int) $previous !== (int) $assignee->id) {
            $task->load('project');
            if (!config('app.is_demo', true)) {
                event(new TaskAssigned($task, $assignee, $actor));
            }
        }

        return $task;
    }
}
