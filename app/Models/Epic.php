<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\RecordsCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A large piece of work (a feature, a module) that groups tasks and bugs of
 * one project across sprints. Its progress is how many of its items are done.
 */
class Epic extends Model
{
    use BelongsToWorkspace, RecordsCreator;

    public const STATUSES = ['open', 'done'];

    /** The colours offered in the epic form (Tailwind 500 shades). */
    public const COLORS = ['#8b5cf6', '#3b82f6', '#06b6d4', '#10b981', '#f59e0b', '#f97316', '#ef4444', '#ec4899'];

    protected $fillable = [
        'workspace_id', 'project_id', 'name', 'description', 'color', 'status',
        'owner_id', 'start_date', 'end_date', 'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected $attributes = [
        'status' => 'open',
        'color' => '#8b5cf6',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
