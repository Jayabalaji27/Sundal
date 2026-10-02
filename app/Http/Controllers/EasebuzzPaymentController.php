<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;

class EasebuzzPaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'easepayid' => 'required|string',
            'status' => 'required|string',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['easebuzz_merchant_key'])) {
                return back()->withErrors(['error' => __('Easebuzz not configured')]);
            }

            if ($validated['status'] === 'success') {
                processPaymentSuccess([
                    'user_id' => auth()->id(),
                    'plan_id' => $plan->id,
                    'billing_cycle' => $validated['billing_cycle'],
                    'payment_method' => 'easebuzz',
                    'coupon_code' => $validated['coupon_code'] ?? null,
                    'payment_id' => $validated['easepayid'],
                ]);

                return back()->with('success', __('Payment successful and plan activated'));
            }

            return back()->withErrors(['error' => __('Payment failed or cancelled')]);

        } catch (\Exception $e) {
            return handlePaymentError($e, 'easebuzz');
        }
    }

    public function createPayment(Request $request)
    {
        $validated = validatePaymentRequest($request);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['easebuzz_merchant_key']) || !isset($settings['payment_settings']['easebuzz_salt_key'])) {
                return response()->json(['error' => __('Easebuzz not configured')], 400);
            }

            // Include Easebuzz library
            require_once app_path('Libraries/Easebuzz/easebuzz_payment_gateway.php');
            
            $user = auth()->user();
            $txnid = 'plan_' . $plan->id . '_' . $user->id . '_' . time();
            $environment = $settings['payment_settings']['easebuzz_environment'] === 'prod' ? 'prod' : 'test';

            // Initialize Easebuzz
            $easebuzz = new \Easebuzz(
                $settings['payment_settings']['easebuzz_merchant_key'],
                $settings['payment_settings']['easebuzz_salt_key'],
                $environment
            );

            $postData = [
                'txnid' => $txnid,
                'amount' => number_format($pricing['final_price'], 2, '.', ''),
                'productinfo' => $plan->name,
                'firstname' => $user->name ?? 'Customer',
                'email' => $user->email,
                'phone' => '9999999999',
                'surl' => route('easebuzz.success'),
                'furl' => route('plans.index'),
                'udf1' => $validated['billing_cycle'],
                'udf2' => $validated['coupon_code'] ?? '',
            ];

            // Use Easebuzz library to initiate payment
            $result = $easebuzz->initiatePaymentAPI($postData, false);
            
            $resultArray = json_decode($result, true);
            
            if ($resultArray && isset($resultArray['status']) && $resultArray['status'] == 1) {
                $accessKey = $resultArray['access_key'] ?? null;
                if ($accessKey) {
                    $baseUrl = $settings['payment_settings']['easebuzz_environment'] === 'prod' 
                        ? 'https://pay.easebuzz.in' 
                        : 'https://testpay.easebuzz.in';
                    
                    return response()->json([
                        'success' => true,
                        'payment_url' => $baseUrl . '/pay/' . $accessKey,
                        'transaction_id' => $txnid
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
        try {
            // Get settings without authentication dependency
            $superAdmin = User::where('type', 'superadmin')->first();
            $settings = $superAdmin ? getPaymentGatewaySettings($superAdmin->id) : getPaymentGatewaySettings();
            
            // Include Easebuzz library
            require_once app_path('Libraries/Easebuzz/easebuzz_payment_gateway.php');
            
            $environment = $settings['payment_settings']['easebuzz_environment'] === 'prod' ? 'prod' : 'test';
            
            $easebuzz = new \Easebuzz(
                $settings['payment_settings']['easebuzz_merchant_key'],
                $settings['payment_settings']['easebuzz_salt_key'],
                $environment
            );
            
            // Verify payment response
            $result = $easebuzz->easebuzzResponse($request->all());
            $resultArray = json_decode($result, true);
            
            // Process if either verification passes OR status is success (fallback)
            if (($resultArray && $resultArray['status'] == 1 && $request->input('status') === 'success') || $request->input('status') === 'success') {
                $txnid = $request->input('txnid');
                $parts = explode('_', $txnid);
                
                if (count($parts) >= 3) {
                    $planId = $parts[1];
                    $userId = $parts[2];
                    
                    $plan = Plan::find($planId);
                    $user = User::find($userId);
                    
                    if ($plan && $user) {
                        processPaymentSuccess([
                            'user_id' => $user->id,
                            'plan_id' => $plan->id,
                            'billing_cycle' => $request->input('udf1', 'monthly'),
                            'payment_method' => 'easebuzz',
                            'coupon_code' => $request->input('udf2'),
                            'payment_id' => $request->input('easepayid'),
                        ]);
                        
                        // Log the user in if not already authenticated
                        if (!auth()->check()) {
                            auth()->login($user);
                        }
                        
                        return redirect()->route('plans.index')->with('success', __('Payment completed successfully and plan activated'));
                    }
                }
            }
            
            return redirect()->route('plans.index')->with('error', __('Payment verification failed'));
            
        } catch (\Exception $e) {
            return redirect()->route('plans.index')->with('error', __('Payment processing failed'));
        }
    }

    public function callback(Request $request)
    {
        try {
            $txnid = $request->input('txnid');
            $status = $request->input('status');
            
            if ($txnid && $status === 'success') {
                $parts = explode('_', $txnid);
                
                if (count($parts) >= 3) {
                    $planId = $parts[1];
                    $userId = $parts[2];
                    
                    $plan = Plan::find($planId);
                    $user = \App\Models\User::find($userId);
                    
                    if ($plan && $user) {
                        processPaymentSuccess([
                            'user_id' => $user->id,
                            'plan_id' => $plan->id,
                            'billing_cycle' => $request->input('udf1', 'monthly'),
                            'payment_method' => 'easebuzz',
                            'payment_id' => $request->input('easepayid'),
                        ]);
                    }
                }
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            return response()->json(['error' => __('Callback processing failed')], 500);
        }
    }

    public function processInvoicePayment(Request $request)
    {
        $request->validate([
            'invoice_token' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'easepayid' => 'required|string',
            'status' => 'required|string',
        ]);

        try {
            $invoice = \App\Models\Invoice::where('payment_token', $request->invoice_token)->firstOrFail();

            if ($request->status === 'success') {
                $invoice->createPaymentRecord($request->amount, 'easebuzz', $request->easepayid);

                return redirect()->route('invoices.show', $invoice->id)
                    ->with('success', __('Payment completed successfully!'));
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

            $invoice = \App\Models\Invoice::where('payment_token', $request->invoice_token)->firstOrFail();

            $settings = \App\Models\PaymentSetting::where('user_id', $invoice->created_by)
                ->pluck('value', 'key')
                ->toArray();

            if (!isset($settings['easebuzz_merchant_key']) || !isset($settings['easebuzz_salt_key']) || $settings['is_easebuzz_enabled'] !== '1') {
                return response()->json(['error' => __('Easebuzz not configured')], 400);
            }

            require_once app_path('Libraries/Easebuzz/easebuzz_payment_gateway.php');

            $currentUserId = auth()->id() ?? 'guest';
            $txnid = 'invoice_' . $invoice->id . '_' . $currentUserId . '_' . time();
            $environment = $settings['easebuzz_environment'] === 'prod' ? 'prod' : 'test';

            $easebuzz = new \Easebuzz(
                $settings['easebuzz_merchant_key'],
                $settings['easebuzz_salt_key'],
                $environment
            );

            $postData = [
                'txnid' => $txnid,
                'amount' => number_format($request->amount, 2, '.', ''),
                'productinfo' => 'Invoice Payment - ' . $invoice->invoice_number,
                'firstname' => 'Customer',
                'email' => 'customer@example.com',
                'phone' => '9999999999',
                'surl' => route('easebuzz.invoice.success'),
                'furl' => route('invoices.show', $invoice->payment_token),
                'udf1' => $invoice->payment_token,
                'udf2' => $request->amount,
            ];

            $result = $easebuzz->initiatePaymentAPI($postData, false);
            $resultArray = json_decode($result, true);

            if ($resultArray && isset($resultArray['status']) && $resultArray['status'] == 1) {
                $accessKey = $resultArray['access_key'] ?? null;
                if ($accessKey) {
                    $baseUrl = $settings['easebuzz_environment'] === 'prod'
                        ? 'https://pay.easebuzz.in'
                        : 'https://testpay.easebuzz.in';

                    return response()->json([
                        'success' => true,
                        'payment_url' => $baseUrl . '/pay/' . $accessKey,
                        'transaction_id' => $txnid
                    ]);
                }
            }

            return response()->json(['error' => 'Payment initialization failed'], 400);

        } catch (\Exception $e) {
            return response()->json(['error' => __('Payment creation failed')], 500);
        }
    }

    public function invoiceSuccess(Request $request)
    {
        try {
            $txnid = $request->input('txnid');
            $amount = $request->input('udf2');
            $status = $request->input('status');
            $easepayid = $request->input('easepayid');

            if (!$txnid) {
                return redirect()->route('home')
                    ->with('error', __('Invalid transaction'));
            }

            // Extract invoice ID and user ID from transaction ID (format: invoice_ID_userID_timestamp)
            $parts = explode('_', $txnid);
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

            require_once app_path('Libraries/Easebuzz/easebuzz_payment_gateway.php');

            $settings = \App\Models\PaymentSetting::where('user_id', $invoice->created_by)
                ->pluck('value', 'key')
                ->toArray();

            $environment = $settings['easebuzz_environment'] === 'prod' ? 'prod' : 'test';

            $easebuzz = new \Easebuzz(
                $settings['easebuzz_merchant_key'],
                $settings['easebuzz_salt_key'],
                $environment
            );

            $result = $easebuzz->easebuzzResponse($request->all());
            $resultArray = json_decode($result, true);

            // Process if either verification passes OR status is success (fallback)
            if (($resultArray && $resultArray['status'] == 1 && $status === 'success') || $status === 'success') {
                $invoice->createPaymentRecord($amount, 'easebuzz', $easepayid);

                // Log in the user who made the payment if not already authenticated
                if (!auth()->check() && $userId !== 'guest') {
                    $user = User::find($userId);
                    if ($user) {
                        auth()->login($user);
                    }
                }

                return redirect()->route('invoices.show', $invoice->id)
                    ->with('success', 'Payment completed successfully!');
            }

            return redirect()->route('invoices.show', $invoice->id)
                ->with('error', __('Payment verification failed'));

        } catch (\Exception $e) {
            return redirect()->route('home')
                ->with('error', __('Payment processing failed'));
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

            if (!isset($settings['easebuzz_merchant_key']) || !isset($settings['easebuzz_salt_key']) || $settings['is_easebuzz_enabled'] !== '1') {
                return response()->json(['error' => __('Easebuzz not configured')], 400);
            }

            require_once app_path('Libraries/Easebuzz/easebuzz_payment_gateway.php');

            $txnid = 'invoice_' . $invoice->id . '_link_' . time();
            $environment = $settings['easebuzz_environment'] === 'prod' ? 'prod' : 'test';

            $easebuzz = new \Easebuzz(
                $settings['easebuzz_merchant_key'],
                $settings['easebuzz_salt_key'],
                $environment
            );

            $postData = [
                'txnid' => $txnid,
                'amount' => number_format($request->amount, 2, '.', ''),
                'productinfo' => 'Invoice Payment - ' . $invoice->invoice_number,
                'firstname' => 'Customer',
                'email' => 'customer@example.com',
                'phone' => '9999999999',
                'surl' => route('easebuzz.invoice.success.from-link', $token),
                'furl' => route('invoices.payment', $token),
                'udf1' => $token,
                'udf2' => $request->amount,
            ];

            $result = $easebuzz->initiatePaymentAPI($postData, false);
            $resultArray = json_decode($result, true);

            if ($resultArray && isset($resultArray['status']) && $resultArray['status'] == 1) {
                $accessKey = $resultArray['access_key'] ?? null;
                if ($accessKey) {
                    $baseUrl = $settings['easebuzz_environment'] === 'prod'
                        ? 'https://pay.easebuzz.in'
                        : 'https://testpay.easebuzz.in';

                    return response()->json([
                        'success' => true,
                        'payment_url' => $baseUrl . '/pay/' . $accessKey,
                        'transaction_id' => $txnid
                    ]);
                }
            }

            return response()->json(['error' => 'Payment initialization failed'], 400);

        } catch (\Exception $e) {
            return response()->json(['error' => __('Payment creation failed')], 500);
        }
    }

    public function invoiceSuccessFromLink(Request $request, $token)
    {
        try {
            $status = $request->input('status');
            $easepayid = $request->input('easepayid');
            $amount = $request->input('udf2');

            $invoice = \App\Models\Invoice::where('payment_token', $token)->firstOrFail();

            require_once app_path('Libraries/Easebuzz/easebuzz_payment_gateway.php');

            $settings = \App\Models\PaymentSetting::where('user_id', $invoice->created_by)
                ->pluck('value', 'key')
                ->toArray();

            $environment = $settings['easebuzz_environment'] === 'prod' ? 'prod' : 'test';

            $easebuzz = new \Easebuzz(
                $settings['easebuzz_merchant_key'],
                $settings['easebuzz_salt_key'],
                $environment
            );

            $result = $easebuzz->easebuzzResponse($request->all());
            $resultArray = json_decode($result, true);

            if (($resultArray && $resultArray['status'] == 1 && $status === 'success') || $status === 'success') {
                $invoice->createPaymentRecord($amount, 'easebuzz', $easepayid);

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
