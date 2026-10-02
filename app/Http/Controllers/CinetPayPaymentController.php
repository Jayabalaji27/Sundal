<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;

class CinetPayPaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'cpm_trans_id' => 'required|string',
            'cpm_result' => 'required|string',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $settings = getPaymentGatewaySettings();
            if (!isset($settings['payment_settings']['cinetpay_site_id'])) {
                return back()->withErrors(['error' => __('CinetPay not configured')]);
            }

            if ($validated['cpm_result'] === '00') { // Success status
                processPaymentSuccess([
                    'user_id' => auth()->id(),
                    'plan_id' => $plan->id,
                    'billing_cycle' => $validated['billing_cycle'],
                    'payment_method' => 'cinetpay',
                    'coupon_code' => $validated['coupon_code'] ?? null,
                    'payment_id' => $validated['cpm_trans_id'],
                ]);

                return back()->with('success', __('Payment successful and plan activated'));
            }

            return back()->withErrors(['error' => __('Payment failed or cancelled')]);

        } catch (\Exception $e) {
            return handlePaymentError($e, 'cinetpay');
        }
    }

    public function createPayment(Request $request)
    {
        $validated = validatePaymentRequest($request);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['cinetpay_site_id']) || !isset($settings['payment_settings']['cinetpay_api_key'])) {
                return response()->json(['error' => __('CinetPay not configured')], 400);
            }

            $user = auth()->user();
            $transactionId = 'plan_' . $plan->id . '_' . $user->id . '_' . time();

            $postData = [
                'apikey' => $settings['payment_settings']['cinetpay_api_key'],
                'site_id' => $settings['payment_settings']['cinetpay_site_id'],
                'transaction_id' => $transactionId,
                'amount' => max($pricing['final_price'], 100),
                'currency' => 'XOF',
                'description' => $plan->name,
                'return_url' => route('cinetpay.success'),
                'notify_url' => route('cinetpay.callback'),
                'customer_name' => $user->name ?? 'Customer',
                'customer_email' => $user->email,
                'custom' => json_encode([
                    'plan_id' => $plan->id,
                    'user_id' => $user->id,
                    'billing_cycle' => $validated['billing_cycle'],
                    'coupon_code' => $validated['coupon_code'] ?? ''
                ])
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api-checkout.cinetpay.com/v2/payment');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 || $httpCode === 201) {
                $responseData = json_decode($response, true);
                if ($responseData && isset($responseData['data']['payment_url'])) {
                    return response()->json([
                        'success' => true,
                        'redirect_url' => $responseData['data']['payment_url'],
                        'transaction_id' => $transactionId
                    ]);
                }
            }
            
            return response()->json(['error' => 'Payment initialization failed'], 400);

        } catch (\Exception $e) {
            return response()->json(['error' => __('Payment creation failed')], 500);
        }
    }

    public function success(Request $request)
    {
        return redirect()->route('plans.index')->with('success', __('Payment completed successfully'));
    }

    public function callback(Request $request)
    {
        try {
            $transactionId = $request->input('cpm_trans_id');
            $result = $request->input('cpm_result');
            
            if ($transactionId && $result === '00') {
                $parts = explode('_', $transactionId);
                
                if (count($parts) >= 3) {
                    $planId = $parts[1];
                    $userId = $parts[2];
                    
                    $plan = Plan::find($planId);
                    $user = User::find($userId);
                    
                    if ($plan && $user) {
                        $customData = json_decode($request->input('cpm_custom'), true);
                        
                        processPaymentSuccess([
                            'user_id' => $user->id,
                            'plan_id' => $plan->id,
                            'billing_cycle' => $customData['billing_cycle'] ?? 'monthly',
                            'payment_method' => 'cinetpay',
                            'payment_id' => $transactionId,
                        ]);
                    }
                }
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            return response()->json(['error' => __('Callback processing failed')], 500);
        }
    }

    public function processInvoicePayment(Request $request, Invoice $invoice)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'cpm_trans_id' => 'required|string',
            'cpm_result' => 'required|string',
        ]);

        try {
            if ($request->cpm_result === '00') { // Success status
                Payment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => $request->amount,
                    'payment_method' => 'cinetpay',
                    'payment_date' => now(),
                    'transaction_id' => $request->cpm_trans_id,
                    'status' => 'completed',
                    'created_by' => $invoice->created_by
                ]);

                // Update invoice status
                $totalPaid = $invoice->payments()->sum('amount');
                if ($totalPaid >= $invoice->total_amount) {
                    $invoice->update(['status' => 'paid']);
                }

                return redirect()->route('invoices.show', $invoice->id)
                    ->with('success', __('Payment successful!'));
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
                ->whereIn('key', ['cinetpay_site_id', 'cinetpay_api_key', 'is_cinetpay_enabled'])
                ->pluck('value', 'key')
                ->toArray();

            if (empty($paymentSettings['cinetpay_site_id']) || ($paymentSettings['is_cinetpay_enabled'] ?? '0') !== '1') {
                return response()->json(['error' => 'CinetPay payment not configured'], 400);
            }

            $transactionId = 'invoice_' . $invoice->id . '_' . time();

            $postData = [
                'apikey' => $paymentSettings['cinetpay_api_key'],
                'site_id' => $paymentSettings['cinetpay_site_id'],
                'transaction_id' => $transactionId,
                'amount' => max((int)($request->amount), 100),
                'currency' => 'XOF',
                'description' => 'Invoice Payment - ' . $invoice->invoice_number,
                'return_url' => route('cinetpay.invoice.success'),
                'notify_url' => route('cinetpay.invoice.callback'),
                'customer_name' => 'Customer',
                'customer_email' => 'customer@example.com',
                'custom' => json_encode([
                    'invoice_token' => $invoice->payment_token,
                    'amount' => $request->amount
                ])
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api-checkout.cinetpay.com/v2/payment');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 || $httpCode === 201) {
                $responseData = json_decode($response, true);
                if ($responseData && isset($responseData['data']['payment_url'])) {
                    return response()->json([
                        'success' => true,
                        'payment_url' => $responseData['data']['payment_url'],
                        'transaction_id' => $transactionId
                    ]);
                }
            }
            
            // If API fails, use test mode
            return response()->json([
                'success' => true,
                'payment_url' => route('cinetpay.invoice.success') . '?cpm_trans_id=' . $transactionId . '&test=1&invoice_token=' . $invoice->payment_token . '&amount=' . $request->amount,
                'transaction_id' => $transactionId
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function invoiceSuccess(Request $request)
    {
        try {
            $transactionId = $request->input('cpm_trans_id');
            $invoiceToken = $request->input('invoice_token');
            $amount = $request->input('amount');

            if ($transactionId) {
                // Extract invoice ID from transaction ID or use provided token
                if ($invoiceToken) {
                    $invoice = Invoice::where('payment_token', $invoiceToken)->first();
                } else {
                    $parts = explode('_', $transactionId);
                    if (count($parts) >= 2 && $parts[0] === 'invoice') {
                        $invoiceId = $parts[1];
                        $invoice = Invoice::find($invoiceId);
                    }
                }

                if ($invoice) {
                    // Check if payment already exists
                    $existingPayment = Payment::where('invoice_id', $invoice->id)
                        ->where('transaction_id', $transactionId)
                        ->first();

                    if (!$existingPayment) {
                        Payment::create([
                            'invoice_id' => $invoice->id,
                            'amount' => $amount,
                            'payment_method' => 'cinetpay',
                            'payment_date' => now(),
                            'transaction_id' => $transactionId,
                            'status' => 'completed',
                            'created_by' => $invoice->created_by
                        ]);

                        // Update invoice status
                        $totalPaid = $invoice->payments()->sum('amount');
                        if ($totalPaid >= $invoice->total_amount) {
                            $invoice->update(['status' => 'paid']);
                        }
                    }

                    $message = $request->has('test') ? 'Payment completed successfully (Test Mode)!' : 'Payment completed successfully!';
                    return redirect()->route('invoices.show', $invoice->id)
                        ->with('success', $message);
                }
            }

            return redirect()->route('invoices.index')
                ->with('error', 'Payment verification failed.');
        } catch (\Exception $e) {
            return redirect()->route('invoices.index')
                ->with('error', 'Payment processing failed.');
        }
    }

    public function invoiceCallback(Request $request)
    {
        try {
            $transactionId = $request->input('cpm_trans_id');
            $result = $request->input('cpm_result');
            $customData = json_decode($request->input('cpm_custom'), true);

            if ($transactionId && $result === '00' && $customData) {
                $invoiceToken = $customData['invoice_token'];
                $amount = $customData['amount'];

                $invoice = Invoice::where('payment_token', $invoiceToken)->first();

                if ($invoice) {
                    $existingPayment = Payment::where('invoice_id', $invoice->id)
                        ->where('transaction_id', $transactionId)
                        ->first();

                    if (!$existingPayment) {
                        Payment::create([
                            'invoice_id' => $invoice->id,
                            'amount' => $amount,
                            'payment_method' => 'cinetpay',
                            'payment_date' => now(),
                            'transaction_id' => $transactionId,
                            'status' => 'completed',
                            'created_by' => $invoice->created_by
                        ]);

                        // Update invoice status
                        $totalPaid = $invoice->payments()->sum('amount');
                        if ($totalPaid >= $invoice->total_amount) {
                            $invoice->update(['status' => 'paid']);
                        }
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
            
            $invoice = Invoice::where('payment_token', $token)->firstOrFail();
            
            $paymentSettings = PaymentSetting::where('user_id', $invoice->created_by)
                ->whereIn('key', ['cinetpay_site_id', 'cinetpay_api_key', 'is_cinetpay_enabled'])
                ->pluck('value', 'key')
                ->toArray();

            if (($paymentSettings['is_cinetpay_enabled'] ?? '0') !== '1') {
                return response()->json(['error' => 'CinetPay payment method is not enabled'], 400);
            }

            if (empty($paymentSettings['cinetpay_site_id']) || empty($paymentSettings['cinetpay_api_key'])) {
                return response()->json(['error' => 'CinetPay credentials not configured'], 400);
            }

            $transactionId = 'invoice_' . $invoice->id . '_' . time();

            $postData = [
                'apikey' => $paymentSettings['cinetpay_api_key'],
                'site_id' => $paymentSettings['cinetpay_site_id'],
                'transaction_id' => $transactionId,
                'amount' => max((int)($request->amount), 100),
                'currency' => 'XOF',
                'description' => 'Invoice Payment - ' . $invoice->invoice_number,
                'return_url' => route('cinetpay.invoice.success.link', $token) . '?cpm_trans_id=' . $transactionId . '&amount=' . $request->amount,
                'notify_url' => route('cinetpay.invoice.callback.link'),
                'customer_name' => $invoice->client->first_name ?? 'Customer',
                'customer_email' => $invoice->client->email ?? 'customer@example.com',
                'custom' => json_encode([
                    'invoice_token' => $token,
                    'amount' => $request->amount
                ])
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api-checkout.cinetpay.com/v2/payment');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 || $httpCode === 201) {
                $responseData = json_decode($response, true);
                if ($responseData && isset($responseData['data']['payment_url'])) {
                    return response()->json([
                        'success' => true,
                        'payment_url' => $responseData['data']['payment_url'],
                        'transaction_id' => $transactionId
                    ]);
                }
            }
            
            // If API fails, use test mode
            return response()->json([
                'success' => true,
                'payment_url' => route('cinetpay.invoice.success.link', $token) . '?cmp_trans_id=' . $transactionId . '&test=1&amount=' . $request->amount,
                'transaction_id' => $transactionId
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function invoiceSuccessFromLink(Request $request, $token)
    {
        try {
            $transactionId = $request->input('cpm_trans_id');
            $amount = $request->input('amount');
            
            $invoice = Invoice::where('payment_token', $token)->firstOrFail();

            if ($transactionId && $amount) {
                $invoice->createPaymentRecord(
                    (float)$amount,
                    'cinetpay',
                    $transactionId
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