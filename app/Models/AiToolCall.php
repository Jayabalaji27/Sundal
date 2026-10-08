<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit row for every tool the assistant calls. Write tools start as
 * `pending` and only run when the user confirms the card.
 */
class AiToolCall extends Model
{
    use BelongsToWorkspace;

    public const PENDING = 'pending';
    public const DONE = 'done';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';

    protected $fillable = [
        'workspace_id', 'user_id', 'ai_conversation_id', 'ai_message_id', 'prompt', 'tool', 'input', 'payload',
        'summary', 'status', 'result', 'error', 'subject_type', 'subject_id',
        'provider', 'model', 'confirmed_at',
    ];

    protected $casts = [
        'input' => 'array',
        'payload' => 'array',
        'result' => 'array',
        'confirmed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function isExpired(): bool
    {
        return $this->status === self::PENDING
            && $this->created_at->lt(now()->subMinutes(config('ai_assistant.confirmation_ttl_minutes', 30)));
    }

    /** Shape sent to the AI Assistant page for confirm cards and results. */
    public function toCard(): array
    {
        return [
            'id' => $this->id,
            'tool' => $this->tool,
            'summary' => $this->summary,
            'status' => $this->isExpired() ? self::EXPIRED : $this->status,
            'details' => $this->payload['details'] ?? [],
            'link' => $this->result['link'] ?? null,
            'error' => $this->error,
        ];
    }
}
