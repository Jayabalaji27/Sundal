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
            $status = $this->webhookCall($webhook['url'], $parameter, $webhook['method']);
        }
    }
    
    private function webhookSetting($module, $id)
    {
        $webhook = Webhook::where('module', $module)->where('user_id', $id)->first();
        
        if (!empty($webhook)) {
            $url = $webhook->url;
            $method = $webhook->method;
            $reference_url = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

            $data['method'] = $method;
            $data['reference_url'] = $reference_url;
            $data['url'] = $url;
            return $data;
        }
        return false;
    }
    
    private function webhookCall($url = null, $parameter = null, $method = 'POST', $debug = false)
    {
        if (!empty($url) && !empty($parameter)) {
            try {
                if ($debug) {
                    dd([
                        'webhook_url' => $url,
                        'method' => $method,
                        'parameter' => $parameter,
                        'decoded_parameter' => json_decode($parameter, true),
                        'curl_command' => "curl -X {$method} -H 'Content-Type: application/json' --data '{$parameter}' {$url}",
                        'debug_info' => [
                            'timestamp' => now()->toDateTimeString(),
                            'user_agent' => request()->header('User-Agent'),
                            'ip' => request()->ip()
                        ]
                    ]);
                }

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
                    \Log::error('Webhook cURL error', [
                        'url' => $url,
                        'error' => $curlError
                    ]);
                    return false;
                }
                
                if ($httpCode === 200) {
                    \Log::info('Webhook sent successfully', [
                        'url' => $url,
                        'status_code' => $httpCode
                    ]);
                    return true;
                } else {
                    \Log::error('Webhook failed', [
                        'url' => $url,
                        'status_code' => $httpCode,
                        'response' => $curlResponse
                    ]);
                    return false;
                }
            } catch (\Throwable $th) {
                \Log::error('Webhook exception', [
                    'url' => $url,
                    'error' => $th->getMessage()
                ]);
                return false;
            }
        } else {
            return false;
        }
    }
}