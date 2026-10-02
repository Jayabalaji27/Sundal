<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Concerns\BelongsToWorkspace;

class ChatConversation extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'project_id', 'type', 'name', 'created_by',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_participants', 'conversation_id', 'user_id')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function latestMessage(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id')->latest()->limit(1);
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->name) {
            return $this->name;
        }

        if ($this->type === 'direct') {
            $other = $this->participants->firstWhere('id', '!=', auth()->id());
            return $other ? $other->name : 'Unknown';
        }

        if ($this->type === 'project' && $this->project) {
            return $this->project->title;
        }

        return 'Conversation';
    }

    public function unreadCountFor(int $userId): int
    {
        $participant = $this->participants->firstWhere('id', $userId);
        if (!$participant) return 0;

        $lastRead = $participant->pivot->last_read_at;

        return $this->messages()
            ->where('user_id', '!=', $userId)
            ->when($lastRead, fn($q) => $q->where('created_at', '>', $lastRead))
            ->count();
    }
}
