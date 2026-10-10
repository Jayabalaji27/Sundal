<?php

namespace App\Actions\Bugs;

use App\Models\Bug;
use App\Models\BugStatus;
use App\Models\User;

/**
 * Moves a bug to another status, logs the change, and records who resolved
 * it when it reaches Resolved or Closed. Shared by BugController and the AI
 * assistant.
 */
class ChangeBugStatus
{
    public function handle(User $actor, Bug $bug, BugStatus $status): Bug
    {
        $oldStatus = $bug->bugStatus->name ?? 'Unknown';

        $bug->update(['bug_status_id' => $status->id]);
        $bug->logStatusChange($oldStatus, $status->name);

        if (in_array($status->name, ['Resolved', 'Closed']) && !$bug->resolved_by) {
            $bug->update(['resolved_by' => $actor->id]);
            $bug->logResolution($actor);
        }

        return $bug;
    }
}
