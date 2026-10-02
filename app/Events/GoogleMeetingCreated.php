<?php

namespace App\Events;

use App\Models\GoogleMeeting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GoogleMeetingCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $meeting;

    public function __construct(GoogleMeeting $meeting)
    {
        $this->meeting = $meeting;
    }
}