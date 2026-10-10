<?php

namespace App\Actions\Timesheets;

use App\Actions\ActionException;
use App\Models\TimesheetEntry;
use App\Models\User;

/** Deletes a time entry from an unlocked timesheet. Shared by TimesheetEntryController and the AI assistant. */
class DeleteTimeEntry
{
    public function handle(User $actor, TimesheetEntry $entry): void
    {
        $timesheet = $entry->timesheet;
        if ($timesheet?->isLocked()) {
            throw new ActionException(__('Submitted or approved timesheets cannot be edited or deleted.'));
        }

        $entry->delete();
        $timesheet?->calculateTotals();
    }
}
