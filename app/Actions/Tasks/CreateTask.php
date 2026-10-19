<?php

namespace App\Actions\Tasks;

use App\Events\TaskAssigned;
use App\Events\TaskCreated;
use App\Models\Task;
use App\Models\TaskStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a task (or a bug: type 'bug') in the workspace's first stage. Shared by TaskController
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
                // type 'bug' plus its details make it a bug (see Task::BUG_FIELDS).
                ...collect($data)->only(['project_id', 'type', 'milestone_id', 'epic_id', 'title', 'description', 'priority', 'story_points', 'start_date', 'end_date', ...Task::BUG_FIELDS])->all(),
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
