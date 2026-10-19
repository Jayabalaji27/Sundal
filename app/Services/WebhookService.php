<?php

namespace App\Services;

use App\Models\Webhook;

class WebhookService
{
    public function triggerWebhooks(string $module, array $data, int $userId): void
    {
        $webhook = $this->webhookSetting($module, $userId);

        if ($webhook) {
            $parameter = json_encode($data);
            $this->webhookCall($webhook['url'], $parameter, $webhook['method'], ['module' => $module, 'webhook_id' => $webhook['id']]);
        }
    }

    private function webhookSetting($module, $id)
    {
        $webhook = Webhook::where('module', $module)->where('user_id', $id)->first();

        if (!empty($webhook)) {
            return [
                'id' => $webhook->id,
                'method' => $webhook->method,
                'url' => $webhook->url,
            ];
        }
        return false;
    }

    /**
     * Sends the webhook and logs the outcome. Only the receiving host is
     * logged: a webhook URL's path often is its secret (Zapier, Make, Slack).
     */
    private function webhookCall($url, $parameter, $method = 'POST', array $context = []): bool
    {
        if (empty($url) || empty($parameter)) {
            return false;
        }

        $started = hrtime(true);
        $context += ['host' => IntegrationLog::host($url), 'method' => strtoupper($method)];

        try {
            $curlHandle = curl_init($url);
            curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $parameter);
            curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            curl_setopt($curlHandle, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($parameter)
            ]);
            curl_setopt($curlHandle, CURLOPT_TIMEOUT, 30);
            curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 10);

            $curlResponse = curl_exec($curlHandle);
            $httpCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curlHandle);
            curl_close($curlHandle);

            if ($curlError) {
                IntegrationLog::failed('webhook', 'send', $curlError, $started, $context);
                return false;
            }

            // Any 2xx is accepted: many receivers answer 201, 202 or 204.
            if ($httpCode >= 200 && $httpCode < 300) {
                IntegrationLog::ok('webhook', 'send', $started, $context + ['status' => $httpCode]);
                return true;
            }

            IntegrationLog::failed('webhook', 'send', "HTTP {$httpCode}: " . mb_substr((string) $curlResponse, 0, 200), $started, $context + ['status' => $httpCode]);
            return false;
        } catch (\Throwable $th) {
            IntegrationLog::failed('webhook', 'send', $th, $started, $context);
            return false;
        }
    }
}
