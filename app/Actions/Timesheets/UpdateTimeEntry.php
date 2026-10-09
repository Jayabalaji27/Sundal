<?php

namespace App\Actions\Timesheets;

use App\Actions\ActionException;
use App\Models\TimesheetEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Changes a time entry. Only the keys present in $data change; a new date
 * can move it to another week's timesheet. Both timesheets must be
 * unlocked. Shared by TimesheetEntryController and the AI assistant.
 */
class UpdateTimeEntry
{
    public const FIELDS = ['project_id', 'task_id', 'date', 'start_time', 'end_time', 'hours', 'description', 'is_billable'];

    public function handle(User $actor, TimesheetEntry $entry, array $data): TimesheetEntry
    {
        $changes = array_intersect_key($data, array_flip(self::FIELDS));
        $merged = [
            'date' => $entry->date instanceof \DateTimeInterface ? $entry->date->format('Y-m-d') : (string) $entry->date,
            'hours' => $entry->hours,
            'start_time' => $entry->start_time,
            'end_time' => $entry->end_time,
            ...$changes,
        ];
        if ($problem = LogTime::hoursProblem($merged, (int) $entry->user_id, $entry->id)) {
            throw new ActionException($problem[1]);
        }

        return DB::transaction(function () use ($entry, $changes, $merged) {
            $oldTimesheet = $entry->timesheet;
            $newTimesheet = LogTime::weekTimesheet((int) $entry->user_id, (int) ($oldTimesheet?->workspace_id ?? $entry->project?->workspace_id), $merged['date']);

            if ($oldTimesheet?->isLocked() || $newTimesheet->isLocked()) {
                throw new ActionException(__('Submitted or approved timesheets cannot be edited or deleted.'));
            }

            $entry->update([...$changes, 'timesheet_id' => $newTimesheet->id]);

            // A new date can move the entry to another week: recalculate both.
            $newTimesheet->calculateTotals();
            if ($oldTimesheet && $oldTimesheet->id !== $newTimesheet->id) {
                $oldTimesheet->calculateTotals();
            }

            return $entry;
        });
    }
}
