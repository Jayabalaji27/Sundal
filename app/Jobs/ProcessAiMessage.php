<?php

namespace App\Jobs;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\Ai\AiAccess;
use App\Services\Ai\AiAssistant;
use App\Services\Ai\AiProviderException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

/**
 * Answers one AI Assistant message on the queue (config ai_assistant.queue).
 * The page polls the conversation until the reply appears.
 *
 * Runs as the user who sent the message: workspace scoping, permission
 * checks and every "who did it" column read the signed-in user, so the job
 * signs them in for its own duration and signs them out after.
 */
class ProcessAiMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        public readonly int $userId,
        public readonly int $conversationId,
        public readonly int $messageId,
    ) {}

    public function handle(AiAssistant $assistant): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            return;
        }

        Auth::guard('web')->setUser($user);

        try {
            $conversation = AiConversation::where('user_id', $user->id)->find($this->conversationId);
            $message = AiMessage::find($this->messageId);
            if (!$conversation || !$message) {
                return;
            }

            if (!AiAccess::canUse($user)) {
                $conversation->messages()->create(['role' => 'assistant', 'content' => __('You no longer have access to the AI Assistant.'), 'is_error' => true]);

                return;
            }

            try {
                $assistant->respond($conversation, $user, $message->content);
            } catch (AiProviderException) {
                // respond() already saved the error in the chat.
            }
        } finally {
            Auth::guard('web')->forgetUser();
        }
    }
}
