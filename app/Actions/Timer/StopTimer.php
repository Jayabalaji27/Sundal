<?php

namespace App\Actions\Timer;

use App\Actions\ActionException;
use App\Models\TimesheetEntry;
use App\Models\User;
use Carbon\Carbon;

/**
 * Stops the actor's timer: the elapsed time goes on the timer's entry and
 * the timer resets. Shared by TimerController and the AI assistant.
 *
 * @return array{hours: float, seconds: int, entry_id: ?int}
 */
class StopTimer
{
    public function handle(User $actor): array
    {
        $totalSeconds = (int) $actor->timer_elapsed_seconds;
        if ($actor->timer_started_at) {
            $totalSeconds += (int) Carbon::parse($actor->timer_started_at)->diffInSeconds(now());
        }
        $hours = round($totalSeconds / 3600, 2);
        $entryId = $actor->timer_entry_id;

        if ($entryId && ($entry = TimesheetEntry::find($entryId))) {
            $entry->update(['end_time' => now()->format('H:i:s'), 'hours' => $hours]);
            $entry->timesheet?->calculateTotals();
        }

        $actor->update([
            'timer_active' => false,
            'timer_project_id' => null,
            'timer_task_id' => null,
            'timer_started_at' => null,
            'timer_description' => null,
            'timer_elapsed_seconds' => 0,
            'timer_entry_id' => null,
        ]);

        return ['hours' => $hours, 'seconds' => $totalSeconds, 'entry_id' => $entryId];
    }

    /** Like handle(), but refuses when no timer is running. */
    public function running(User $actor): array
    {
        if (!$actor->timer_active) {
            throw new ActionException(__('No active timer'));
        }

        return $this->handle($actor);
    }
}
