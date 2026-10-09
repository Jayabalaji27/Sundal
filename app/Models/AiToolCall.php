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
    public const UNDONE = 'undone';

    /** Minutes after confirming during which the Undo link works. */
    public const UNDO_MINUTES = 10;

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

    public function canUndo(): bool
    {
        return $this->status === self::DONE
            && !empty($this->result['undo'])
            && $this->confirmed_at?->gt(now()->subMinutes(self::UNDO_MINUTES));
    }

    /** @return string[] required draft fields still without a value */
    public function missingFields(): array
    {
        return collect($this->payload['form']['fields'] ?? [])
            ->filter(fn (array $f) => $f['required'] && in_array($f['value'], [null, '', []], true))
            ->pluck('name')
            ->values()
            ->all();
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
            'items' => $this->payload['items'] ?? [],
            'confirm_phrase' => $this->payload['confirm_phrase'] ?? null,
            // Drafts: "question" while a must-know value is missing (answered with
            // buttons or a short reply), then "review" (confirm, or Edit to change).
            'stage' => $this->payload['stage'] ?? 'review',
            'ask' => $this->missingFields(),
            'question' => collect($this->payload['form']['fields'] ?? [])->firstWhere('name', $this->missingFields()[0] ?? null)['question'] ?? null,
            'fields' => $this->payload['form']['fields'] ?? [],
            'form_error' => $this->payload['form']['error'] ?? null,
            'link' => $this->result['link'] ?? null,
            'can_undo' => $this->canUndo(),
            'undo_until' => $this->canUndo() ? $this->confirmed_at->addMinutes(self::UNDO_MINUTES)->toIso8601String() : null,
            'error' => $this->error,
        ];
    }
}
