<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationTemplate extends Model
{
    protected $fillable = [
        'template_id',
        'user_id',
        'workspace_id',
        'is_active',
        'type',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function notificationTemplate(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public static function getUserNotificationTemplateSettings($userId, $workspaceId = null)
    {
        $query = self::where('user_id', $userId);
        
        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        }
        
        return $query->with('notificationTemplate')
            ->get()
            ->pluck('is_active', 'notificationTemplate.name')
            ->toArray();
    }

    public static function isNotificationActive($templateName, $userId, $type = 'slack', $workspaceId = null)
    {
        $template = NotificationTemplate::where('name', $templateName)
            ->where('type', $type)
            ->first();
        if (!$template) {
            return false;
        }

        $query = self::where('user_id', $userId)
            ->where('template_id', $template->id)
            ->where('type', $type)
            ->where('is_active', true);
        
        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        }

        return $query->exists();
    }

    public static function setNotificationStatus($templateName, $userId, $type, $isActive, $workspaceId = null)
    {
        $template = NotificationTemplate::where('name', $templateName)->first();
        if (!$template) {
            return false;
        }

        $data = [
            'user_id' => $userId,
            'template_id' => $template->id,
            'type' => $type
        ];
        
        if ($workspaceId) {
            $data['workspace_id'] = $workspaceId;
        }

        return self::updateOrCreate(
            $data,
            ['is_active' => $isActive]
        );
    }
}