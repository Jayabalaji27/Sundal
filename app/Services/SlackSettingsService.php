<?php

namespace App\Services;

use App\Models\SlackSetting;

class SlackSettingsService
{
    public static function getSettings($userId, $workspaceId = null)
    {
        $settings = SlackSetting::getUserSettings($userId, $workspaceId);
        
        // Get slack_notifications and ensure it's an array with proper boolean values
        $notifications = $settings['slack_notifications'] ?? [];
        if (is_string($notifications)) {
            $notifications = json_decode($notifications, true) ?? [];
        }
        
        // Merge with defaults to ensure all notification types exist
        $defaultNotifications = [
            'new_project' => false,
            'new_task' => false,
            'task_stage_updated' => false,
            'new_milestone' => false,
            'milestone_status_updated' => false,
            'new_task_comment' => false,
            'new_invoice' => false,
            'invoice_status_updated' => false,
            'expense_approval' => false,
            'new_budget' => false,
        ];
        
        $notifications = array_merge($defaultNotifications, $notifications);
        
        return [
            'slack_enabled' => $settings['slack_enabled'] ?? false,
            'slack_webhook_url' => $settings['slack_webhook_url'] ?? '',
            'slack_notifications' => $notifications,
        ];
    }
}