<?php

namespace App\Services\Ai;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiToolCall;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * The AI Assistant sidebar: the user's own chats, newest first, a page at a
 * time, with a one-line preview and how many confirm cards wait in each.
 *
 * Filters: all (not archived), favorites (starred, not archived), waiting
 * (a confirm card still waits for the user), archived.
 */
class ConversationList
{
    public const FILTERS = ['all', 'favorites', 'waiting', 'archived'];

    public const PER_PAGE = 20;

    private const PREVIEW_LENGTH = 90;

    /** @return array{conversations: array<int, array<string, mixed>>, has_more: bool, counts: array<string, int>} */
    public function page(User $user, string $filter = 'all', int $page = 1): array
    {
        $page = max(1, $page);
        $rows = $this->filtered($user, $filter)
            ->addSelect(['preview' => AiMessage::select('content')
                ->whereColumn('ai_conversation_id', 'ai_conversations.id')
                ->reorder('id', 'desc')
                ->limit(1)])
            ->withCount(['toolCalls as waiting_count' => fn ($q) => $this->waitingCards($q, $user)])
            ->orderByDesc('last_message_at')->orderByDesc('id')
            ->skip(($page - 1) * self::PER_PAGE)
            ->limit(self::PER_PAGE + 1)
            ->get();

        return [
            'conversations' => $rows->take(self::PER_PAGE)->map(fn (AiConversation $c) => self::row($c))->values()->all(),
            'has_more' => $rows->count() > self::PER_PAGE,
            'counts' => $this->counts($user),
        ];
    }

    /** @return array{favorites: int, waiting: int, archived: int} */
    public function counts(User $user): array
    {
        return [
            'favorites' => $this->filtered($user, 'favorites')->count(),
            'waiting' => $this->filtered($user, 'waiting')->count(),
            'archived' => $this->filtered($user, 'archived')->count(),
        ];
    }

    /** One chat as the sidebar shows it. */
    public static function row(AiConversation $conversation): array
    {
        $preview = $conversation->getAttribute('preview');
        if ($preview === null && !array_key_exists('preview', $conversation->getAttributes())) {
            $preview = $conversation->messages()->reorder('id', 'desc')->value('content');
        }

        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'topic' => $conversation->topic,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'is_favorite' => (bool) $conversation->is_favorite,
            'archived' => $conversation->archived_at !== null,
            'preview' => self::plain((string) $preview),
            'waiting' => (int) ($conversation->getAttribute('waiting_count') ?? 0),
        ];
    }

    private function filtered(User $user, string $filter): Builder
    {
        $query = AiConversation::ownedBy($user)->where('workspace_id', $user->current_workspace_id);

        return match ($filter) {
            'archived' => $query->whereNotNull('archived_at'),
            'favorites' => $query->whereNull('archived_at')->where('is_favorite', true),
            'waiting' => $query->whereNull('archived_at')->whereHas('toolCalls', fn ($q) => $this->waitingCards($q, $user)),
            default => $query->whereNull('archived_at'),
        };
    }

    /** Cards still waiting for this user: pending and not past the confirmation time. */
    private function waitingCards($query, User $user)
    {
        return $query->where('user_id', $user->id)
            ->where('status', AiToolCall::PENDING)
            ->where('created_at', '>=', now()->subMinutes(config('ai_assistant.confirmation_ttl_minutes', 30)));
    }

    /** "**Done**: see [INV-2](/invoices/2)" → "Done: see INV-2", one line. */
    private static function plain(string $text): string
    {
        $text = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $text);
        $text = str_replace(['**', '`'], '', (string) $text);
        $text = trim(preg_replace('/\s+/', ' ', $text));

        return Str::limit($text, self::PREVIEW_LENGTH);
    }
}
