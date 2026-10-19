<?php

namespace App\Services;

use App\Models\NotificationTemplate;
use App\Models\Setting;
use App\Models\User;
use Exception;

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

        return $telegramService->dispatchCurl($botToken, $chatId, $message, 'message.send', ['template' => $templateName, 'workspace_id' => $workspaceId]);
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
            return $this->dispatchCurl($botToken, $chatId, $message, 'message.send', ['template' => $templateName]);
        } catch (Exception $e) {
            IntegrationLog::failed('telegram', 'message.send', $e, null, ['template' => $templateName]);
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

    private function dispatchCurl(string $botToken, string $chatId, string $message, string $action, array $context = []): bool
    {
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        $payload = [
            'chat_id'                  => $chatId,
            'text'                     => $message,
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => true,
        ];

        return $this->post($url, $payload, $action, $context);
    }

    /** Sends to the Bot API and logs the outcome (the bot token in the URL is masked). */
    private function post(string $url, array $payload, string $action, array $context = []): bool
    {
        $started = hrtime(true);

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

        $ok = $httpCode === 200 && empty($curlError);
        // Telegram says why in "description", e.g. "Bad Request: chat not found".
        $ok
            ? IntegrationLog::ok('telegram', $action, $started, array_filter($context))
            : IntegrationLog::failed('telegram', $action, $curlError ?: "HTTP {$httpCode}: " . (json_decode((string) $response, true)['description'] ?? mb_substr((string) $response, 0, 200)), $started, array_filter($context) + ['status' => $httpCode ?: null]);

        return $ok;
    }

    private function replaceVariables(string $content, array $variables): string
    {
        return str_replace(array_keys($variables), array_values($variables), $content);
    }

    public function sendTestMessage(string $botToken, string $chatId): bool
    {
        // Double quotes, so the line breaks are real ones, not "\n" printed in the chat.
        $message = '🤖 <b>Test Message from ' . e(config('app.name')) . '</b>'
            . "\n\nThis is a test message to verify your Telegram integration is working correctly."
            . "\n\nIf you can see this message, your bot configuration is successful! 🎉";

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        return $this->post($url, [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ], 'message.test');
    }

    public function getBotInfo($botToken)
    {
        $started = hrtime(true);
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
            IntegrationLog::ok('telegram', 'bot.info', $started);

            return json_decode($response, true);
        }
        IntegrationLog::failed('telegram', 'bot.info', $curlError ?: "HTTP {$httpCode}: " . (json_decode((string) $response, true)['description'] ?? ''), $started, ['status' => $httpCode ?: null]);

        return null;
    }
}