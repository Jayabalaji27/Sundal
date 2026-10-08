<?php

namespace App\Services\Ai;

/**
 * Marks model changes made while a confirmed AI tool call runs, so the
 * activity log can record them as "via AI assistant" with the tool call id.
 */
final class AiActionContext
{
    private static ?int $toolCallId = null;

    public static function run(int $toolCallId, callable $callback): mixed
    {
        $previous = self::$toolCallId;
        self::$toolCallId = $toolCallId;

        try {
            return $callback();
        } finally {
            self::$toolCallId = $previous;
        }
    }

    /** Extra activity-log metadata; empty outside an AI action. */
    public static function metadata(): array
    {
        return self::$toolCallId === null
            ? []
            : ['via' => 'ai_assistant', 'ai_tool_call_id' => self::$toolCallId];
    }
}
