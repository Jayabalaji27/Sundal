<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToWorkspace;

class TaskStage extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'name', 'color', 'order', 'is_default', 'is_completed'
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_completed' => 'boolean',
        'order' => 'integer'
    ];

    /** Stage names treated as "done" when a stage is created without an explicit flag. */
    public const COMPLETED_STAGE_NAMES = ['done', 'completed', 'complete'];

    protected static function booted(): void
    {
        static::creating(function (TaskStage $stage) {
            if (!isset($stage->attributes['is_completed'])) {
                $stage->is_completed = in_array(strtolower(trim((string) $stage->name)), self::COMPLETED_STAGE_NAMES, true);
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public function scopeForWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }
}