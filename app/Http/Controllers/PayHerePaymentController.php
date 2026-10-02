<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;

class PayHerePaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'payment_id' => 'required|string',
            'status_code' => 'required|string',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['payhere_merchant_id'])) {
                return back()->withErrors(['error' => __('PayHere not configured')]);
            }

            if ($validated['status_code'] === '2') { // Success status
                processPaymentSuccess([
                    'user_id' => auth()->id(),
                    'plan_id' => $plan->id,
                    'billing_cycle' => $validated['billing_cycle'],
                    'payment_method' => 'payhere',
                    'coupon_code' => $validated['coupon_code'] ?? null,
                    'payment_id' => $validated['payment_id'],
                ]);

                return back()->with('success', __('Payment successful and plan activated'));
            }

            return back()->withErrors(['error' => __('Payment failed or cancelled')]);

        } catch (\Exception $e) {
            return handlePaymentError($e, 'payhere');
        }
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
        return redirect()->route('plans.index')->with('success', __('Payment completed successfully'));
    }

    public function callback(Request $request)
    {
        try {
            $orderId = $request->input('order_id');
            $statusCode = $request->input('status_code');
            
            if ($orderId && $statusCode === '2') {
                $parts = explode('_', $orderId);
                
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
                            'payment_method' => 'payhere',
                            'payment_id' => $request->input('payment_id'),
                        ]);
                    }
                }
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Callback processing failed'], 500);
        }
    }

    public function processInvoicePayment(Request $request, Invoice $invoice)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'order_id' => 'required|string',
            'status_code' => 'required|string',
        ]);

        try {
            if ($request->status_code === '2') { // Success status
                Payment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => $request->amount,
                    'payment_method' => 'payhere',
                    'payment_date' => now(),
                    'transaction_id' => $request->order_id,
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
            $orderId = $request->input('order_id');
            $invoiceToken = $request->input('custom_1');
            $amount = $request->input('custom_2');
            $statusCode = $request->input('status_code');

            if ($orderId && $statusCode === '2') {
                if ($invoiceToken) {
                    $invoice = Invoice::where('payment_token', $invoiceToken)->first();
                } else {
                    $parts = explode('_', $orderId);
                    if (count($parts) >= 2 && $parts[0] === 'invoice') {
                        $invoiceId = $parts[1];
                        $invoice = Invoice::find($invoiceId);
                    }
                }

                if ($invoice) {
                    // Check if payment already exists
                    $existingPayment = Payment::where('invoice_id', $invoice->id)
                        ->where('transaction_id', $orderId)
                        ->first();

                    if (!$existingPayment) {
                        Payment::create([
                            'invoice_id' => $invoice->id,
                            'amount' => $amount,
                            'payment_method' => 'payhere',
                            'payment_date' => now(),
                            'transaction_id' => $orderId,
                            'status' => 'completed',
                            'created_by' => $invoice->created_by
                        ]);

                        // Update invoice status
                        $totalPaid = $invoice->payments()->sum('amount');
                        if ($totalPaid >= $invoice->total_amount) {
                            $invoice->update(['status' => 'paid']);
                        }
                    }

                    return redirect()->route('invoices.show', $invoice->id)
                        ->with('success', 'Payment completed successfully!');
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
            $orderId = $request->input('order_id');
            $statusCode = $request->input('status_code');
            $invoiceToken = $request->input('custom_1');
            $amount = $request->input('custom_2');

            if ($orderId && $statusCode === '2' && $invoiceToken) {
                $invoice = Invoice::where('payment_token', $invoiceToken)->first();

                if ($invoice) {
                    $existingPayment = Payment::where('invoice_id', $invoice->id)
                        ->where('transaction_id', $orderId)
                        ->first();

                    if (!$existingPayment) {
                        Payment::create([
                            'invoice_id' => $invoice->id,
                            'amount' => $amount,
                            'payment_method' => 'payhere',
                            'payment_date' => now(),
                            'transaction_id' => $orderId,
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
                $invoice->createPaymentRecord(
                    (float)$amount,
                    'payhere',
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