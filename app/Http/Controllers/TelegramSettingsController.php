<?php

namespace App\Http\Controllers;

use App\Models\TelegramSetting;
use App\Services\TelegramSettingsService;
use App\Services\TelegramService;
use Illuminate\Http\Request;

class TelegramSettingsController extends Controller
{
    public function show()
    {
        $user = auth()->user();
        return response()->json([
            'telegram_enabled'   => getSetting('telegram_enabled', false, $user->id),
            'telegram_bot_token' => getSetting('telegram_bot_token', '', $user->id),
            'telegram_chat_id'   => getSetting('telegram_chat_id', '', $user->id),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'telegram_enabled' => 'boolean',
            'telegram_bot_token' => 'nullable|string',
            'telegram_chat_id' => 'nullable|string',
            'telegram_notifications' => 'array',
            'telegram_notifications.*' => 'boolean',
        ]);

        $user = auth()->user();
        $workspaceId = $user->current_workspace_id;

        TelegramSettingsService::updateUserSettings($user->id, $workspaceId, $validated);

        return back();
    }

    public function test(Request $request)
    {
        $validated = $request->validate([
            'telegram_bot_token' => 'required|string',
            'telegram_chat_id' => 'required|string',
        ]);

        $message = "*Test Telegram Integration*\n\nThis is a test message from Taskly. Your Telegram integration is working correctly!";
        
        $payload = [
            'chat_id' => $validated['telegram_chat_id'],
            'text' => $message,
            'parse_mode' => 'Markdown'
        ];
        
        $jsonPayload = json_encode($payload);
        $ch = curl_init("https://api.telegram.org/bot{$validated['telegram_bot_token']}/sendMessage");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonPayload)
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode === 200 && empty($curlError)) {
            return back();
        } else {
            return back()->withErrors(['telegram_test' => 'Failed to send test message. Please check your bot token and chat ID.']);
        }
    }
}