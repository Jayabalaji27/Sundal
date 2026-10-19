<?php

namespace App\Services;

use App\Models\UserNotificationTemplate;
use App\Models\Setting;

class SlackService
{
    public static function send($templateName, $data = [], $userId = null, $workspaceId = null)
    {
        $userId = $userId ?: auth()->id();

        if (!$userId) {
            return false;
        }

        $webhookUrl =  Setting::where('key', 'slack_webhook_url')
            ->where('workspace_id', $workspaceId)
            ->first()?->value;

        // $webhookUrl = getSetting('slack_webhook_url', '', $userId);
        if (!$webhookUrl) {
            return false;
        }

        $user = \App\Models\User::find($userId);
        $language = $user?->lang ?: 'en';

        $slackService = new self();
        return $slackService->sendTemplateMessageWithLanguage($templateName, $data, $webhookUrl, $language, $workspaceId);
    }

    public function sendTemplateMessageWithLanguage($templateName, $variables, $webhookUrl, $language = 'en', $workspaceId = null)
    {
        if (!$webhookUrl) {
            return false;
        }

        // Get template content (you can extend this to use actual templates)
        $message = $this->getTemplateMessage($templateName, $variables, $language);

        $payload = [
            'text' => $message,
            'username' => config('app.name'),
            'icon_emoji' => ':bell:'
        ];

        return $this->sendCurlRequest($webhookUrl, $payload, 'message.send', array_filter(['template' => $templateName, 'workspace_id' => $workspaceId]));
    }

    private function getTemplateMessage($templateName, $variables, $language)
    {
        // Get template from database with type check
        $template = \App\Models\NotificationTemplate::where('name', $templateName)
            ->where('type', 'slack')
            ->first();

        if (!$template) {
            return "*{$templateName} Notification*\n\nTemplate not found.";
        }

        // Get template content for the language
        $templateLang = $template->notificationTemplateLangs()
            ->where('lang', $language)
            ->first();

        if (!$templateLang) {
            // Fallback to English if language not found
            $templateLang = $template->notificationTemplateLangs()
                ->where('lang', 'en')
                ->first();
        }

        if (!$templateLang) {
            return "*{$templateName} Notification*\n\nTemplate content not found.";
        }

        // Replace variables in template content
        $message = $this->replaceVariables($templateLang->content, $variables);

        return $message;
    }

    private function replaceVariables(string $content, array $variables): string
    {
        return str_replace(array_keys($variables), array_values($variables), $content);
    }

    /** Posts to the incoming webhook and logs the outcome (never the webhook URL: it is the secret). */
    private function sendCurlRequest($webhookUrl, $payload, string $action, array $context = [])
    {
        $started = hrtime(true);
        $jsonPayload = json_encode($payload);
        $ch = curl_init($webhookUrl);
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

        $ok = $httpCode === 200 && empty($curlError);
        $ok
            ? IntegrationLog::ok('slack', $action, $started, $context)
            : IntegrationLog::failed('slack', $action, $curlError ?: "HTTP {$httpCode}: " . mb_substr((string) $response, 0, 200), $started, $context + ['status' => $httpCode ?: null]);

        return $ok;
    }

    private static function formatMessage($templateName, $data)
    {
        $title = $data['title'] ?? ucfirst(str_replace('_', ' ', $templateName));
        $message = $data['message'] ?? 'New notification from ' . config('app.name');

        $formatted = "*{$title}*\n\n{$message}";

        if (isset($data['url'])) {
            $formatted .= "\n\n<{$data['url']}|View Details>";
        }

        return $formatted;
    }

    public function sendTestMessage($webhookUrl)
    {
        $payload = [
            'text' => '*Test Message from ' . config('app.name') . '*

This is a test message to verify your Slack integration is working correctly.

If you can see this message, your webhook configuration is successful! 🎉',
            'username' => config('app.name'),
            'icon_emoji' => ':white_check_mark:'
        ];

        return $this->sendCurlRequest($webhookUrl, $payload, 'message.test');
    }
}