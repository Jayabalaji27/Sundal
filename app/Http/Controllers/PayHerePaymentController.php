<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\PlanOrder;
use Illuminate\Http\Request;

class PayHerePaymentController extends Controller
{
    /**
     * Legacy browser endpoint. A PayHere success code posted by the browser proves
     * nothing, so this never activates a plan - the plan is activated by PayHere's
     * signed server notification (callback). This only reports the order's state.
     */
    public function processPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'payment_id' => 'required|string',
        ]);

        $order = PlanOrder::where('payment_id', $validated['payment_id'])
            ->where('user_id', auth()->id())
            ->where('payment_method', 'payhere')
            ->first();

        if ($order?->status === 'approved') {
            return back()->with('success', __('Payment successful and plan activated'));
        }

        return back()->with('warning', __('Your plan will be activated as soon as PayHere confirms the payment.'));
    }

    public function createPayment(Request $request)
    {
        $validated = validatePaymentRequest($request);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['payhere_merchant_id'])) {
                return response()->json(['error' => __('PayHere not configured')], 400);
            }

            $user = auth()->user();
            $orderId = 'plan_' . $plan->id . '_' . $user->id . '_' . time();

            // The plan is only assigned when PayHere's signed notification confirms
            // this order (see callback()).
            createPendingPlanOrder([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $validated['billing_cycle'],
                'payment_method' => 'payhere',
                'coupon_code' => $validated['coupon_code'] ?? null,
                'payment_id' => $orderId,
            ]);

            $paymentData = [
                'merchant_id' => $settings['payment_settings']['payhere_merchant_id'],
                'return_url' => route('payhere.success'),
                'cancel_url' => route('plans.index'),
                'notify_url' => route('payhere.callback'),
                'order_id' => $orderId,
                'items' => $plan->name,
                'currency' => 'LKR',
                'amount' => number_format($pricing['final_price'], 2, '.', ''),
                'first_name' => $user->name ?? 'Customer',
                'last_name' => 'User',
                'email' => $user->email,
                'phone' => '0771234567',
                'address' => 'No.1, Galle Road',
                'city' => 'Colombo',
                'country' => 'Sri Lanka',
            ];

            // Generate hash
            $hashString = strtoupper(
                md5(
                    $paymentData['merchant_id'] . 
                    $paymentData['order_id'] . 
                    number_format($paymentData['amount'], 2, '.', '') . 
                    $paymentData['currency'] . 
                    strtoupper(md5($settings['payment_settings']['payhere_merchant_secret']))
                )
            );
            $paymentData['hash'] = $hashString;

            $baseUrl = $settings['payment_settings']['payhere_mode'] === 'live' 
                ? 'https://www.payhere.lk' 
                : 'https://sandbox.payhere.lk';

            return response()->json([
                'success' => true,
                'payment_url' => $baseUrl . '/pay/checkout',
                'payment_data' => $paymentData,
                'order_id' => $orderId
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => __('Payment creation failed')], 500);
        }
    }

    public function success(Request $request)
    {
        $order = PlanOrder::where('payment_id', (string) $request->input('order_id'))
            ->where('payment_method', 'payhere')
            ->first();

        if ($order?->status === 'approved') {
            return redirect()->route('plans.index')->with('success', __('Payment completed successfully'));
        }

        return redirect()->route('plans.index')->with('warning', __('Your plan will be activated as soon as PayHere confirms the payment.'));
    }

    /**
     * PayHere server notification (notify_url) for plan purchases. Activates the
     * pending order only when the md5sig matches, the status is "success" (2) and
     * the amount/currency match what the order was created for.
     */
    public function callback(Request $request)
    {
        try {
            $settings = platformPaymentSettings();

            if (!$this->hasValidSignature($request, $settings) || $request->input('status_code') !== '2') {
                return response()->json(['status' => 'ignored'], 400);
            }

            $order = PlanOrder::where('payment_id', (string) $request->input('order_id'))
                ->where('payment_method', 'payhere')
                ->first();

            if (!$order || strtoupper((string) $request->input('payhere_currency')) !== 'LKR') {
                return response()->json(['status' => 'ignored'], 400);
            }

            $completed = completePlanOrderPayment($order, (float) $request->input('payhere_amount'), $request->input('payment_id'));

            return response()->json(['status' => $completed ? 'success' : 'rejected'], $completed ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Callback processing failed'], 500);
        }
    }

    /**
     * Browser-reported PayHere result for an invoice: recorded as a pending
     * payment for the invoice owner to approve (it can't be verified here).
     */
    public function processInvoicePayment(Request $request, Invoice $invoice)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'order_id' => 'required|string',
            'status_code' => 'required|string',
        ]);

        try {
            if ($request->status_code === '2') {
                $invoice->createPaymentRecord((float) $request->amount, 'payhere', $request->order_id);

                return redirect()->route('invoices.show', $invoice->id)
                    ->with('success', __('Payment received. It will be applied once it is confirmed.'));
            }

            return back()->withErrors(['error' => __('Payment failed or cancelled')]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => __('Payment processing failed. Please try again or contact support.')]);
        }
    }

    public function createInvoicePayment(Request $request)
    {
        try {
            $request->validate([
                'invoice_token' => 'required|string',
                'amount' => 'required|numeric|min:0.01'
            ]);

            $invoice = Invoice::where('payment_token', $request->invoice_token)->firstOrFail();

            $paymentSettings = PaymentSetting::where('user_id', $invoice->created_by)
                ->whereIn('key', ['payhere_merchant_id', 'payhere_merchant_secret', 'payhere_mode', 'is_payhere_enabled'])
                ->pluck('value', 'key')
                ->toArray();

            if (empty($paymentSettings['payhere_merchant_id']) || ($paymentSettings['is_payhere_enabled'] ?? '0') !== '1') {
                return response()->json(['error' => 'PayHere payment not configured'], 400);
            }

            $orderId = 'invoice_' . $invoice->id . '_' . time();

            $paymentData = [
                'merchant_id' => $paymentSettings['payhere_merchant_id'],
                'return_url' => route('payhere.invoice.success'),
                'cancel_url' => route('invoices.payment', $invoice->payment_token),
                'notify_url' => route('payhere.invoice.callback'),
                'order_id' => $orderId,
                'items' => 'Invoice Payment - ' . $invoice->invoice_number,
                'currency' => 'LKR',
                'amount' => number_format($request->amount, 2, '.', ''),
                'first_name' => 'Customer',
                'last_name' => 'User',
                'email' => 'customer@example.com',
                'phone' => '0771234567',
                'address' => 'No.1, Galle Road',
                'city' => 'Colombo',
                'country' => 'Sri Lanka',
            ];

            // Generate hash
            $hashString = strtoupper(
                md5(
                    $paymentData['merchant_id'] . 
                    $paymentData['order_id'] . 
                    number_format($paymentData['amount'], 2, '.', '') . 
                    $paymentData['currency'] . 
                    strtoupper(md5($paymentSettings['payhere_merchant_secret']))
                )
            );
            $paymentData['hash'] = $hashString;
            $paymentData['custom_1'] = $invoice->payment_token;
            $paymentData['custom_2'] = $request->amount;

            $baseUrl = ($paymentSettings['payhere_mode'] ?? 'sandbox') === 'live' 
                ? 'https://www.payhere.lk' 
                : 'https://sandbox.payhere.lk';

            return response()->json([
                'success' => true,
                'payment_data' => $paymentData,
                'action_url' => $baseUrl . '/pay/checkout'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function invoiceSuccess(Request $request)
    {
        try {
            $orderId = (string) $request->input('order_id');
            $invoiceToken = $request->input('custom_1');
            $invoice = $invoiceToken ? Invoice::where('payment_token', $invoiceToken)->first() : null;

            if ($invoice) {
                // The signed notification (invoiceCallback) confirms the payment;
                // a browser redirect alone only leaves it pending.
                if ($orderId && $request->input('status_code') === '2') {
                    $invoice->createPaymentRecord((float) $request->input('custom_2'), 'payhere', $orderId);
                }

                return redirect()->route('invoices.show', $invoice->id)
                    ->with('success', __('Payment received. It will be applied once it is confirmed.'));
            }

            return redirect()->route('invoices.index')->with('error', __('Payment verification failed'));
        } catch (\Exception $e) {
            return redirect()->route('invoices.index')->with('error', __('Payment processing failed'));
        }
    }

    /**
     * PayHere server notification for invoice payments: signature-checked against
     * the invoice owner's merchant secret, then recorded as a completed payment.
     */
    public function invoiceCallback(Request $request)
    {
        try {
            $invoice = Invoice::where('payment_token', (string) $request->input('custom_1'))->first();
            if (!$invoice) {
                return response('IGNORED', 400);
            }

            $settings = PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            if (!$this->hasValidSignature($request, $settings) || $request->input('status_code') !== '2') {
                return response('IGNORED', 400);
            }

            $orderId = (string) $request->input('order_id');
            $amount = (float) $request->input('payhere_amount');

            $existing = Payment::where('invoice_id', $invoice->id)->where('transaction_id', $orderId)->first();
            if ($existing) {
                // A pending record from the browser redirect: confirm it.
                if ($existing->status !== Payment::STATUS_COMPLETED && abs((float) $existing->amount - $amount) < 0.01) {
                    $existing->update(['status' => Payment::STATUS_COMPLETED]);
                }
            } else {
                $invoice->createPaymentRecord($amount, 'payhere', $orderId, verified: true);
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
            
            $invoice = Invoice::where('payment_token', $token)->firstOrFail();
            
            $paymentSettings = PaymentSetting::where('user_id', $invoice->created_by)
                ->whereIn('key', ['payhere_merchant_id', 'payhere_merchant_secret', 'payhere_mode', 'is_payhere_enabled'])
                ->pluck('value', 'key')
                ->toArray();

            if (($paymentSettings['is_payhere_enabled'] ?? '0') !== '1') {
                return response()->json(['error' => 'PayHere payment method is not enabled'], 400);
            }

            if (empty($paymentSettings['payhere_merchant_id']) || empty($paymentSettings['payhere_merchant_secret'])) {
                return response()->json(['error' => 'PayHere credentials not configured'], 400);
            }

            $orderId = 'invoice_' . $invoice->id . '_' . $request->amount . '_' . time();

            $paymentData = [
                'merchant_id' => $paymentSettings['payhere_merchant_id'],
                'return_url' => route('payhere.invoice.success.link', $token) . '?order_id=' . $orderId . '&amount=' . $request->amount,
                'cancel_url' => route('invoices.payment', $token),
                'notify_url' => route('payhere.invoice.callback.link'),
                'order_id' => $orderId,
                'items' => 'Invoice #' . $invoice->invoice_number,
                'currency' => 'LKR',
                'amount' => number_format($request->amount, 2, '.', ''),
                'first_name' => $invoice->client->first_name ?? 'Customer',
                'last_name' => $invoice->client->last_name ?? 'User',
                'email' => $invoice->client->email ?? 'customer@example.com',
                'phone' => '0771234567',
                'address' => 'No.1, Galle Road',
                'city' => 'Colombo',
                'country' => 'Sri Lanka',
            ];

            // Generate hash
            $hashString = strtoupper(
                md5(
                    $paymentData['merchant_id'] . 
                    $paymentData['order_id'] . 
                    number_format($paymentData['amount'], 2, '.', '') . 
                    $paymentData['currency'] . 
                    strtoupper(md5($paymentSettings['payhere_merchant_secret']))
                )
            );
            $paymentData['hash'] = $hashString;
            $paymentData['custom_1'] = $token;
            $paymentData['custom_2'] = $request->amount;

            $baseUrl = ($paymentSettings['payhere_mode'] ?? 'sandbox') === 'live' 
                ? 'https://www.payhere.lk' 
                : 'https://sandbox.payhere.lk';

            return response()->json([
                'success' => true,
                'payment_data' => $paymentData,
                'action_url' => $baseUrl . '/pay/checkout'
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function invoiceSuccessFromLink(Request $request, $token)
    {
        try {
            $orderId = $request->input('order_id');
            $amount = $request->input('amount');
            $statusCode = $request->input('status_code');
            $invoice = Invoice::where('payment_token', $token)->firstOrFail();

            if ($orderId && $amount && $statusCode === '2') {
                // Unverified browser redirect: pending until PayHere's signed
                // notification or the invoice owner confirms it.
                $invoice->createPaymentRecord((float) $amount, 'payhere', $orderId);

                return redirect()->route('invoices.payment', $token)
                    ->with('success', __('Payment received. It will be applied once it is confirmed.'));
            }

            return redirect()->route('invoices.payment', $token)
                ->with('error', 'Payment verification failed');
        } catch (\Exception $e) {
            return redirect()->route('invoices.payment', $token)
                ->with('error', 'Payment processing failed');
        }
    }


    /**
     * PayHere notify signature:
     * md5sig = UPPER(md5(merchant_id . order_id . payhere_amount . payhere_currency . status_code . UPPER(md5(merchant_secret))))
     */
    private function hasValidSignature(Request $request, array $settings): bool
    {
        $merchantId = $settings['payhere_merchant_id'] ?? null;
        $secret = $settings['payhere_merchant_secret'] ?? null;
        $received = (string) $request->input('md5sig');

        if (!$merchantId || !$secret || $received === '' || (string) $request->input('merchant_id') !== (string) $merchantId) {
            return false;
        }

        $expected = strtoupper(md5(
            $merchantId
            . $request->input('order_id')
            . $request->input('payhere_amount')
            . $request->input('payhere_currency')
            . $request->input('status_code')
            . strtoupper(md5($secret))
        ));

        return hash_equals($expected, strtoupper($received));
    }
}
