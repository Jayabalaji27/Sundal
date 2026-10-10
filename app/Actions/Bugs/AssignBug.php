<?php

namespace App\Actions\Bugs;

use App\Events\BugAssigned;
use App\Models\Bug;
use App\Models\User;

/**
 * Assigns a bug to a workspace member, optionally setting its due date,
 * and notifies the new assignee.
 */
class AssignBug
{
    public function handle(User $actor, Bug $bug, User $assignee, ?string $dueDate = null): Bug
    {
        $changes = ['assigned_to' => $assignee->id];
        if ($dueDate !== null) {
            $changes['end_date'] = $dueDate;
        }

        $previous = $bug->assigned_to;
        $bug->update($changes);

        if ((int) $previous !== (int) $assignee->id) {
            $bug->load('project');
            if (!config('app.is_demo', true)) {
                event(new BugAssigned($bug, $assignee, $actor));
            }
        }

        return $bug;
    }

    /** Undo: put back the previous assignee (or none) and due date. */
    public function revert(User $actor, Bug $bug, ?int $assignedTo, ?string $endDate): Bug
    {
        $bug->update(['assigned_to' => $assignedTo, 'end_date' => $endDate]);

        return $bug;
    }
}
