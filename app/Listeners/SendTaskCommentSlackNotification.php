<?php

namespace App\Listeners;

use App\Events\TaskCommentAdded;
use App\Services\SlackService;

class SendTaskCommentSlackNotification
{
    public function handle(TaskCommentAdded $event): void
    {
        $comment = $event->taskComment;
        $task = $comment->task;
        $userId = $comment->user_id ?? auth()->id();
        $workspaceId = $task->project->workspace_id ?? null;
        
        if (!$userId) return;

        if (isSlackNotificationEnabled('new_task_comment', $userId, $workspaceId)) {
            $data = [
                'title' => 'New Task Comment',
                'message' => "A new comment has been added to task '{$task->title}'.",
                'task_name' => $task->title,
                'project_name' => $task->project->title ?? 'Unknown Project',
                'comment_by' => $comment->user->name ?? 'Unknown User',
                'url' => route('tasks.show', $task->id)
            ];

            SlackService::send('new_task_comment', $data, $userId, $workspaceId);
        }
    }
}