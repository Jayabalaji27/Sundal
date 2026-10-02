<?php

namespace App\Events;

use App\Models\Todo;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TodoStatusUpdated
{
    use Dispatchable, SerializesModels;

    public $todo;
    public $oldStatus;

    public function __construct(Todo $todo, string $oldStatus)
    {
        $this->todo = $todo;
        $this->oldStatus = $oldStatus;
    }
}
