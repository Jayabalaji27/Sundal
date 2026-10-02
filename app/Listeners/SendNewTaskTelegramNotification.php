<?php

namespace App\Listeners;

use App\Events\TaskCreated;
use App\Services\TelegramService;

class SendNewTaskTelegramNotification
{
    public function handle(TaskCreated $event): void
    {
        $task = $event->task;
        $userId = $task->created_by ?? auth()->id();
        $workspaceId = $task->project->workspace_id ?? null;
        
        if (!$userId) return;

        if (isTelegramNotificationEnabled('new_task', $userId, $workspaceId)) {
            $data = [
                'title' => 'New Task Created',
                'message' => "A new task '{$task->title}' has been created.",
                'task_name' => $task->title,
                'project_name' => $task->project->title ?? 'Unknown Project',
                'assigned_to' => $task->assignedTo->name ?? 'Unassigned',
                'url' => route('tasks.show', $task->id)
            ];

            TelegramService::send('new_task', $data, $userId, $workspaceId);
        }
    }
}