<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Concerns\BelongsToWorkspace;

class ZoomMeeting extends BaseModel
{
    use HasFactory, BelongsToWorkspace;

    protected $fillable = [
        'zoom_meeting_id',
        'title',
        'description',
        'start_time',
        'end_time',
        'timezone',
        'duration',
        'join_url',
        'start_url',
        'password',
        'attendees',
        'status',
        'type',
        'user_id',
        'workspace_id',
        'project_id',
        'zoom_settings',
        // Google Calendar Sync Fields
        'sync_google_calendar'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'attendees' => 'array',
        'zoom_settings' => 'array',
        // Google Calendar Sync Fields
        'sync_google_calendar' => 'boolean'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'zoom_meeting_members');
    }

    // Get project members assigned to this meeting
    public function getProjectMembers()
    {
        return $this->members;
    }

    public function scopeForWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_time', '>', now())
                    ->where('status', '!=', 'cancelled');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('start_time', today());
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'scheduled' => 'bg-blue-100 text-blue-800',
            'started' => 'bg-green-100 text-green-800',
            'ended' => 'bg-gray-100 text-gray-800',
            'cancelled' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    public function getIsLiveAttribute()
    {
        return $this->status === 'started' || 
               (now()->between($this->start_time, $this->end_time) && $this->status === 'scheduled');
    }


}