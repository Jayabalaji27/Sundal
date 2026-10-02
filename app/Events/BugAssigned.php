<?php

namespace App\Events;

use App\Models\Bug;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BugAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Bug $bug,
        public User $assignedUser,
        public User $assignedBy
    ) {
        //
    }
}