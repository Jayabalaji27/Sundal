<?php

namespace App\Notifications;

use App\Models\TaskComment;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Bell notification for a task comment: "mention" when the comment @mentions the
 * user, "comment" for the task's assignee and creator.
 */
class TaskCommentNotification extends Notification
{
    public function __construct(protected TaskComment $comment, protected string $kind = 'comment')
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $task = $this->comment->task;
        $sender = $this->comment->user?->name ?? __('Someone');

        return [
            'type' => $this->kind,
            'workspace_id' => $task->project->workspace_id,
            'task_id' => $task->id,
            'comment_id' => $this->comment->id,
            'sender_id' => $this->comment->user_id,
            'sender_name' => $sender,
            'title' => $this->kind === 'mention'
                ? __(':name mentioned you on ":task"', ['name' => $sender, 'task' => $task->title])
                : __(':name commented on ":task"', ['name' => $sender, 'task' => $task->title]),
            'content' => Str::limit(trim(preg_replace('/\s+/', ' ', $this->comment->comment)), 120),
            'link' => route('tasks.index', ['task_id' => $task->id]),
        ];
    }
}
