<?php

namespace App\Listeners;

use App\Events\TaskStageUpdated;
use App\Services\TelegramService;

class SendTaskStageUpdateTelegramNotification
{
    public function handle(TaskStageUpdated $event): void
    {
        $task = $event->task;
        $userId = auth()->id();
        $workspaceId = $task->project->workspace_id ?? null;
        
        if (!$userId) return;

        if (isTelegramNotificationEnabled('task_stage_updated', $userId, $workspaceId)) {
            $data = [
                'title' => 'Task Stage Updated',
                'message' => "Task '{$task->title}' stage updated from '{$event->oldStage}' to '{$event->newStage}'.",
                'task_name' => $task->title,
                'project_name' => $task->project->title ?? 'Unknown Project',
                'old_stage' => $event->oldStage,
                'new_stage' => $event->newStage,
                'url' => route('tasks.show', $task->id)
            ];

            TelegramService::send('task_stage_updated', $data, $userId, $workspaceId);
        }
    }
}