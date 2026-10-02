<?php

namespace App\Events;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectMemberAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Project $project,
        public User $assignedUser,
        public User $assignedBy,
        public string $role
    ) {
        //
    }
}