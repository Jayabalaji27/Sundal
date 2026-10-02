<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlackSetting extends Model
{
    protected $table = 'settings';

    protected $fillable = ['user_id', 'workspace_id', 'key', 'value'];

    protected $casts = [
        'user_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function setValueAttribute($value)
    {
        $jsonKeys = ['slack_notifications'];
        
        if (in_array($this->key, $jsonKeys)) {
            $this->attributes['value'] = json_encode($value);
        } else {
            $this->attributes['value'] = is_bool($value) ? ($value ? '1' : '0') : $value;
        }
    }

    public function getValueAttribute($value)
    {
        $booleanKeys = ['slack_enabled'];
        $jsonKeys = ['slack_notifications'];
        
        if (in_array($this->key, $booleanKeys)) {
            return $value === '1' || $value === 1 || $value === true;
        }
        
        if (in_array($this->key, $jsonKeys)) {
            return json_decode($value, true) ?: [];
        }
        
        return $value;
    }

    public static function updateOrCreateSetting($userId, $key, $value, $workspaceId = null)
    {
        return self::updateOrCreate(
            ['user_id' => $userId, 'workspace_id' => $workspaceId, 'key' => $key],
            ['value' => $value]
        );
    }

    public static function getUserSettings($userId, $workspaceId = null)
    {
        if (!$userId) {
            return [];
        }
        
        $user = \App\Models\User::find($userId);
        if ($user && $user->type === 'superadmin') {
            $workspaceId = null;
        } elseif ($user && $user->type === 'company' && is_null($workspaceId)) {
            $workspaceId = $user->current_workspace_id;
        }
        
        return self::where('user_id', $userId)
            ->where('workspace_id', $workspaceId)
            ->pluck('value', 'key')->toArray();
    }
}