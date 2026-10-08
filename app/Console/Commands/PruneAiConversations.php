<?php

namespace App\Console\Commands;

use App\Models\AiConversation;
use App\Models\AiProviderSetting;
use App\Models\AiToolCall;
use Illuminate\Console\Command;

/**
 * Deletes AI Assistant chats older than each workspace's retention period
 * and expires confirm cards nobody acted on. Audit rows (ai_tool_calls)
 * stay; deleting a chat only removes their prompt text.
 */
class PruneAiConversations extends Command
{
    protected $signature = 'ai:prune-conversations';

    protected $description = 'Delete AI Assistant conversations past their retention period';

    public function handle(): int
    {
        $default = (int) config('ai_assistant.retention_days', 90);
        $retention = AiProviderSetting::pluck('retention_days', 'workspace_id');
        $deleted = 0;

        AiConversation::query()
            ->where('last_message_at', '<', now()->subDays(min([$default, ...$retention->values()->all()])))
            ->orWhere(fn ($q) => $q->whereNull('last_message_at')->where('created_at', '<', now()->subDays($default)))
            ->chunkById(200, function ($conversations) use ($retention, $default, &$deleted) {
                foreach ($conversations as $conversation) {
                    $days = (int) ($retention[$conversation->workspace_id] ?? $default);
                    $lastActive = $conversation->last_message_at ?? $conversation->created_at;
                    if ($lastActive->lt(now()->subDays($days))) {
                        $conversation->delete();
                        $deleted++;
                    }
                }
            });

        $expired = AiToolCall::where('status', AiToolCall::PENDING)
            ->where('created_at', '<', now()->subMinutes(config('ai_assistant.confirmation_ttl_minutes', 30)))
            ->update(['status' => AiToolCall::EXPIRED]);

        $this->info("Deleted {$deleted} conversations, expired {$expired} confirm cards.");

        return self::SUCCESS;
    }
}
