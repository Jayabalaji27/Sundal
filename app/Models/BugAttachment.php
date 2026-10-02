<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BugAttachment extends Model
{
    protected $fillable = [
        'bug_id', 'media_item_id', 'uploaded_by'
    ];

    public function bug(): BelongsTo
    {
        return $this->belongsTo(Bug::class);
    }

    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeForBug($query, $bugId)
    {
        return $query->where('bug_id', $bugId);
    }

    public function canBeUpdatedBy(User $user): bool
    {
        $workspace = $this->bug->project->workspace;
        $userRole = $workspace->getMemberRole($user);
        
        // Uploader can always update their own attachments
        if ($this->uploaded_by === $user->id) {
            return true;
        }
        
        // Workspace owner and managers can update any attachment
        return $workspace->isOwner($user) || $userRole === 'manager';
    }

    public function canBeDeletedBy(User $user): bool
    {
        $workspace = $this->bug->project->workspace;
        $userRole = $workspace->getMemberRole($user);
        
        // Uploader can always delete their own attachments
        if ($this->uploaded_by === $user->id) {
            return true;
        }
        
        // Workspace owner and managers can delete any attachment
        return $workspace->isOwner($user) || $userRole === 'manager';
    }
}