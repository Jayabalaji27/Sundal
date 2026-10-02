<?php

namespace App\Notifications;

use App\Models\ChatMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewChatMessageNotification extends Notification
{
    public function __construct(protected ChatMessage $message)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $conversation = $this->message->conversation;

        return [
            'type' => 'chat_message',
            'workspace_id' => $conversation->workspace_id,
            'conversation_id' => $conversation->id,
            'sender_id' => $this->message->user_id,
            'sender_name' => $this->message->sender->name ?? '',
            'title' => 'New message from ' . ($this->message->sender->name ?? 'someone'),
            'content' => Str::limit($this->message->message, 100),
            'link' => route('chat.index', ['conversation' => $conversation->id]),
        ];
    }
}
