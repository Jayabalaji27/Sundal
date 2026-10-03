<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Server-side confirmation of PayPal Checkout orders.
 *
 * The PayPal JS buttons run in the payer's browser, so an order ID posted back
 * to us proves nothing on its own. Before a plan is activated or an invoice
 * payment is recorded, the order is fetched from PayPal with the merchant's
 * credentials, captured if it has only been approved, and checked for status,
 * amount and currency.
 */
class PayPalService
{
    private string $baseUrl;

    public function __construct(private string $clientId, private string $secret, string $mode = 'sandbox')
    {
        $this->baseUrl = $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /** Build from a PaymentSetting key/value array (paypal_client_id, paypal_secret_key, paypal_mode). */
    public static function fromSettings(array $settings): self
    {
        if (empty($settings['paypal_client_id']) || empty($settings['paypal_secret_key'])) {
            throw new RuntimeException(__('PayPal is not configured.'));
        }

        return new self($settings['paypal_client_id'], $settings['paypal_secret_key'], $settings['paypal_mode'] ?? 'sandbox');
    }

    /**
     * Make sure $orderId is a completed PayPal payment of exactly $expectedAmount
     * in $expectedCurrency. Captures the order first if the payer approved it
     * but nobody captured it yet.
     *
     * @return array{order_id: string, capture_id: string, amount: float, currency: string}
     * @throws RuntimeException when the payment can't be confirmed.
     */
    public function confirmOrder(string $orderId, float $expectedAmount, string $expectedCurrency): array
    {
        if (!preg_match('/^[A-Za-z0-9-]{5,64}$/', $orderId)) {
            throw new RuntimeException(__('Invalid PayPal order reference.'));
        }

        $order = $this->request('get', "/v2/checkout/orders/{$orderId}");

        if (($order['status'] ?? null) === 'APPROVED') {
            $order = $this->request('post', "/v2/checkout/orders/{$orderId}/capture", (object) []);
        }

        if (($order['status'] ?? null) !== 'COMPLETED') {
            throw new RuntimeException(__('PayPal has not completed this payment (status: :status).', ['status' => $order['status'] ?? 'unknown']));
        }

        $captures = collect($order['purchase_units'] ?? [])
            ->flatMap(fn ($unit) => $unit['payments']['captures'] ?? [])
            ->where('status', 'COMPLETED');

        if ($captures->isEmpty()) {
            throw new RuntimeException(__('PayPal returned no completed capture for this order.'));
        }

        $currency = strtoupper($captures->first()['amount']['currency_code'] ?? '');
        $amount = round($captures->sum(fn ($capture) => (float) ($capture['amount']['value'] ?? 0)), 2);

        if ($currency !== strtoupper($expectedCurrency)) {
            throw new RuntimeException(__('PayPal payment currency :got does not match :expected.', ['got' => $currency, 'expected' => strtoupper($expectedCurrency)]));
        }

        if (abs($amount - round($expectedAmount, 2)) > 0.009) {
            throw new RuntimeException(__('PayPal payment amount :got does not match the expected :expected.', ['got' => number_format($amount, 2), 'expected' => number_format($expectedAmount, 2)]));
        }

        return [
            'order_id' => $orderId,
            'capture_id' => (string) $captures->first()['id'],
            'amount' => $amount,
            'currency' => $currency,
        ];
    }

    private function request(string $method, string $path, $body = null): array
    {
        $request = Http::withToken($this->accessToken())->acceptJson()->timeout(30);
        $response = $method === 'post' ? $request->post($this->baseUrl . $path, $body) : $request->get($this->baseUrl . $path);

        if ($response->status() === 404) {
            throw new RuntimeException(__('PayPal order not found.'));
        }
        if (!$response->successful()) {
            throw new RuntimeException(__('PayPal request failed (HTTP :status).', ['status' => $response->status()]));
        }

        return $response->json() ?? [];
    }

    private function accessToken(): string
    {
        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->secret)
            ->timeout(30)
            ->post($this->baseUrl . '/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if (!$response->successful() || empty($response['access_token'])) {
            throw new RuntimeException(__('Could not authenticate with PayPal. Check the PayPal credentials in payment settings.'));
        }

        return $response['access_token'];
    }
}
