<?php

namespace App\Services;

use App\Models\TelegramSetting;

class TelegramSettingsService
{
    public static function getUserSettings($userId, $workspaceId)
    {
        $settings = TelegramSetting::where('user_id', $userId)
            ->where('workspace_id', $workspaceId)
            ->pluck('value', 'key')
            ->toArray();

        // Parse JSON values
        if (isset($settings['telegram_notifications'])) {
            $settings['telegram_notifications'] = json_decode($settings['telegram_notifications'], true);
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

        $settings['telegram_notifications'] = array_merge(
            $defaultNotifications,
            $settings['telegram_notifications'] ?? []
        );

        // Set defaults for other settings
        $settings['telegram_enabled'] = $settings['telegram_enabled'] ?? false;
        $settings['telegram_bot_token'] = $settings['telegram_bot_token'] ?? '';
        $settings['telegram_chat_id'] = $settings['telegram_chat_id'] ?? '';

        return $settings;
    }

    public static function updateUserSettings($userId, $workspaceId, $data)
    {
        foreach ($data as $key => $value) {
            if ($key === 'telegram_notifications') {
                $value = json_encode($value);
            }

            TelegramSetting::updateOrCreate(
                [
                    'user_id' => $userId,
                    'workspace_id' => $workspaceId,
                    'key' => $key,
                ],
                [
                    'value' => $value,
                ]
            );
        }
    }
}