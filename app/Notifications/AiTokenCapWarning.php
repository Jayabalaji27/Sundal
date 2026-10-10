<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent once a month to the workspace owner when AI Assistant usage reaches
 * 80% of the monthly token cap they set.
 */
class AiTokenCapWarning extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $workspaceName,
        private readonly int $used,
        private readonly int $cap,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $percent = (int) floor($this->used / max(1, $this->cap) * 100);

        return (new MailMessage)
            ->subject(__('AI Assistant: :percent% of the monthly token cap used', ['percent' => $percent]))
            ->line(__('The AI Assistant in ":workspace" has used :used of its :cap token monthly cap (:percent%).', [
                'workspace' => $this->workspaceName,
                'used' => number_format($this->used),
                'cap' => number_format($this->cap),
                'percent' => $percent,
            ]))
            ->line(__('When the cap is reached, the assistant stops answering until next month or until you raise the cap.'))
            ->action(__('Open AI settings'), route('ai-assistant.index'));
    }
}
