<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'workspace_id',
        'key',
        'value',
    ];

    /**
     * Get the user that owns the setting.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function getUserSettings($userId, $workspaceId = null)
    {
        return self::where('user_id', $userId)
            ->where('workspace_id', $workspaceId)
            ->pluck('value', 'key')->toArray();
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }
}