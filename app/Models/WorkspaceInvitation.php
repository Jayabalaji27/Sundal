<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WorkspaceInvitation extends Model
{
    protected $fillable = [
        'workspace_id', 'email', 'token', 'role', 
        'invited_by', 'expires_at', 'accepted_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($invitation) {
            $invitation->token = Str::random(64);
            $invitation->expires_at = now()->addDays(7);
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    public function isAccepted(): bool
    {
        return !is_null($this->accepted_at);
    }
}