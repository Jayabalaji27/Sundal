<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'text',
        'color',
        'type',
        'assign_to',
        'workspace',
        'created_by'
    ];

    protected $casts = [
        'workspace' => 'integer',
        'created_by' => 'integer'
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function workspaceRelation(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace');
    }

    public function getAssignedUsersAttribute()
    {
        if (empty($this->assign_to)) {
            return collect();
        }
        
        $userIds = explode(',', $this->assign_to);
        return User::whereIn('id', $userIds)->get();
    }
}