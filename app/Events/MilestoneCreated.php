<?php

namespace App\Events;

use App\Models\ProjectMilestone;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MilestoneCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $milestone;

    public function __construct(ProjectMilestone $milestone)
    {
        $this->milestone = $milestone;
    }
}