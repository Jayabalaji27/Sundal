<?php

namespace App\Actions\Timer;

use App\Actions\ActionException;
use App\Actions\Timesheets\LogTime;
use App\Models\Project;
use App\Models\TimesheetEntry;
use App\Models\User;

/**
 * Starts the actor's timer on a project (and task). A 0-hour entry is
 * created on this week's timesheet and filled in when the timer stops. A
 * timer left running in another workspace is stopped first. Shared by
 * TimerController and the AI assistant.
 */
class StartTimer
{
    public function __construct(private readonly StopTimer $stopTimer) {}

    public function handle(User $actor, Project $project, ?int $taskId = null, ?string $description = null): TimesheetEntry
    {
        if (!$actor->current_workspace_id) {
            throw new ActionException(__('User must have a current workspace to start timer'));
        }

        if ($actor->timer_active) {
            $timerProject = Project::find($actor->timer_project_id);
            if ($timerProject && (int) $timerProject->workspace_id === (int) $actor->current_workspace_id) {
                throw new ActionException(__('Timer is already active'));
            }
            // Running in another workspace, or its project is gone: stop it first.
            $this->stopTimer->handle($actor);
        }

        $timesheet = LogTime::weekTimesheet($actor->id, (int) $actor->current_workspace_id, now()->toDateString());

        $entry = TimesheetEntry::create([
            'timesheet_id' => $timesheet->id,
            'project_id' => $project->id,
            'task_id' => $taskId,
            'user_id' => $actor->id,
            'date' => now()->toDateString(),
            'start_time' => now()->format('H:i:s'),
            'end_time' => null,
            'hours' => 0,
            'description' => $description ?? '',
            'is_billable' => true,
            'hourly_rate' => 0,
        ]);

        $actor->update([
            'timer_active' => true,
            'timer_project_id' => $project->id,
            'timer_task_id' => $taskId,
            'timer_started_at' => now(),
            'timer_description' => $description,
            'timer_elapsed_seconds' => 0,
            'timer_entry_id' => $entry->id,
        ]);

        return $entry;
    }
}
