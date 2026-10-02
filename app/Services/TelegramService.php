<?php

namespace App\Services;

use App\Models\NotificationTemplate;
use App\Models\Setting;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public static function send($templateName, $data = [], $userId = null, $workspaceId = null)
    {
        $userId = $userId ?: auth()->id();

        if (!$userId) {
            return false;
        }

        $botToken =  Setting::where('key', 'telegram_bot_token')
            ->where('workspace_id', $workspaceId)
            ->first()?->value;
        
        $chatId =  Setting::where('key', 'telegram_chat_id')
            ->where('workspace_id', $workspaceId)
            ->first()?->value;

        if (!$botToken || !$chatId) {
            return false;
        }

        $user     = User::find($userId);
        $language = $user?->lang ?: 'en';

        $telegramService = new self();
        $message = $telegramService->getTemplateMessage($templateName, $data, $language, $userId);

        return $telegramService->dispatchCurl($botToken, $chatId, $message);
    }
    public function sendTemplateMessageToChat(string $templateName, array $variables, string $chatId, string $language = 'en', int $userId = null)
    {
        try {
            $userId = $userId ?: createdBy();

            $botToken = getSetting('telegram_bot_token', '', $userId);
            if (!$botToken) {
                return false;
            }

            $message = $this->getTemplateMessage($templateName, $variables, $language, $userId);
            return $this->dispatchCurl($botToken, $chatId, $message);
        } catch (Exception $e) {
            Log::error('Telegram message sending failed: ' . $e->getMessage());
            return false;
        }
    }

    private function getTemplateMessage(string $templateName, array $variables, string $language, int $userId = null): string
    {
        $template = NotificationTemplate::where('name', $templateName)
            ->where('type', 'telegram')
            ->first();

        if (!$template) {
            return "<b>{$templateName}</b>\n\nTemplate not found.";
        }

        $templateLang = $template->notificationTemplateLangs()
            ->where('lang', $language)
            ->first();

        if (!$templateLang) {
            $templateLang = $template->notificationTemplateLangs()
                ->where('lang', 'en')
                ->first();
        }

        if (!$templateLang) {
            return "<b>{$templateName}</b>\n\nTemplate content not found.";
        }

        return $this->replaceVariables($templateLang->content, $variables);
    }

    private function dispatchCurl(string $botToken, string $chatId, string $message): bool
    {
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        $payload = [
            'chat_id'                  => $chatId,
            'text'                     => $message,
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => true,
        ];

        $jsonPayload = json_encode($payload);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonPayload),
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        return $httpCode === 200 && empty($curlError);
    }

    private function replaceVariables(string $content, array $variables): string
    {
        return str_replace(array_keys($variables), array_values($variables), $content);
    }

    public function sendTestMessage(string $botToken, string $chatId): bool
    {
        $message = '🤖 <b>Test Message from Taskly</b>\n\nThis is a test message to verify your Telegram integration is working correctly.\n\nIf you can see this message, your bot configuration is successful! 🎉';
        
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];

        $jsonPayload = json_encode($data);
        $ch = curl_init($url);
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

        return $httpCode === 200 && empty($curlError);
    }

    public function getBotInfo($botToken)
    {
        $url = "https://api.telegram.org/bot{$botToken}/getMe";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 && empty($curlError)) {
            return json_decode($response, true);
        }
        
        return null;
    }
}