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

    protected static function booted(): void
    {
        // settings() caches each user/workspace's settings for 5 minutes; without
        // this a saved toggle (e.g. Landing Page off) kept its old value until expiry.
        $forget = fn (Setting $setting) => \Illuminate\Support\Facades\Cache::forget("settings_{$setting->user_id}_{$setting->workspace_id}");
        static::saved($forget);
        static::deleted($forget);
    }
}