<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ProjectNote extends Model
{
    protected $fillable = [
        'project_id', 'title', 'content', 'is_pinned', 'created_by', 'updated_by'
    ];

    protected $casts = [
        'is_pinned' => 'boolean'
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePinned(Builder $query): Builder
    {
        return $query->where('is_pinned', true);
    }

    public function scopeUnpinned(Builder $query): Builder
    {
        return $query->where('is_pinned', false);
    }

    public function togglePin(): void
    {
        $this->update([
            'is_pinned' => !$this->is_pinned,
            'updated_by' => auth()->id()
        ]);

        $action = $this->is_pinned ? __('pinned') : __('unpinned');
        $this->project->logActivity(
            'note_' . ($this->is_pinned ? 'pinned' : 'unpinned'),
            __('Note ":title" was :action', ['title' => $this->title, 'action' => $action])
        );
    }
}