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

    /**
     * The stage a bug word means, now that bugs move through task stages:
     * "Resolved", "Closed" or "Fixed" is the done stage, "Testing" the review
     * stage, "Open" the in-progress one, and "New" the first stage.
     */
    public static function forBugStatus(int $workspaceId, string $status): ?self
    {
        // The workspace is passed in (the AI resolver, seeders), so the signed-in
        // user's current workspace must not narrow it further.
        $stages = static::withoutGlobalScope('workspace')->forWorkspace($workspaceId)->ordered()->get();
        if ($stages->isEmpty()) {
            return null;
        }

        $word = strtolower(trim($status));
        $named = fn (array $words) => $stages->first(fn (self $s) => collect($words)->contains(fn ($w) => str_contains(strtolower($s->name), $w)));
        $first = $stages->firstWhere('is_default', true) ?? $stages->first();

        return match (true) {
            (bool) preg_match('/resolv|clos|done|fixed|complete/', $word) => $stages->firstWhere('is_completed', true) ?? $stages->last(),
            (bool) preg_match('/test|review|qa|verif/', $word) => $named(['review', 'test', 'qa']) ?? $named(['progress']) ?? $first,
            (bool) preg_match('/progress|doing|open|reopen/', $word) => $named(['progress']) ?? $first,
            (bool) preg_match('/new|todo|to do|backlog/', $word) => $first,
            default => null,
        };
    }
}