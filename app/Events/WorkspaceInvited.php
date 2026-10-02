<?php

namespace App\Events;

use App\Models\WorkspaceInvitation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkspaceInvited
{
    use Dispatchable, SerializesModels;

    public function __construct(public WorkspaceInvitation $invitation) {
        //
    }
}