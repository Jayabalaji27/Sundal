<?php

namespace App\Actions\Timesheets;

use App\Actions\ActionException;
use App\Models\Timesheet;
use App\Models\TimesheetApproval;
use App\Models\User;

/**
 * Sends the actor's own draft or rejected timesheet to the workspace owner
 * for approval. Shared by TimesheetController and the AI assistant.
 */
class SubmitTimesheet
{
    public function handle(User $actor, Timesheet $timesheet): Timesheet
    {
        if ((int) $timesheet->user_id !== (int) $actor->id) {
            throw new ActionException(__('You can only submit your own timesheets.'));
        }
        if (!in_array($timesheet->status, ['draft', 'rejected'], true)) {
            throw new ActionException(__('Only draft or rejected timesheets can be submitted.'));
        }
        if (!$timesheet->entries()->exists()) {
            throw new ActionException(__('Cannot submit a timesheet without entries.'));
        }

        $timesheet->update(['status' => 'submitted', 'submitted_at' => now()]);

        if ($owner = $timesheet->workspace?->owner) {
            TimesheetApproval::create([
                'timesheet_id' => $timesheet->id,
                'approver_id' => $owner->id,
                'status' => 'pending',
            ]);
        }

        return $timesheet;
    }
}
