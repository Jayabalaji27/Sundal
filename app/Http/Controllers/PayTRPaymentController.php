<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Invoice;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PayTRPaymentController extends Controller
{
    private function getPayTRCredentials($userId = null)
    {
        if ($userId) {
            $settings = PaymentSetting::where('user_id', $userId)
                ->whereIn('key', ['paytr_merchant_id', 'paytr_merchant_key', 'paytr_merchant_salt'])
                ->pluck('value', 'key')
                ->toArray();

            return [
                'merchant_id' => $settings['paytr_merchant_id'] ?? null,
                'merchant_key' => $settings['paytr_merchant_key'] ?? null,
                'merchant_salt' => $settings['paytr_merchant_salt'] ?? null,
                'currency' => 'TRY'
            ];
        }

        $settings = getPaymentGatewaySettings();
                
        return [
            'merchant_id' => $settings['payment_settings']['paytr_merchant_id'] ?? null,
            'merchant_key' => $settings['payment_settings']['paytr_merchant_key'] ?? null,
            'merchant_salt' => $settings['payment_settings']['paytr_merchant_salt'] ?? null,
            'currency' => $settings['general_settings']['defaultCurrency'] ?? 'TRY'
        ];
    }

    public function createPaymentToken(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'user_name' => 'required|string',
            'user_email' => 'required|email',
            'user_phone' => 'required|string',
            'user_address' => 'nullable|string',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            $credentials = $this->getPayTRCredentials();
            
            if (!$credentials['merchant_id'] || !$credentials['merchant_key'] || !$credentials['merchant_salt']) {
                throw new \Exception(__('PayTR credentials not configured'));
            }
            
            $merchant_oid = 'plan_' . $plan->id . '_' . time() . '_' . uniqid();
            $payment_amount = intval($pricing['final_price'] * 100); // Convert to kuruş
            $user_basket = json_encode([[
                $plan->name . ' - ' . ucfirst($validated['billing_cycle']),
                number_format($pricing['final_price'], 2),
                1
            ]]);
            
            // Create pending order
            createPlanOrder([
                'user_id' => auth()->id(),
                'plan_id' => $plan->id,
                'billing_cycle' => $validated['billing_cycle'],
                'payment_method' => 'paytr',
                'coupon_code' => $validated['coupon_code'] ?? null,
                'payment_id' => $merchant_oid,
                'status' => 'pending'
            ]);
            
            // Generate hash according to PayTR documentation
            $hashStr = $credentials['merchant_id'] . 
                      $request->ip() .
                      $merchant_oid .
                      $validated['user_email'] .
                      $payment_amount .
                      $user_basket .
                      '1' . // no_installment
                      '0' . // max_installment
                      $credentials['currency'] .
                      '1' . // test_mode
                      $credentials['merchant_salt'];
            
            $paytr_token = base64_encode(hash_hmac('sha256', $hashStr, $credentials['merchant_key'], true));
            
            $post_data = [
                'merchant_id' => $credentials['merchant_id'],
                'user_ip' => $request->ip(),
                'merchant_oid' => $merchant_oid,
                'email' => $validated['user_email'],
                'payment_amount' => $payment_amount,
                'paytr_token' => $paytr_token,
                'user_basket' => $user_basket,
                'no_installment' => 1,
                'max_installment' => 0,
                'user_name' => $validated['user_name'],
                'user_address' => $validated['user_address'] ?? 'Turkey',
                'user_phone' => $validated['user_phone'],
                'merchant_ok_url' => route('paytr.success'),
                'merchant_fail_url' => route('paytr.failure'),
                'timeout_limit' => 30,
                'currency' => $credentials['currency'],
                'test_mode' => 1
            ];
            
            $response = Http::asForm()->timeout(40)->post('https://www.paytr.com/odeme/api/get-token', $post_data);
            
            if ($response->successful()) {
                $result = $response->json();
                if ($result['status'] == 'success') {
                    return response()->json([
                        'success' => true,
                        'token' => $result['token'],
                        'iframe_url' => 'https://www.paytr.com/odeme/guvenli/' . $result['token']
                    ]);
                } else {
                    throw new \Exception($result['reason'] ?? __('Token generation failed'));
                }
            } else {
                throw new \Exception(__('PayTR API connection failed'));
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function success(Request $request)
    {
        return redirect()->route('plans.index')->with('success', __('Payment completed successfully!'));
    }

    public function failure(Request $request)
    {
        return redirect()->route('plans.index')->with('error', __('Payment failed. Please try again.'));
    }

    public function callback(Request $request)
    {
        try {
            $merchant_oid = $request->input('merchant_oid');
            $status = $request->input('status');
            $total_amount = $request->input('total_amount');
            $hash = $request->input('hash');
            
            $credentials = $this->getPayTRCredentials();
            
            // Verify hash for security
            $hashStr = $merchant_oid . $credentials['merchant_salt'] . $status . $total_amount;
            $calculatedHash = base64_encode(hash_hmac('sha256', $hashStr, $credentials['merchant_key'], true));
                        
            if ($hash === $calculatedHash && $status === 'success') {
                $planOrder = \App\Models\PlanOrder::where('payment_id', $merchant_oid)->first();
                
                if ($planOrder && $planOrder->status === 'pending') {
                    processPaymentSuccess([
                        'user_id' => $planOrder->user_id,
                        'plan_id' => $planOrder->plan_id,
                        'billing_cycle' => $planOrder->billing_cycle,
                        'payment_method' => 'paytr',
                        'coupon_code' => $planOrder->coupon_code,
                        'payment_id' => $merchant_oid,
                    ]);
                }
            }
            
            return response('OK', 200);
        } catch (\Exception $e) {
            return response('ERROR', 500);
        }
    }

    public function createInvoicePayment(Request $request)
    {
        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01'
        ]);

        try {
            $invoice = Invoice::findOrFail($request->invoice_id);
            $settings = PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            
            if (!isset($settings['paytr_merchant_id']) || !isset($settings['is_paytr_enabled']) || $settings['is_paytr_enabled'] !== '1') {
                return response()->json(['error' => __('PayTR not configured')], 400);
            }

            $credentials = $this->getPayTRCredentials($invoice->created_by);
            $merchant_oid = 'invoice_' . $invoice->id . '_' . time() . '_' . uniqid();
            $payment_amount = intval($request->amount * 100); // Convert to kuruş
            $user_basket = json_encode([[
                'Invoice #' . $invoice->invoice_number . ' Payment',
                number_format($request->amount, 2),
                1
            ]]);

            // Generate hash according to PayTR documentation
            $hashStr = $credentials['merchant_id'] . 
                      $request->ip() .
                      $merchant_oid .
                      'customer@example.com' .
                      $payment_amount .
                      $user_basket .
                      '1' . // no_installment
                      '0' . // max_installment
                      'TRY' .
                      '1' . // test_mode
                      $credentials['merchant_salt'];
            
            $paytr_token = base64_encode(hash_hmac('sha256', $hashStr, $credentials['merchant_key'], true));
            
            $post_data = [
                'merchant_id' => $credentials['merchant_id'],
                'user_ip' => $request->ip(),
                'merchant_oid' => $merchant_oid,
                'email' => 'customer@example.com',
                'payment_amount' => $payment_amount,
                'paytr_token' => $paytr_token,
                'user_basket' => $user_basket,
                'no_installment' => 1,
                'max_installment' => 0,
                'user_name' => 'Customer',
                'user_address' => 'Turkey',
                'user_phone' => '5555555555',
                'merchant_ok_url' => route('paytr.invoice.success', [
                    'invoice_id' => $invoice->id,
                    'amount' => $request->amount
                ]),
                'merchant_fail_url' => route('invoices.show', $invoice->id),
                'timeout_limit' => 30,
                'currency' => 'TRY',
                'test_mode' => 1
            ];
            
            $response = Http::asForm()->timeout(40)->post('https://www.paytr.com/odeme/api/get-token', $post_data);
            
            if ($response->successful()) {
                $result = $response->json();
                if ($result['status'] == 'success') {
                    return response()->json([
                        'success' => true,
                        'redirect_url' => 'https://www.paytr.com/odeme/guvenli/' . $result['token']
                    ]);
                } else {
                    return response()->json(['error' => $result['reason'] ?? __('Token generation failed')], 500);
                }
            }
            
            return response()->json(['error' => __('PayTR API connection failed')], 500);

        } catch (\Exception $e) {
            return response()->json(['error' => __('Payment creation failed')], 500);
        }
    }

    public function processInvoicePayment(Request $request)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'merchant_oid' => 'required|string',
            ]);
            
            $invoice = Invoice::where('payment_token', $request->route('token'))->firstOrFail();
            $settings = PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            
            if (!isset($settings['is_paytr_enabled']) || $settings['is_paytr_enabled'] !== '1') {
                return back()->withErrors(['error' => 'PayTR not enabled']);
            }

            // For demo purposes, simulate successful payment
            $invoice->createPaymentRecord(
                $request->amount,
                'paytr',
                $request->merchant_oid
            );
            
            return redirect()->route('invoices.show', $invoice->id)
                ->with('success', 'PayTR payment processed successfully.');
            
        } catch (ValidationException $e) {
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
            $merchantOid = $request->input('merchant_oid');
            
            if ($invoiceId && $amount) {
                $invoice = Invoice::find($invoiceId);
                
                if ($invoice) {
                    $invoice->createPaymentRecord(
                        $amount,
                        'paytr',
                        $merchantOid ?: 'paytr_' . time()
                    );
                    
                    return redirect()->route('invoices.show', $invoice->id)
                        ->with('success', __('PayTR payment completed successfully'));
                }
            }
            
            return redirect()->route('home')->with('error', __('Payment verification failed'));
            
        } catch (\Exception $e) {
            return redirect()->route('home')->with('error', __('Payment processing failed'));
        }
    }

    public function processInvoicePaymentFromLink(Request $request, $token)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01'
            ]);
            
            $invoice = Invoice::where('payment_token', $token)->firstOrFail();
            $settings = PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            
            if (!isset($settings['is_paytr_enabled']) || $settings['is_paytr_enabled'] !== '1') {
                return response()->json(['error' => 'PayTR not enabled'], 400);
            }

            if (!isset($settings['paytr_merchant_id'])) {
                return response()->json(['error' => 'PayTR not configured'], 400);
            }

            $credentials = $this->getPayTRCredentials($invoice->created_by);
            $merchant_oid = 'invoice_' . $invoice->id . '_' . time() . '_' . uniqid();
            $payment_amount = intval($request->amount * 100);
            $user_basket = json_encode([[
                'Invoice #' . $invoice->invoice_number . ' Payment',
                number_format($request->amount, 2),
                1
            ]]);

            $hashStr = $credentials['merchant_id'] . 
                      $request->ip() .
                      $merchant_oid .
                      'customer@example.com' .
                      $payment_amount .
                      $user_basket .
                      '1' . '0' . 'TRY' . '1' .
                      $credentials['merchant_salt'];
            
            $paytr_token = base64_encode(hash_hmac('sha256', $hashStr, $credentials['merchant_key'], true));
            
            $post_data = [
                'merchant_id' => $credentials['merchant_id'],
                'user_ip' => $request->ip(),
                'merchant_oid' => $merchant_oid,
                'email' => 'customer@example.com',
                'payment_amount' => $payment_amount,
                'paytr_token' => $paytr_token,
                'user_basket' => $user_basket,
                'no_installment' => 1,
                'max_installment' => 0,
                'user_name' => 'Customer',
                'user_address' => 'Turkey',
                'user_phone' => '5555555555',
                'merchant_ok_url' => route('paytr.invoice.success.link', [
                    'token' => $token,
                    'amount' => $request->amount
                ]),
                'merchant_fail_url' => route('invoices.payment', $token),
                'timeout_limit' => 30,
                'currency' => 'TRY',
                'test_mode' => 1
            ];
            
            $response = Http::asForm()->timeout(40)->post('https://www.paytr.com/odeme/api/get-token', $post_data);
            
            if ($response->successful()) {
                $result = $response->json();
                if ($result['status'] == 'success') {
                    return response()->json([
                        'success' => true,
                        'redirect_url' => 'https://www.paytr.com/odeme/guvenli/' . $result['token']
                    ]);
                } else {
                    return response()->json(['error' => $result['reason'] ?? 'Token generation failed'], 400);
                }
            }
            
            return response()->json(['error' => 'PayTR API connection failed'], 400);
            
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Payment processing failed. Please try again.'], 500);
        }
    }

    public function invoiceSuccessFromLink(Request $request, $token)
    {
        try {
            $amount = $request->input('amount');
            $merchantOid = $request->input('merchant_oid');
            
            if ($amount) {
                $invoice = Invoice::where('payment_token', $token)->firstOrFail();
                
                $invoice->createPaymentRecord(
                    $amount,
                    'paytr',
                    $merchantOid ?: 'paytr_' . time()
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