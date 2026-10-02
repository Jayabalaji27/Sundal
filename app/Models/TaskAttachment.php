<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAttachment extends Model
{
    protected $fillable = [
        'task_id', 'media_item_id', 'uploaded_by'
    ];
    
    protected $with = ['mediaItem'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'media_item_id');
    }
    
    protected static function booted()
    {
        static::retrieved(function ($attachment) {
            if ($attachment->mediaItem) {
                $attachment->mediaItem->makeVisible(['url', 'thumb_url']);
            }
        });
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeForTask($query, $taskId)
    {
        return $query->where('task_id', $taskId);
    }
}