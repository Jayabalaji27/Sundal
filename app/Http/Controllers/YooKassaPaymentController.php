<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanOrder;
use Illuminate\Http\Request;
use YooKassa\Client;

class YooKassaPaymentController extends Controller
{
    public function createPayment(Request $request)
    {
        $validated = validatePaymentRequest($request);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            $settings = getPaymentGatewaySettings();

            if (!isset($settings['payment_settings']['yookassa_shop_id'])) {
                return response()->json(['error' => 'YooKassa not configured'], 400);
            }

            $client = new Client();
            $client->setAuth((int)$settings['payment_settings']['yookassa_shop_id'], $settings['payment_settings']['yookassa_secret_key']);
            $user = auth()->user();

            // Pending until YooKassa itself reports the payment as succeeded
            // (success() / callback() re-fetch it from the API).
            $order = createPendingPlanOrder([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $validated['billing_cycle'],
                'payment_method' => 'yookassa',
                'coupon_code' => $validated['coupon_code'] ?? null,
                'payment_id' => null,
            ]);

            $payment = $client->createPayment([
                'amount' => [
                    'value' => number_format($pricing['final_price'], 2, '.', ''),
                    'currency' => 'RUB',
                ],
                'confirmation' => [
                    'type' => 'redirect',
                    'return_url' => route('yookassa.success', ['order' => $order->order_number]),
                ],
                'capture' => true,
                'description' => 'Plan: ' . $plan->name,
                'metadata' => [
                    'plan_order' => $order->order_number,
                    'plan_id' => $plan->id,
                    'user_id' => $user->id,
                    'billing_cycle' => $validated['billing_cycle'],
                ]
            ], uniqid('', true));

            $order->update(['payment_id' => $payment['id']]);

            if ($payment['confirmation']['confirmation_url'] != null) {
                return response()->json([
                    'success' => true,
                    'payment_url' => $payment['confirmation']['confirmation_url'],
                    'payment_id' => $payment['id']
                ]);
            }

            return response()->json(['error' => __('Payment creation failed')], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => __('Payment creation failed')], 500);
        }
    }

    /**
     * Return URL after YooKassa checkout. Public route: the plan order is looked
     * up by its order number and only activated if YooKassa's API says the
     * payment succeeded for the right amount.
     */
    public function success(Request $request)
    {
        try {
            $order = PlanOrder::where('order_number', (string) $request->input('order'))
                ->where('payment_method', 'yookassa')
                ->first();

            if ($order && $this->confirmPlanOrder($order)) {
                return redirect()->route('plans.index')->with('success', __('Payment successful and plan activated'));
            }

            return redirect()->route('plans.index')->with('warning', __('Your plan will be activated as soon as YooKassa confirms the payment.'));
        } catch (\Exception $e) {
            return redirect()->route('plans.index')->with('error', __('Payment processing failed'));
        }
    }

    /**
     * YooKassa webhook. The request body is only used to find our order; the
     * payment itself is re-fetched from YooKassa's API before anything happens.
     */
    public function callback(Request $request)
    {
        try {
            $paymentId = (string) $request->input('object.id');
            $order = $paymentId !== ''
                ? PlanOrder::where('payment_id', $paymentId)->where('payment_method', 'yookassa')->first()
                : null;

            if ($order) {
                $this->confirmPlanOrder($order);
            }

            // Always 200 so YooKassa doesn't keep retrying notifications we ignore.
            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            return response()->json(['error' => __('Callback processing failed')], 500);
        }
    }

    public function createInvoicePayment(Request $request)
    {
        try {
            $request->validate([
                'invoice_id' => 'required|exists:invoices,id',
                'amount' => 'required|numeric|min:0.01'
            ]);

            $invoice = \App\Models\Invoice::findOrFail($request->invoice_id);
            $settings = \App\Models\PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            
            if (!isset($settings['yookassa_shop_id']) || !isset($settings['is_yookassa_enabled']) || $settings['is_yookassa_enabled'] !== '1') {
                return response()->json(['error' => __('YooKassa not configured')], 400);
            }

            $client = new Client();
            $client->setAuth((int)$settings['yookassa_shop_id'], $settings['yookassa_secret_key']);

            $orderId = 'invoice_' . $invoice->id . '_' . time();

            $payment = $client->createPayment([
                'amount' => [
                    'value' => number_format($request->amount, 2, '.', ''),
                    'currency' => 'RUB',
                ],
                'confirmation' => [
                    'type' => 'redirect',
                    'return_url' => route('yookassa.invoice.success', [
                        'invoice_id' => $invoice->id,
                        'amount' => $request->amount
                    ]),
                ],
                'capture' => true,
                'description' => 'Invoice Payment - ' . $invoice->invoice_number,
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'order_id' => $orderId,
                    'amount' => $request->amount
                ]
            ], uniqid('', true));

            if ($payment['confirmation']['confirmation_url'] != null) {
                return response()->json([
                    'success' => true,
                    'redirect_url' => $payment['confirmation']['confirmation_url'],
                    'payment_id' => $payment['id']
                ]);
            }

            return response()->json(['error' => __('Payment creation failed')], 500);

        } catch (\Exception $e) {
            return response()->json(['error' => __('Payment creation failed')], 500);
        }
    }

    public function processInvoicePayment(Request $request)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'payment_id' => 'required|string',
            ]);
            
            $invoice = \App\Models\Invoice::where('payment_token', $request->route('token'))->firstOrFail();
            $settings = \App\Models\PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            
            if (!isset($settings['is_yookassa_enabled']) || $settings['is_yookassa_enabled'] !== '1') {
                return back()->withErrors(['error' => 'YooKassa not enabled']);
            }

            $invoice->createPaymentRecord(
                $request->amount,
                'yookassa',
                $request->payment_id
            );
            
            return redirect()->route('invoices.show', $invoice->id)
                ->with('success', 'YooKassa payment processed successfully.');
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Payment processing failed. Please try again or contact support.']);
        }
    }

    public function invoiceSuccess(Request $request)
    {
        try {
            $invoiceId = $request->input('invoice_id');
            $amount = $request->input('amount');
            
            if ($invoiceId && $amount) {
                $invoice = \App\Models\Invoice::find($invoiceId);
                
                if ($invoice) {
                    $invoice->createPaymentRecord(
                        $amount,
                        'yookassa',
                        'yookassa_' . time()
                    );
                    
                    return redirect()->route('invoices.show', $invoice->id)
                        ->with('success', 'Payment completed successfully!`');
                }
            }
            
            return redirect()->route('invoices.show', $invoiceId ?: 1)
                ->with('error', 'Payment verification failed');
            
        } catch (\Exception $e) {
            return redirect()->route('invoices.show', $request->input('invoice_id') ?: 1)
                ->with('error', 'Payment processing failed');
        }
    }

    /** YooKassa webhook for invoice payments; the payment is re-fetched from the API. */
    public function invoiceCallback(Request $request)
    {
        try {
            $paymentId = (string) $request->input('object.id');
            $invoiceId = $request->input('object.metadata.invoice_id');
            $invoice = $invoiceId ? \App\Models\Invoice::find($invoiceId) : null;

            if ($paymentId !== '' && $invoice) {
                $settings = \App\Models\PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
                $payment = $this->fetchSucceededPayment($settings, $paymentId);

                if ($payment && (int) ($payment->getMetadata()?->toArray()['invoice_id'] ?? 0) === $invoice->id) {
                    $amount = (float) $payment->getAmount()->getValue();
                    if ($amount <= (float) $invoice->remaining_amount + 0.009) {
                        $invoice->createPaymentRecord($amount, 'yookassa', $paymentId, verified: true);
                    }
                }
            }

            return response('OK', 200);
        } catch (\Exception $e) {
            return response('ERROR', 500);
        }
    }

    public function processInvoicePaymentFromLink(Request $request, $token)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01'
            ]);
            
            $invoice = \App\Models\Invoice::where('payment_token', $token)->firstOrFail();
            
            $paymentSettings = \App\Models\PaymentSetting::where('user_id', $invoice->created_by)
                ->whereIn('key', ['yookassa_shop_id', 'yookassa_secret_key', 'is_yookassa_enabled'])
                ->pluck('value', 'key')
                ->toArray();

            if (($paymentSettings['is_yookassa_enabled'] ?? '0') !== '1') {
                return response()->json(['error' => 'YooKassa payment method is not enabled'], 400);
            }

            if (empty($paymentSettings['yookassa_shop_id']) || empty($paymentSettings['yookassa_secret_key'])) {
                return response()->json(['error' => 'YooKassa credentials not configured'], 400);
            }

            $client = new Client();
            $client->setAuth((int)$paymentSettings['yookassa_shop_id'], $paymentSettings['yookassa_secret_key']);

            $orderId = 'invoice_' . $invoice->id . '_' . time();

            $payment = $client->createPayment([
                'amount' => [
                    'value' => number_format($request->amount, 2, '.', ''),
                    'currency' => 'RUB',
                ],
                'confirmation' => [
                    'type' => 'redirect',
                    'return_url' => route('yookassa.invoice.success.link', $token) . '?order_id=' . $orderId . '&amount=' . $request->amount,
                ],
                'capture' => true,
                'description' => 'Invoice Payment - ' . $invoice->invoice_number,
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'invoice_token' => $token,
                    'order_id' => $orderId,
                    'amount' => $request->amount
                ]
            ], uniqid('', true));

            if ($payment['confirmation']['confirmation_url'] != null) {
                return response()->json([
                    'success' => true,
                    'redirect_url' => $payment['confirmation']['confirmation_url'],
                    'payment_id' => $payment['id']
                ]);
            }

            return response()->json(['error' => 'Payment creation failed'], 500);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function invoiceSuccessFromLink(Request $request, $token)
    {
        try {
            $orderId = $request->input('order_id');
            $amount = $request->input('amount');
            
            $invoice = \App\Models\Invoice::where('payment_token', $token)->firstOrFail();

            if ($orderId && $amount) {
                $invoice->createPaymentRecord(
                    (float)$amount,
                    'yookassa',
                    $orderId
                );

                return redirect()->route('invoices.payment', $token)
                    ->with('success', 'Payment processed successfully.');
            }

            return redirect()->route('invoices.payment', $token)
                ->with('error', 'Payment verification failed');
        } catch (\Exception $e) {
            return redirect()->route('invoices.payment', $token)
                ->with('error', 'Payment processing failed');
        }
    }


    /** Activate a pending plan order if YooKassa reports its payment as succeeded. */
    private function confirmPlanOrder(PlanOrder $order): bool
    {
        if ($order->status === 'approved') {
            return true;
        }
        if (!$order->payment_id) {
            return false;
        }

        $payment = $this->fetchSucceededPayment(platformPaymentSettings(), $order->payment_id);
        if (!$payment || strtoupper($payment->getAmount()->getCurrency()) !== 'RUB') {
            return false;
        }

        return completePlanOrderPayment($order, (float) $payment->getAmount()->getValue());
    }

    /** The payment from YooKassa's API, or null unless it's paid and succeeded. */
    private function fetchSucceededPayment(array $settings, string $paymentId)
    {
        if (empty($settings['yookassa_shop_id']) || empty($settings['yookassa_secret_key'])) {
            return null;
        }

        $client = new Client();
        $client->setAuth((int) $settings['yookassa_shop_id'], $settings['yookassa_secret_key']);
        $payment = $client->getPaymentInfo($paymentId);

        return ($payment && $payment->getStatus() === 'succeeded' && $payment->getPaid()) ? $payment : null;
    }
}
