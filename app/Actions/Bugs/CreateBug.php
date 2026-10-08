<?php

namespace App\Actions\Bugs;

use App\Actions\ActionException;
use App\Events\BugAssigned;
use App\Models\Bug;
use App\Models\BugStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reports a bug in the workspace's first status. Shared by BugController and
 * the AI assistant. Expects validated data with a project in the actor's
 * workspace; the actor is stored as reported_by.
 */
class CreateBug
{
    public function handle(User $actor, array $data): Bug
    {
        $firstStatus = BugStatus::forWorkspace($actor->current_workspace_id)->ordered()->first()
            ?? throw new ActionException(__('No bug status found. Please contact administrator.'));

        $assignedTo = $data['assigned_to'] ?? null;

        // One unit: if the notification step fails, the bug isn't left half-created.
        return DB::transaction(function () use ($actor, $data, $firstStatus, $assignedTo) {
            $bug = Bug::create([
                ...collect($data)->only([
                    'project_id', 'milestone_id', 'title', 'description', 'priority', 'severity',
                    'steps_to_reproduce', 'expected_behavior', 'actual_behavior', 'environment',
                    'start_date', 'end_date',
                ])->all(),
                'assigned_to' => $assignedTo,
                'bug_status_id' => $firstStatus->id,
                'reported_by' => $actor->id,
            ]);

            if ($assignedTo && ($assignedUser = User::find($assignedTo))) {
                $bug->load('project');
                if (!config('app.is_demo', true)) {
                    event(new BugAssigned($bug, $assignedUser, $actor));
                }
            }

            return $bug;
        });
    }
}
