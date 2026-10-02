<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;

class MidtransPaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'transaction_status' => 'required|string',
            'order_id' => 'required|string',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['midtrans_secret_key'])) {
                return back()->withErrors(['error' => __('Midtrans not configured')]);
            }

            if (in_array($validated['transaction_status'], ['capture', 'settlement'])) {
                processPaymentSuccess([
                    'user_id' => auth()->id(),
                    'plan_id' => $plan->id,
                    'billing_cycle' => $validated['billing_cycle'],
                    'payment_method' => 'midtrans',
                    'coupon_code' => $validated['coupon_code'] ?? null,
                    'payment_id' => $validated['order_id'],
                ]);

                return back()->with('success', __('Payment successful and plan activated'));
            }

            return back()->withErrors(['error' => __('Payment failed or cancelled')]);

        } catch (\Exception $e) {
            return handlePaymentError($e, 'midtrans');
        }
    }

    public function createPayment(Request $request)
    {
        $validated = validatePaymentRequest($request);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['midtrans_secret_key'])) {
                return response()->json(['error' => __('Midtrans not configured')], 400);
            }

            $user = auth()->user();
            $orderId = 'plan_' . $plan->id . '_' . $user->id . '_' . time();

            // Convert to IDR (whole numbers only, no cents)
            $amount = intval($pricing['final_price']);
            
            $paymentData = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => $amount
                ],
                'credit_card' => [
                    'secure' => true
                ],
                'customer_details' => [
                    'first_name' => $user->name ?? 'Customer',
                    'email' => $user->email,
                ],
                'item_details' => [
                    [
                        'id' => $plan->id,
                        'price' => $amount,
                        'quantity' => 1,
                        'name' => $plan->name
                    ]
                ]
            ];

            $snapToken = $this->createSnapToken($paymentData, $settings['payment_settings']);

            if ($snapToken) {
                $baseUrl = $settings['payment_settings']['midtrans_mode'] === 'live' 
                    ? 'https://app.midtrans.com' 
                    : 'https://app.sandbox.midtrans.com';

                return response()->json([
                    'success' => true,
                    'snap_token' => $snapToken,
                    'payment_url' => $baseUrl . '/snap/v1/transactions/' . $snapToken,
                    'order_id' => $orderId
                ]);
            }

            throw new \Exception(__('Failed to create Midtrans snap token'));

        } catch (\Exception $e) {
            return response()->json(['error' => __('Payment creation failed')], 500);
        }
    }

    public function callback(Request $request)
    {
        try {
            $orderId = $request->input('order_id');
            $transactionStatus = $request->input('transaction_status');
            
            if ($orderId && in_array($transactionStatus, ['capture', 'settlement'])) {
                $parts = explode('_', $orderId);
                
                if (count($parts) >= 3) {
                    $planId = $parts[1];
                    $userId = $parts[2];
                    
                    $plan = Plan::find($planId);
                    $user = \App\Models\User::find($userId);
                    
                    if ($plan && $user) {
                        processPaymentSuccess([
                            'user_id' => $user->id,
                            'plan_id' => $plan->id,
                            'billing_cycle' => 'monthly',
                            'payment_method' => 'midtrans',
                            'payment_id' => $request->input('transaction_id'),
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
        try {
            $request->validate([
                'invoice_token' => 'required|string',
                'amount' => 'required|numeric|min:0.01'
            ]);

            $invoice = \App\Models\Invoice::where('payment_token', $request->invoice_token)->firstOrFail();
            $settings = \App\Models\PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();

            if (!isset($settings['midtrans_secret_key']) || !isset($settings['is_midtrans_enabled']) || $settings['is_midtrans_enabled'] !== '1') {
                return response()->json(['error' => __('Midtrans payment not configured')], 400);
            }

            $orderId = 'invoice_' . $invoice->id . '_' . time();
            $amount = intval($request->amount);

            $paymentData = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => $amount
                ],
                'credit_card' => ['secure' => true],
                'customer_details' => [
                    'first_name' => $invoice->client->name ?? 'Customer',
                    'email' => $invoice->client->email ?? 'customer@example.com',
                ],
                'item_details' => [[
                    'id' => $invoice->id,
                    'price' => $amount,
                    'quantity' => 1,
                    'name' => 'Invoice Payment - ' . $invoice->invoice_number
                ]],
                'callbacks' => [
                    'finish' => route('midtrans.invoice.success') . '?order_id=' . $orderId . '&invoice_token=' . $request->invoice_token . '&amount=' . $request->amount
                ]
            ];

            $snapToken = $this->createSnapToken($paymentData, $settings);

            if ($snapToken) {
                return response()->json([
                    'success' => true,
                    'snap_token' => $snapToken,
                    'order_id' => $orderId
                ]);
            }

            return response()->json(['error' => __('Failed to create payment')], 500);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function processInvoicePayment(Request $request, $invoice)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01'
            ]);

            $settings = \App\Models\PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            if (!isset($settings['is_midtrans_enabled']) || $settings['is_midtrans_enabled'] !== '1') {
                return back()->withErrors(['error' => __('Midtrans not enabled')]);
            }

            $orderId = 'invoice_' . $invoice->id . '_' . time();
            $amount = intval($request->amount);

            $paymentData = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => $amount
                ],
                'credit_card' => ['secure' => true],
                'customer_details' => [
                    'first_name' => $invoice->client->name ?? 'Customer',
                    'email' => $invoice->client->email ?? 'customer@example.com',
                ],
                'item_details' => [[
                    'id' => $invoice->id,
                    'price' => $amount,
                    'quantity' => 1,
                    'name' => 'Invoice Payment - ' . $invoice->invoice_number
                ]]
            ];

            $invoice->createPaymentRecord(
                $request->amount,
                'midtrans',
                'midtrans_' . time()
            );
            
            return redirect()->route('invoices.show', $invoice->id)
                ->with('success', __('Midtrans payment processed successfully'));


        } catch (\Exception $e) {
            return back()->withErrors(['error' => __('Payment processing failed')]);
        }
    }

    public function invoiceSuccess(Request $request)
    {
        try {
            $orderId = $request->input('order_id');
            $invoiceToken = $request->input('invoice_token');

            if ($orderId && $invoiceToken) {
                $invoice = \App\Models\Invoice::where('payment_token', $invoiceToken)->first();

                if ($invoice) {
                    // Get the payment amount from Midtrans transaction
                    $transactionAmount = $this->getTransactionAmount($orderId, $invoice->created_by);
                    // If we can't get it from Midtrans, use URL parameter or remaining amount
                    if (!$transactionAmount) {
                        $amount = $request->input('amount') ? (float)$request->input('amount') : $invoice->remaining_amount;
                    } else {
                        $amount = $transactionAmount;
                    }
                    
                    $invoice->createPaymentRecord($amount, 'midtrans', $orderId);

                    return redirect()->route('invoices.show', $invoice->id)
                        ->with('success', __('Payment completed successfully!'));
                }
            }

            return redirect()->back()->withErrors(['error' => __('Payment verification failed')]);

        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => __('Payment processing failed')]);
        }
    }

    public function invoiceCallback(Request $request)
    {
        try {
            $orderId = $request->input('order_id');
            $transactionStatus = $request->input('transaction_status');

            if ($orderId && in_array($transactionStatus, ['capture', 'settlement'])) {
                $parts = explode('_', $orderId);
                if (count($parts) >= 3) {
                    $invoiceId = $parts[1];
                    $invoice = \App\Models\Invoice::find($invoiceId);

                    if ($invoice) {
                        $amount = $request->input('gross_amount') ? (float)$request->input('gross_amount') : $invoice->remaining_amount;
                        $invoice->createPaymentRecord($amount, 'midtrans', $request->input('transaction_id') ?? $orderId);
                    }
                }
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            return response()->json(['error' => __('Callback processing failed')], 500);
        }
    }

    private function getTransactionAmount($orderId, $userId)
    {
        try {
            $settings = \App\Models\PaymentSetting::where('user_id', $userId)->pluck('value', 'key')->toArray();
            
            if (!isset($settings['midtrans_secret_key'])) {
                return null;
            }
            
            $baseUrl = ($settings['midtrans_mode'] ?? 'sandbox') === 'live' 
                ? 'https://api.midtrans.com' 
                : 'https://api.sandbox.midtrans.com';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v2/' . $orderId . '/status');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Basic ' . base64_encode($settings['midtrans_secret_key'] . ':'),
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $result = json_decode($response, true);
                if (isset($result['gross_amount'])) {
                    return (float)$result['gross_amount'];
                }
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function createSnapToken($paymentData, $settings)
    {
        try {
            $baseUrl = ($settings['midtrans_mode'] ?? 'sandbox') === 'live' 
                ? 'https://app.midtrans.com' 
                : 'https://app.sandbox.midtrans.com';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $baseUrl . '/snap/v1/transactions');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Basic ' . base64_encode($settings['midtrans_secret_key'] . ':'),
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new \Exception(__('cURL Error: ') . $curlError);
            }

            if ($httpCode !== 201) {
                throw new \Exception(__('HTTP Error: ') . $httpCode . ' - ' . $response);
            }

            $result = json_decode($response, true);
            
            if (!isset($result['token'])) {
                throw new \Exception(__('No token in response: ') . $response);
            }
            
            return $result['token'];

        } catch (\Exception $e) {
            \Log::error('Midtrans createSnapToken error: ' . $e->getMessage());
            return false;
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
                ->whereIn('key', ['midtrans_secret_key', 'midtrans_mode', 'is_midtrans_enabled'])
                ->pluck('value', 'key')
                ->toArray();

            if (($paymentSettings['is_midtrans_enabled'] ?? '0') !== '1') {
                return response()->json(['error' => 'Midtrans payment method is not enabled'], 400);
            }

            if (empty($paymentSettings['midtrans_secret_key'])) {
                return response()->json(['error' => 'Midtrans credentials not configured'], 400);
            }

            $orderId = 'invoice_' . $invoice->id . '_' . time();
            $amount = intval($request->amount);

            $paymentData = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => $amount
                ],
                'credit_card' => ['secure' => true],
                'customer_details' => [
                    'first_name' => $invoice->client->first_name ?? 'Customer',
                    'email' => $invoice->client->email ?? 'customer@example.com',
                ],
                'item_details' => [[
                    'id' => $invoice->id,
                    'price' => $amount,
                    'quantity' => 1,
                    'name' => 'Invoice Payment - ' . $invoice->invoice_number
                ]],
                'callbacks' => [
                    'finish' => route('midtrans.invoice.success.link', $token) . '?order_id=' . $orderId . '&amount=' . $request->amount
                ]
            ];

            $snapToken = $this->createSnapToken($paymentData, $paymentSettings);

            if ($snapToken) {
                return response()->json([
                    'success' => true,
                    'snap_token' => $snapToken,
                    'order_id' => $orderId
                ]);
            }

            return response()->json(['error' => 'Failed to create payment'], 500);

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
                    'midtrans',
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


}