<?php

namespace App\Http\Controllers;

use App\Models\SlackSetting;
use App\Services\SlackSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SlackSettingsController extends Controller
{
    public function getSettings(Request $request)
    {
        $user = auth()->user();
        return response()->json([
            'slack_enabled'  => getSetting('slack_enabled', false, $user->id),
            'slack_webhook_url' => getSetting('slack_webhook_url', '', $user->id),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'slack_enabled' => 'boolean',
            'slack_webhook_url' => 'nullable|url',
            'slack_notifications' => 'array',
        ]);

        $userId = auth()->id();
        $workspaceId = auth()->user()->current_workspace_id;

        SlackSetting::updateOrCreateSetting($userId, 'slack_enabled', $request->slack_enabled, $workspaceId);
        SlackSetting::updateOrCreateSetting($userId, 'slack_webhook_url', $request->slack_webhook_url, $workspaceId);
        SlackSetting::updateOrCreateSetting($userId, 'slack_notifications', $request->slack_notifications, $workspaceId);

        return back();
    }

    public function testWebhook(Request $request)
    {
        $request->validate([
            'webhook_url' => 'required|url',
        ]);

        $message = [
            'text' => 'Test message from Taskly SaaS',
            'username' => 'Taskly Bot',
            'icon_emoji' => ':robot_face:',
        ];

        try {
            $response = Http::post($request->webhook_url, $message);

            if ($response->successful()) {
                return back();
            }

            return back()->withErrors(['webhook' => 'Failed to send test message']);
        } catch (\Exception $e) {
            return back()->withErrors(['webhook' => 'Error: ' . $e->getMessage()]);
        }
    }
}