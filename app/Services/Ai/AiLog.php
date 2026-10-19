<?php

namespace App\Services\Ai;

use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The AI assistant's own log, storage/logs/ai-YYYY-MM-DD.log: one line per
 * reply, card decision, voice transcription, settings change and provider
 * error, with who, which workspace, provider, model, time taken and tokens.
 * What people said stays in the database (ai_messages, ai_tool_calls) and is
 * never written here. Each line also carries the request_id of the request
 * it came from (App\Http\Middleware\AssignRequestId).
 */
final class AiLog
{
    public static function info(string $event, array $context = []): void
    {
        self::write('info', $event, $context);
    }

    public static function warning(string $event, array $context = []): void
    {
        self::write('warning', $event, $context);
    }

    public static function error(string $event, array $context = []): void
    {
        self::write('error', $event, $context);
    }

    /** Who asked, in which workspace and conversation. */
    public static function who(User $user, ?AiConversation $conversation = null): array
    {
        return [
            'user_id' => $user->id,
            'workspace_id' => $conversation?->workspace_id ?? $user->current_workspace_id,
            'conversation_id' => $conversation?->id,
        ];
    }

    /** Milliseconds since $started (an hrtime(true) value). */
    public static function ms(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1e6);
    }

    /** The class and message of an unexpected error, for the log line. */
    public static function describe(Throwable $e): string
    {
        return class_basename($e) . ': ' . mb_substr($e->getMessage(), 0, 500);
    }

    private static function write(string $level, string $event, array $context): void
    {
        try {
            Log::channel('ai')->log($level, $event, array_filter($context, fn ($value) => $value !== null && $value !== []));
        } catch (Throwable) {
            // Logging must never break the assistant.
        }
    }
}
