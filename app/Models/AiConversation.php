<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversation extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'user_id', 'title', 'topic', 'is_favorite', 'archived_at', 'last_message_at'];

    protected $casts = [
        'is_favorite' => 'boolean',
        'archived_at' => 'datetime',
        'last_message_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // The audit rows outlive the chat: drop the prompt text and the links to
        // the chat, keep who/what/inputs/result. Done here rather than relying on
        // the foreign keys alone.
        static::deleting(function (AiConversation $conversation) {
            AiToolCall::withoutGlobalScope('workspace')
                ->where('ai_conversation_id', $conversation->id)
                ->update(['prompt' => null, 'ai_conversation_id' => null, 'ai_message_id' => null]);
            AiUsage::withoutGlobalScope('workspace')
                ->where('ai_conversation_id', $conversation->id)
                ->update(['ai_conversation_id' => null]);
            $conversation->messages()->delete();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class)->orderBy('id');
    }

    public function toolCalls(): HasMany
    {
        return $this->hasMany(AiToolCall::class)->orderBy('id');
    }

    public function scopeOwnedBy($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }
}
