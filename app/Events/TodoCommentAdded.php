<?php

namespace App\Events;

use App\Models\Todo;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TodoCommentAdded
{
    use Dispatchable, SerializesModels;

    public $todo;
    public $activityType;

    public function __construct(Todo $todo, string $activityType)
    {
        $this->todo = $todo;
        $this->activityType = $activityType; // 'comment' or 'attachment'
    }
}
