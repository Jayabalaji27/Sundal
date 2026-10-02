<?php

namespace App\Events;

use App\Models\ProjectMilestone;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MilestoneStatusUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $milestone;
    public $oldStatus;
    public $newStatus;

    public function __construct(ProjectMilestone $milestone, $oldStatus, $newStatus)
    {
        $this->milestone = $milestone;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }
}