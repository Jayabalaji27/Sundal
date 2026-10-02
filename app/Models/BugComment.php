<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BugComment extends Model
{
    protected $fillable = [
        'bug_id', 'user_id', 'comment', 'mentions'
    ];

    protected $casts = [
        'mentions' => 'array'
    ];

    public function bug(): BelongsTo
    {
        return $this->belongsTo(Bug::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForBug($query, $bugId)
    {
        return $query->where('bug_id', $bugId);
    }

    public function canBeUpdatedBy(User $user): bool
    {
        $workspace = $this->bug->project->workspace;
        $userRole = $workspace->getMemberRole($user);
        
        // Only comment creator, manager, or workspace owner can edit
        return $this->user_id === $user->id || 
               $workspace->isOwner($user) || 
               $userRole === 'manager';
    }

    public function canBeDeletedBy(User $user): bool
    {
        $workspace = $this->bug->project->workspace;
        $userRole = $workspace->getMemberRole($user);
        
        // Only comment creator, manager, or workspace owner can delete
        return $this->user_id === $user->id || 
               $workspace->isOwner($user) || 
               $userRole === 'manager';
    }
}