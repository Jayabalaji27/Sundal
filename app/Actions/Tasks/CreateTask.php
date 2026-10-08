<?php

namespace App\Actions\Tasks;

use App\Events\TaskAssigned;
use App\Events\TaskCreated;
use App\Models\Task;
use App\Models\TaskStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a task in the workspace's first stage. Shared by TaskController
 * and the AI assistant, so both fire the same notifications. The acting
 * user is passed in, never read from the session.
 *
 * Expects $data already validated and the project already checked to be
 * in the actor's workspace.
 */
class CreateTask
{
    public function handle(User $actor, array $data): Task
    {
        $firstStage = TaskStage::forWorkspace($actor->current_workspace_id)->ordered()->first();
        $assignedTo = $data['assigned_to'] ?? null;

        // One unit: if a follow-up step fails, no half-created task is left behind.
        return DB::transaction(function () use ($actor, $data, $firstStage, $assignedTo) {
            $task = Task::create([
                ...collect($data)->only(['project_id', 'milestone_id', 'title', 'description', 'priority', 'start_date', 'end_date'])->all(),
                'assigned_to' => $assignedTo,
                'task_stage_id' => $firstStage?->id,
                'created_by' => $actor->id,
                'progress' => 0,
            ]);

            if (!config('app.is_demo', true)) {
                event(new TaskCreated($task));
            }

            if ($assignedTo && ($assignedUser = User::find($assignedTo))) {
                $task->load('project');
                if (!config('app.is_demo', true)) {
                    event(new TaskAssigned($task, $assignedUser, $actor));
                }
            }

            return $task;
        });
    }
}
