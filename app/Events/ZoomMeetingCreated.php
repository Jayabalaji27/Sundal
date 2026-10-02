<?php

namespace App\Events;

use App\Models\ZoomMeeting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ZoomMeetingCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $meeting;

    public function __construct(ZoomMeeting $meeting)
    {
        $this->meeting = $meeting;
    }
}