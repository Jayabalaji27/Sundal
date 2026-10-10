<?php

namespace App\Services\Ai\Tools;

use App\Actions\Timer\StopTimer;
use App\Models\Project;
use App\Models\TimesheetEntry;
use App\Models\User;
use Carbon\Carbon;

/** Stop the user's running timer; the time goes on its timesheet entry. */
class StopTimerTool extends AiTool
{
    public function __construct(private readonly StopTimer $stopTimer) {}

    public function name(): string
    {
        return 'stop_timer';
    }

    public function description(): string
    {
        return 'Stop the user\'s running timer and save the time on their timesheet. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['timesheet_use_timer'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        if (!$user->timer_active) {
            throw new ToolInputException(__('No timer is running.'));
        }

        $seconds = (int) $user->timer_elapsed_seconds
            + ($user->timer_started_at ? (int) Carbon::parse($user->timer_started_at)->diffInSeconds(now()) : 0);

        return new PreparedAction(
            summary: __('Stop the timer (:hours h so far)', ['hours' => round($seconds / 3600, 2)]),
            details: array_filter([
                __('Project') => Project::whereKey($user->timer_project_id)->value('title'),
                __('Started') => $user->timer_started_at ? Carbon::parse($user->timer_started_at)->format('Y-m-d H:i') : null,
                __('Time so far') => sprintf('%d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60)),
            ]),
            payload: ['entry_id' => $user->timer_entry_id],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        // The card belongs to the timer it was shown for, not one started since.
        if (!$user->timer_active || (int) $user->timer_entry_id !== (int) $payload['entry_id']) {
            throw new ToolInputException(__('That timer is no longer running.'));
        }

        $stopped = $this->stopTimer->running($user);
        $entry = $stopped['entry_id'] ? TimesheetEntry::find($stopped['entry_id']) : null;

        return new ToolOutcome(
            __('Timer stopped: :hours h saved on your timesheet.', ['hours' => $stopped['hours'] + 0]),
            $entry,
            $entry ? route('timesheets.show', $entry->timesheet_id, false) : null,
        );
    }
}
