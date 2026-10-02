<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Concerns\BelongsToWorkspace;

class Todo extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'created_by',
        'title',
        'description',
        'priority',
        'status',
        'due_date',
        'completed_at'
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    protected $appends = [];

    /**
     * Get the comments for the todo
     */
    public function comments()
    {
        return $this->hasMany(TodoComment::class)->with('user')->latest();
    }

    /**
     * Get the attachments for the todo
     */
    public function attachments()
    {
        return $this->hasMany(TodoAttachment::class)->latest();
    }

    /**
     * Get the workspace that owns the todo
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the user who created the todo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the members this todo is shared with
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'todo_members', 'todo_id', 'user_id')
                    ->withTimestamps();
    }

    /**
     * Boot method to handle status changes
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($todo) {
            foreach ($todo->attachments as $attachment) {
                delete_file($attachment->file);
            }
        });

        static::updating(function ($todo) {
            // Auto-update to overdue if due date has passed (not today) and status is pending or in_progress
            if ($todo->due_date && 
                $todo->due_date->isPast() && 
                !$todo->due_date->isToday() &&
                in_array($todo->status, ['pending', 'in_progress']) &&
                $todo->status !== 'completed') {
                $todo->status = 'overdue';
            }
            
            // Set completed_at when status changes to completed
            if ($todo->isDirty('status')) {
                if ($todo->status === 'completed' && $todo->getOriginal('status') !== 'completed') {
                    $todo->completed_at = now();
                } elseif ($todo->status !== 'completed' && $todo->getOriginal('status') === 'completed') {
                    $todo->completed_at = null;
                }
            }
        });
        
        static::retrieved(function ($todo) {
            // Auto-update to overdue when todo is retrieved from database
            if ($todo->due_date && 
                $todo->due_date->isPast() && 
                !$todo->due_date->isToday() &&
                in_array($todo->status, ['pending', 'in_progress']) &&
                $todo->status !== 'completed') {
                $todo->status = 'overdue';
                $todo->saveQuietly(); // Save without triggering events
            }
        });
    }
}
