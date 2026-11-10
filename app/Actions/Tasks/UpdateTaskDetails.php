<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Services\GoogleCalendarService;

/**
 * Changes a task's or bug's own details (title, description, priority,
 * points, dates, milestone, bug fields) for the AI assistant. Assignment,
 * stage, sprint and epic have their own actions. A task already synced to
 * Google Calendar gets its event updated, as the task form does.
 */
class UpdateTaskDetails
{
    public const FIELDS = ['title', 'description', 'priority', 'story_points', 'start_date', 'end_date', 'milestone_id', ...Task::BUG_FIELDS];

    public function __construct(private readonly GoogleCalendarService $calendar) {}

    public function handle(User $actor, Task $task, array $changes): Task
    {
        $task->update(array_intersect_key($changes, array_flip(self::FIELDS)));

        if ($task->google_calendar_event_id) {
            try {
                $this->calendar->updateEvent($task->google_calendar_event_id, $task, $actor->id, $actor->current_workspace_id);
            } catch (\Throwable $e) {
                \Log::error('Failed to sync task with Google Calendar', ['task_id' => $task->id, 'error' => $e->getMessage()]);
            }
        }

        return $task;
    }
}
