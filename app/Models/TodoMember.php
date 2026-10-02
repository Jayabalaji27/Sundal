<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TodoMember extends Model
{
    protected $fillable = [
        'todo_id',
        'user_id'
    ];

    /**
     * Get the todo that this member belongs to
     */
    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }

    /**
     * Get the user who is a member of this todo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
