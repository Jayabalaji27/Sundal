<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;

class OzowPaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'transaction_id' => 'required|string',
            'status' => 'required|string',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $settings = getPaymentGatewaySettings();

            if (!isset($settings['payment_settings']['ozow_site_key'])) {
                return back()->withErrors(['error' => __('Ozow not configured')]);
            }

            if ($validated['status'] === 'Complete') {
                processPaymentSuccess([
                    'user_id' => auth()->id(),
                    'plan_id' => $plan->id,
                    'billing_cycle' => $validated['billing_cycle'],
                    'payment_method' => 'ozow',
                    'coupon_code' => $validated['coupon_code'] ?? null,
                    'payment_id' => $validated['transaction_id'],
                ]);

                return back()->with('success', __('Payment successful and plan activated'));
            }

            return back()->withErrors(['error' => __('Payment failed or cancelled')]);

        } catch (\Exception $e) {
            return handlePaymentError($e, 'ozow');
        }
    }

    public function createPayment(Request $request)
    {
        $validated = validatePaymentRequest($request);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            $settings = getPaymentGatewaySettings();

            if (!isset($settings['payment_settings']['ozow_site_key']) || !isset($settings['payment_settings']['ozow_private_key']) || !isset($settings['payment_settings']['ozow_api_key'])) {
                return response()->json(['error' => __('Ozow not configured')], 400);
            }

            $siteCode = $settings['payment_settings']['ozow_site_key'];
            $privateKey = $settings['payment_settings']['ozow_private_key'];
            $apiKey = $settings['payment_settings']['ozow_api_key'];
            $isTest = $settings['payment_settings']['ozow_mode'] == 'sandbox' ? 'true' : 'false';
            $amount = $pricing['final_price'];
            $cancelUrl = route('plans.index');
            $successUrl = route('ozow.success');
            $bankReference = time() . 'FKU';
            $transactionReference = time();
            $countryCode = 'ZA';
            $currency = 'ZAR';

            $inputString = $siteCode . $countryCode . $currency . $amount . $transactionReference . $bankReference . $cancelUrl . $successUrl . $successUrl . $successUrl . $isTest . $privateKey;
            $hashCheck = hash('sha512', strtolower($inputString));

            $data = [
                'countryCode' => $countryCode,
                'amount' => $amount,
                'transactionReference' => $transactionReference,
                'bankReference' => $bankReference,
                'cancelUrl' => $cancelUrl,
                'currencyCode' => $currency,
                'errorUrl' => $successUrl,
                'isTest' => $isTest,
                'notifyUrl' => $successUrl,
                'siteCode' => $siteCode,
                'successUrl' => $successUrl,
                'hashCheck' => $hashCheck,
            ];

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.ozow.com/postpaymentrequest',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'ApiKey: ' . $apiKey,
                    'Content-Type: application/json'
                ],
            ]);

            $response = curl_exec($curl);
            curl_close($curl);
            $json_attendance = json_decode($response);

            if (isset($json_attendance->url) && $json_attendance->url != null) {
                return response()->json([
                    'success' => true,
                    'payment_url' => $json_attendance->url,
                    'transaction_id' => $transactionReference
                ]);
            } else {
                return response()->json(['error' => __('Payment creation failed')], 500);
            }

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
            $transactionId = $request->input('TransactionReference');
            $status = $request->input('Status');

            if ($transactionId && $status === 'Complete') {
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
                            'payment_method' => 'ozow',
                            'payment_id' => $transactionId,
                        ]);
                    }
                }
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Callback processing failed'], 500);
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

            $settings = \App\Models\PaymentSetting::where('user_id', $invoice->created_by)
                ->pluck('value', 'key')
                ->toArray();

            // Log settings for debugging
            \Log::info('Ozow Settings Check', [
                'invoice_created_by' => $invoice->created_by,
                'ozow_enabled' => $settings['is_ozow_enabled'] ?? 'not_set',
                'has_site_key' => isset($settings['ozow_site_key']),
                'has_private_key' => isset($settings['ozow_private_key']),
                'has_api_key' => isset($settings['ozow_api_key'])
            ]);

            if (!isset($settings['ozow_site_key']) || !isset($settings['ozow_private_key']) || !isset($settings['ozow_api_key']) || $settings['is_ozow_enabled'] !== '1') {
                return response()->json(['error' => __('Ozow not configured')], 400);
            }

            $currentUserId = auth()->id() ?? 'guest';
            $siteCode = $settings['ozow_site_key'];
            $privateKey = $settings['ozow_private_key'];
            $apiKey = $settings['ozow_api_key'];
            $isTest = $settings['ozow_mode'] == 'sandbox' ? 'true' : 'false';
            $amount = $request->amount;
            $cancelUrl = route('invoices.show', $invoice->id);
            $successUrl = route('ozow.invoice.success');
            $bankReference = 'inv' . $invoice->id . '_' . $currentUserId;
            $transactionReference = 'invoice_' . $invoice->id . '_' . $currentUserId . '_' . time();
            $countryCode = 'ZA';
            $currency = 'ZAR';

            $inputString = $siteCode . $countryCode . $currency . $amount . $transactionReference . $bankReference . $cancelUrl . $successUrl . $successUrl . $successUrl . $isTest . $privateKey;
            $hashCheck = hash('sha512', strtolower($inputString));

            $data = [
                'countryCode' => $countryCode,
                'amount' => $amount,
                'transactionReference' => $transactionReference,
                'bankReference' => $bankReference,
                'cancelUrl' => $cancelUrl,
                'currencyCode' => $currency,
                'errorUrl' => $successUrl,
                'isTest' => $isTest,
                'notifyUrl' => $successUrl,
                'siteCode' => $siteCode,
                'successUrl' => $successUrl,
                'hashCheck' => $hashCheck,
            ];

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.ozow.com/postpaymentrequest',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'ApiKey: ' . $apiKey,
                    'Content-Type: application/json'
                ],
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);
            $json_attendance = json_decode($response);



            if (isset($json_attendance->url) && $json_attendance->url != null) {
                return response()->json([
                    'success' => true,
                    'payment_url' => $json_attendance->url,
                    'transaction_id' => $transactionReference
                ]);
            } else {
                return response()->json(['error' => __('Payment creation failed')], 500);
            }

        } catch (\Exception $e) {
            return response()->json(['error' => __('Payment creation failed')], 500);
        }
    }

    public function invoiceSuccess(Request $request)
    {
        try {
            $transactionId = $request->input('TransactionReference');
            $status = $request->input('Status');
            $amount = $request->input('Amount');

            if (!$transactionId) {
                return redirect()->route('home')
                    ->with('error', __('Invalid transaction'));
            }

            // Extract invoice ID and user ID from transaction ID (format: invoice_ID_userID_timestamp)
            $parts = explode('_', $transactionId);
            if (count($parts) < 3 || $parts[0] !== 'invoice') {
                return redirect()->route('home')
                    ->with('error', __('Invalid transaction format'));
            }

            $invoiceId = $parts[1];
            $userId = $parts[2];
            $invoice = \App\Models\Invoice::find($invoiceId);

            if (!$invoice) {
                return redirect()->route('home')
                    ->with('error', __('Invoice not found'));
            }

            if ($status === 'Complete') {
                $invoice->createPaymentRecord($amount, 'ozow', $transactionId);

                // Log in the user who made the payment if not already authenticated
                if (!auth()->check() && $userId !== 'guest') {
                    $user = User::find($userId);
                    if ($user) {
                        auth()->login($user);
                    }
                }

                return redirect()->route('invoices.show', $invoice->id)
                    ->with('success', __('Payment completed successfully!'));
            }

            return redirect()->route('invoices.show', $invoice->id)
                ->with('error', __('Payment verification failed'));

        } catch (\Exception $e) {
            return redirect()->route('home')
                ->with('error', __('Payment processing failed'));
        }
    }

    public function processInvoicePayment(Request $request)
    {
        $request->validate([
            'invoice_token' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'transaction_id' => 'required|string',
            'status' => 'required|string',
        ]);

        try {
            $invoice = \App\Models\Invoice::where('payment_token', $request->invoice_token)->firstOrFail();

            if ($request->status === 'Complete') {
                $invoice->createPaymentRecord($request->amount, 'ozow', $request->transaction_id);

                return redirect()->route('invoices.show', $invoice->id)
                    ->with('success', __('Payment completed successfully!'));
            }

            return back()->withErrors(['error' => __('Payment failed or cancelled')]);

        } catch (\Exception $e) {
            return back()->withErrors(['error' => __('Payment processing failed. Please try again or contact support.')]);
        }
    }

    public function createInvoicePaymentFromLink(Request $request, $token)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01'
            ]);

            $invoice = \App\Models\Invoice::where('payment_token', $token)->firstOrFail();

            $settings = \App\Models\PaymentSetting::where('user_id', $invoice->created_by)
                ->pluck('value', 'key')
                ->toArray();

            if (!isset($settings['ozow_site_key']) || !isset($settings['ozow_private_key']) || !isset($settings['ozow_api_key']) || $settings['is_ozow_enabled'] !== '1') {
                return response()->json(['error' => __('Ozow not configured')], 400);
            }

            $siteCode = $settings['ozow_site_key'];
            $privateKey = $settings['ozow_private_key'];
            $apiKey = $settings['ozow_api_key'];
            $isTest = $settings['ozow_mode'] == 'sandbox' ? 'true' : 'false';
            $amount = $request->amount;
            $cancelUrl = route('invoices.payment', $token);
            $successUrl = route('ozow.invoice.success.from-link', $token);
            $bankReference = 'inv' . $invoice->id . '_link';
            $transactionReference = 'invoice_' . $invoice->id . '_link_' . time();
            $countryCode = 'ZA';
            $currency = 'ZAR';

            $inputString = $siteCode . $countryCode . $currency . $amount . $transactionReference . $bankReference . $cancelUrl . $successUrl . $successUrl . $successUrl . $isTest . $privateKey;
            $hashCheck = hash('sha512', strtolower($inputString));

            $data = [
                'countryCode' => $countryCode,
                'amount' => $amount,
                'transactionReference' => $transactionReference,
                'bankReference' => $bankReference,
                'cancelUrl' => $cancelUrl,
                'currencyCode' => $currency,
                'errorUrl' => $successUrl,
                'isTest' => $isTest,
                'notifyUrl' => $successUrl,
                'siteCode' => $siteCode,
                'successUrl' => $successUrl,
                'hashCheck' => $hashCheck,
            ];

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.ozow.com/postpaymentrequest',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'ApiKey: ' . $apiKey,
                    'Content-Type: application/json'
                ],
            ]);

            $response = curl_exec($curl);
            curl_close($curl);
            $json_response = json_decode($response);

            if (isset($json_response->url) && $json_response->url != null) {
                return response()->json([
                    'success' => true,
                    'payment_url' => $json_response->url,
                    'transaction_id' => $transactionReference
                ]);
            } else {
                return response()->json(['error' => __('Payment creation failed')], 500);
            }

        } catch (\Exception $e) {
            return response()->json(['error' => __('Payment creation failed')], 500);
        }
    }

    public function invoiceSuccessFromLink(Request $request, $token)
    {
        try {
            $status = $request->input('Status');
            $amount = $request->input('Amount');
            $transactionId = $request->input('TransactionReference');

            $invoice = \App\Models\Invoice::where('payment_token', $token)->firstOrFail();

            if ($status === 'Complete') {
                $invoice->createPaymentRecord($amount, 'ozow', $transactionId);

                return redirect()->route('invoices.payment', $token)
                    ->with('success', 'Payment processed successfully.');
            }

            return redirect()->route('invoices.payment', $token)
                ->with('error', __('Payment verification failed'));

        } catch (\Exception $e) {
            return redirect()->route('invoices.payment', $token)
                ->with('error', __('Payment processing failed'));
        }
    }
}
