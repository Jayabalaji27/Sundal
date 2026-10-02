<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramSetting extends Model
{
    protected $table = 'settings';

    protected $fillable = [
        'user_id',
        'workspace_id',
        'key',
        'value',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public static function getUserSettings($userId, $workspaceId)
    {
        $settings = self::where('user_id', $userId)
            ->where('workspace_id', $workspaceId)
            ->pluck('value', 'key')
            ->toArray();

        // Default values
        $defaults = [
            'telegram_enabled' => false,
            'telegram_bot_token' => null,
            'telegram_chat_id' => null,
            'telegram_notifications' => json_encode([
                'new_project' => false,
                'new_task' => false,
                'task_stage_updated' => false,
                'new_milestone' => false,
                'milestone_status_updated' => false,
                'new_invoice' => false,
                'new_task_comment' => false,
                'invoice_status_updated' => false,
                'expense_approval' => false,
                'new_budget' => false,
            ]),
        ];

        return array_merge($defaults, $settings);
    }
}