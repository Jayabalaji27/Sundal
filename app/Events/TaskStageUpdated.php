<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskStageUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $task;
    public $oldStage;
    public $newStage;

    public function __construct(Task $task, $oldStage, $newStage)
    {
        $this->task = $task;
        $this->oldStage = $oldStage;
        $this->newStage = $newStage;
    }
}