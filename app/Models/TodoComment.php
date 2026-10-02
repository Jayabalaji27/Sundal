<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TodoComment extends Model
{
    protected $fillable = [
        'todo_id',
        'user_id',
        'comment',
    ];

    protected $appends = ['can_update', 'can_delete'];

    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getCanUpdateAttribute(): bool
    {
        return auth()->check() && auth()->id() === $this->user_id;
    }

    public function getCanDeleteAttribute(): bool
    {
        return auth()->check() && auth()->id() === $this->user_id;
    }
}
