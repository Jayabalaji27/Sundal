<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use App\Models\Invoice;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;

class AamarpayPaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'pay_status' => 'required|string',
            'mer_txnid' => 'required|string',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['aamarpay_store_id'])) {
                return back()->withErrors(['error' => __('Aamarpay not configured')]);
            }

            if ($validated['pay_status'] === 'Successful') {
                processPaymentSuccess([
                    'user_id' => auth()->id(),
                    'plan_id' => $plan->id,
                    'billing_cycle' => $validated['billing_cycle'],
                    'payment_method' => 'aamarpay',
                    'coupon_code' => $validated['coupon_code'] ?? null,
                    'payment_id' => $validated['mer_txnid'],
                ]);

                return back()->with('success', __('Payment successful and plan activated'));
            }

            return back()->withErrors(['error' => __('Payment failed or cancelled')]);

        } catch (\Exception $e) {
            return handlePaymentError($e, 'aamarpay');
        }
    }

    public function createPayment(Request $request)
    {
        $validated = validatePaymentRequest($request);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['aamarpay_store_id']) || !isset($settings['payment_settings']['aamarpay_signature'])) {
                return response()->json(['error' => __('Aamarpay not configured')], 400);
            }

            $user = auth()->user();
            $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
            $currency = $settings['payment_settings']['currency'] ?? 'BDT';
            $url = 'https://sandbox.aamarpay.com/request.php';

            // Use proper test store_id for sandbox
            $storeId = $settings['payment_settings']['aamarpay_store_id'];
            if ($storeId === 'aamarpaytest') {
                $storeId = 'aamarpaytest'; // This might need to be changed to actual test store ID
            }
            
            $fields = [
                'store_id' => $storeId,
                'amount' => $pricing['final_price'],
                'payment_type' => '',
                'currency' => $currency,
                'tran_id' => $orderID,
                'cus_name' => $user->name ?? 'Customer',
                'cus_email' => $user->email,
                'cus_add1' => '',
                'cus_add2' => '',
                'cus_city' => '',
                'cus_state' => '',
                'cus_postcode' => '',
                'cus_country' => '',
                'cus_phone' => '1234567890',
                'success_url' => route('aamarpay.success', [
                    'response' => 'success',
                    'coupon' => $validated['coupon_code'] ?? '',
                    'plan_id' => $plan->id,
                    'price' => $pricing['final_price'],
                    'order_id' => $orderID,
                    'user_id' => $user->id,
                    'billing_cycle' => $validated['billing_cycle']
                ]),
                'fail_url' => route('aamarpay.success', [
                    'response' => 'failure',
                    'coupon' => $validated['coupon_code'] ?? '',
                    'plan_id' => $plan->id,
                    'price' => $pricing['final_price'],
                    'order_id' => $orderID
                ]),
                'cancel_url' => route('aamarpay.success', ['response' => 'cancel']),
                'signature_key' => $settings['payment_settings']['aamarpay_signature'],
                'desc' => 'Plan: ' . $plan->name,
            ];

            $fields_string = http_build_query($fields);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $url_forward = str_replace('"', '', stripslashes($response));
            curl_close($ch);

            if ($url_forward) {
                return $this->redirectToMerchant($url_forward);
            }

            return response()->json(['error' => __('Payment creation failed')], 500);

        } catch (\Exception $e) {
            return response()->json(['error' => __('Payment creation failed')], 500);
        }
    }

    private function redirectToMerchant($url)
    {
        $token = csrf_token();
        $redirectUrl = 'https://sandbox.aamarpay.com/' . $url;
        
        return response(view('aamarpay-redirect', compact('redirectUrl', 'token')));
    }

    public function success(Request $request)
    {
        try {
            $response = $request->input('response');
            $planId = $request->input('plan_id');
            $userId = $request->input('user_id');
            $coupon = $request->input('coupon');
            $billingCycle = $request->input('billing_cycle', 'monthly');
            $orderId = $request->input('order_id');
            
            if ($response === 'success' && $planId && $userId) {
                $plan = Plan::find($planId);
                $user = User::find($userId);
                
                if ($plan && $user) {
                    processPaymentSuccess([
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'billing_cycle' => $billingCycle,
                        'payment_method' => 'aamarpay',
                        'coupon_code' => $coupon,
                        'payment_id' => $orderId,
                    ]);
                    
                    if (!auth()->check()) {
                        auth()->login($user);
                    }
                    
                    return redirect()->route('plans.index')->with('success', __('Payment completed successfully and plan activated'));
                }
            }
            
            return redirect()->route('plans.index')->with('error', __('Payment failed or cancelled'));
            
        } catch (\Exception $e) {
            return redirect()->route('plans.index')->with('error', __('Payment processing failed'));
        }
    }

    public function callback(Request $request)
    {
        try {
            $transactionId = $request->input('mer_txnid');
            $status = $request->input('pay_status');
            
            if ($transactionId && $status === 'Successful') {
                $parts = explode('_', $transactionId);
                
                if (count($parts) >= 3) {
                    $planId = $parts[1];
                    $userId = $parts[2];
                    
                    $plan = Plan::find($planId);
                    $user = User::find($userId);
                    
                    if ($plan && $user) {
                        processPaymentSuccess([
                            'user_id' => $user->id,
                            'plan_id' => $plan->id,
                            'billing_cycle' => 'monthly',
                            'payment_method' => 'aamarpay',
                            'payment_id' => $request->input('pg_txnid'),
                        ]);
                    }
                }
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            return response()->json(['error' => __('Callback processing failed')], 500);
        }
    }

    public function createInvoicePayment(Request $request)
    {
        
        $validated = $request->validate([
            'invoice_token' => 'required|string',
            'amount' => 'required|numeric|min:0.01'
        ]);

        try {
            $invoice = Invoice::where('payment_token', $validated['invoice_token'])->firstOrFail();
            
            $paymentSettings = PaymentSetting::where('user_id', $invoice->created_by)
                ->whereIn('key', ['aamarpay_store_id', 'aamarpay_signature', 'is_aamarpay_enabled'])
                ->pluck('value', 'key')
                ->toArray();

            if (empty($paymentSettings['aamarpay_store_id']) || $paymentSettings['is_aamarpay_enabled'] !== '1') {
                if ($request->expectsJson()) {
                    return response()->json(['error' => 'AamarPay payment not configured'], 400);
                }
                return back()->withErrors(['error' => 'AamarPay payment not configured']);
            }

            $currentUserId = auth()->id() ?? 'guest';
            $orderId = 'invoice_' . $invoice->id . '_' . $currentUserId . '_' . time();
            $amount = number_format($validated['amount'], 2, '.', '');

            $successUrl = url('aamarpay/invoice/success?' . http_build_query([
                'response' => 'success',
                'order_id' => $orderId,
                'invoice_token' => $validated['invoice_token'],
                'amount' => $amount
            ]));

            $failUrl = url('aamarpay/invoice/success?' . http_build_query([
                'response' => 'failure',
                'order_id' => $orderId,
                'invoice_token' => $validated['invoice_token']
            ]));

            $cancelUrl = url('aamarpay/invoice/success?' . http_build_query([
                'response' => 'cancel',
                'invoice_token' => $validated['invoice_token']
            ]));

            $paymentData = [
                'store_id' => $paymentSettings['aamarpay_store_id'],
                'tran_id' => $orderId,
                'success_url' => $successUrl,
                'fail_url' => $failUrl,
                'cancel_url' => $cancelUrl,
                'amount' => $amount,
                'currency' => 'BDT',
                'signature_key' => $paymentSettings['aamarpay_signature'],
                'desc' => 'Invoice Payment - ' . $invoice->invoice_number,
                'cus_name' => $invoice->customer_name ?? 'Customer',
                'cus_email' => $invoice->customer_email ?? 'customer@example.com',
                'cus_add1' => 'Address',
                'cus_city' => 'City',
                'cus_country' => 'Bangladesh',
                'cus_phone' => '01700000000'
            ];

            $fields_string = http_build_query($paymentData);
            $url = 'https://sandbox.aamarpay.com/request.php';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $url_forward = str_replace('"', '', stripslashes($response));
            curl_close($ch);

            if ($url_forward) {
                $redirectUrl = 'https://sandbox.aamarpay.com/' . $url_forward;

                // Always redirect in same tab
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => true,
                        'redirect_url' => $redirectUrl,
                        'redirect_type' => 'same_tab'
                    ]);
                }

                return redirect($redirectUrl);
            }

            if ($request->expectsJson()) {
                return response()->json(['error' => 'Payment creation failed'], 500);
            }

            return back()->withErrors(['error' => 'Payment creation failed']);

        } catch (\Exception $e) {
            \Log::error('Aamarpay createInvoicePayment error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 500);
            }

            return back()->withErrors(['error' => $e->getMessage()]);
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
                ->whereIn('key', ['aamarpay_store_id', 'aamarpay_signature', 'is_aamarpay_enabled'])
                ->pluck('value', 'key')
                ->toArray();

            if (($paymentSettings['is_aamarpay_enabled'] ?? '0') !== '1') {
                return response()->json(['error' => 'Aamarpay payment method is not enabled'], 400);
            }

            if (empty($paymentSettings['aamarpay_store_id']) || empty($paymentSettings['aamarpay_signature'])) {
                return response()->json(['error' => 'Aamarpay credentials not configured'], 400);
            }

            $orderId = 'invoice_' . $invoice->id . '_' . time();
            $amount = number_format($request->amount, 2, '.', '');

            $successUrl = url('aamarpay/invoice/success/' . $token) . '?' . http_build_query([
                'response' => 'success',
                'order_id' => $orderId,
                'amount' => $amount
            ]);

            $failUrl = url('aamarpay/invoice/success/' . $token) . '?' . http_build_query([
                'response' => 'failure',
                'order_id' => $orderId
            ]);

            $cancelUrl = url('aamarpay/invoice/success/' . $token) . '?' . http_build_query([
                'response' => 'cancel'
            ]);

            $paymentData = [
                'store_id' => $paymentSettings['aamarpay_store_id'],
                'tran_id' => $orderId,
                'success_url' => $successUrl,
                'fail_url' => $failUrl,
                'cancel_url' => $cancelUrl,
                'amount' => $amount,
                'currency' => 'BDT',
                'signature_key' => $paymentSettings['aamarpay_signature'],
                'desc' => 'Invoice Payment - ' . $invoice->invoice_number,
                'cus_name' => $invoice->client->first_name ?? 'Customer',
                'cus_email' => $invoice->client->email ?? 'customer@example.com',
                'cus_add1' => 'Address',
                'cus_city' => 'City',
                'cus_country' => 'Bangladesh',
                'cus_phone' => '01700000000'
            ];

            $fields_string = http_build_query($paymentData);
            $url = 'https://sandbox.aamarpay.com/request.php';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $url_forward = str_replace('"', '', stripslashes($response));
            curl_close($ch);

            if ($url_forward) {
                $redirectUrl = 'https://sandbox.aamarpay.com/' . $url_forward;
                return response()->json([
                    'success' => true,
                    'redirect_url' => $redirectUrl
                ]);
            }

            return response()->json(['error' => 'Payment creation failed'], 500);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function invoiceSuccess(Request $request)
    {
        try {
            $response = $request->input('response');
            $invoiceToken = $request->input('invoice_token');
            $amount = $request->input('amount');
            $orderId = $request->input('order_id');

            if ($response === 'success' && $invoiceToken && $amount) {
                $invoice = Invoice::where('payment_token', $invoiceToken)->first();

                if ($invoice) {
                    $invoice->createPaymentRecord($amount, 'aamarpay', $orderId ?: 'aamarpay_' . time());
                    
                    $parts = explode('_', $orderId);
                    $userId = null;
                    if (count($parts) >= 3) {
                        $userId = $parts[2]; 
                    }

                    if (!auth()->check() && $userId && $userId !== 'guest') {
                        $user = User::find($userId);
                        if ($user) {
                            auth()->login($user);
                        }
                    }

                    return redirect()->route('invoices.show', $invoice->id)
                        ->with('success', 'Payment completed successfully!');
                }
            }

            return redirect()->route('home')
                ->with('error', 'Payment failed or cancelled');

        } catch (\Exception $e) {
            return redirect()->route('home')
                ->with('error', 'Payment processing failed');
        }
    }

    public function invoiceSuccessFromLink(Request $request, $token)
    {
        try {
            $payStatus = $request->input('pay_status');
            $amount = $request->input('amount');
            $orderId = $request->input('mer_txnid');
            $pgTxnId = $request->input('pg_txnid');
            
            $invoice = Invoice::where('payment_token', $token)->firstOrFail();

            if ($payStatus === 'Successful' && $amount) {
                $invoice->createPaymentRecord(
                    $amount,
                    'aamarpay',
                    $pgTxnId ?: $orderId ?: 'aamarpay_' . time()
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


    public function invoiceCallback(Request $request)
    {
        try {
            $transactionId = $request->input('mer_txnid');
            $status = $request->input('pay_status');
            $amount = $request->input('amount');
            
            if ($transactionId && $status === 'Successful') {
                $invoice = Invoice::where('transaction_id', $transactionId)->first();
                
                if ($invoice) {
                    $invoice->update([
                        'status' => 'paid',
                        'paid_amount' => $amount,
                        'payment_date' => now(),
                        'payment_method' => 'aamarpay',
                        'transaction_id' => $request->input('pg_txnid')
                    ]);
                }
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            return response()->json(['error' => __('Callback processing failed')], 500);
        }
    }
}