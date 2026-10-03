<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanOrder;
use App\Models\User;
use Illuminate\Http\Request;

class EasebuzzPaymentController extends Controller
{
    /**
     * Legacy browser endpoint. A "success" status posted by the browser proves
     * nothing, so this never activates a plan; Easebuzz's hash-verified response
     * (success/callback) does. This only reports the order's state.
     */
    public function processPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'txnid' => 'required|string',
        ]);

        $order = PlanOrder::where('payment_id', $validated['txnid'])
            ->where('user_id', auth()->id())
            ->where('payment_method', 'easebuzz')
            ->first();

        if ($order?->status === 'approved') {
            return back()->with('success', __('Payment successful and plan activated'));
        }

        return back()->with('warning', __('Your plan will be activated as soon as Easebuzz confirms the payment.'));
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

            // Assigned only once Easebuzz's hash-verified response confirms it.
            createPendingPlanOrder([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $validated['billing_cycle'],
                'payment_method' => 'easebuzz',
                'coupon_code' => $validated['coupon_code'] ?? null,
                'payment_id' => $txnid,
            ]);
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

    /**
     * Easebuzz browser redirect (surl). Public route, so nothing here is trusted
     * without a valid reverse hash. Never logs anyone in.
     */
    public function success(Request $request)
    {
        $completed = $this->completeVerifiedPlanPayment($request);

        return redirect()->route('plans.index')->with(
            $completed ? 'success' : 'error',
            $completed ? __('Payment completed successfully and plan activated') : __('Payment verification failed')
        );
    }

    /** Easebuzz server-to-server notification for plan purchases. */
    public function callback(Request $request)
    {
        $completed = $this->completeVerifiedPlanPayment($request);

        return response()->json(['status' => $completed ? 'success' : 'rejected'], $completed ? 200 : 400);
    }

    /**
     * Browser-reported Easebuzz result for an invoice: recorded as pending for
     * the invoice owner to approve, since it can't be verified here.
     */
    public function processInvoicePayment(Request $request)
    {
        $request->validate([
            'invoice_token' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'easepayid' => 'required|string',
            'status' => 'required|string',
        ]);

        try {
            $invoice = \App\Models\Invoice::where('payment_token', $request->invoice_token)->firstOrFail();

            if ($request->status === 'success') {
                $invoice->createPaymentRecord($request->amount, 'easebuzz', $request->easepayid);

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

    /** Easebuzz redirect after an invoice payment. Hash-verified; never logs anyone in. */
    public function invoiceSuccess(Request $request)
    {
        try {
            $parts = explode('_', (string) $request->input('txnid'));
            $invoice = (count($parts) >= 3 && $parts[0] === 'invoice') ? \App\Models\Invoice::find($parts[1]) : null;

            if (!$invoice) {
                return redirect()->route('home')->with('error', __('Invalid transaction'));
            }

            $recorded = $this->recordVerifiedInvoicePayment($request, $invoice);

            return redirect()->route('invoices.payment', $invoice->payment_token)->with(
                $recorded ? 'success' : 'error',
                $recorded ? __('Payment completed successfully!') : __('Payment verification failed')
            );
        } catch (\Exception $e) {
            return redirect()->route('home')->with('error', __('Payment processing failed'));
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
            $invoice = \App\Models\Invoice::where('payment_token', $token)->firstOrFail();
            $recorded = $this->recordVerifiedInvoicePayment($request, $invoice);

            return redirect()->route('invoices.payment', $token)->with(
                $recorded ? 'success' : 'error',
                $recorded ? __('Payment processed successfully.') : __('Payment verification failed')
            );
        } catch (\Exception $e) {
            return redirect()->route('invoices.payment', $token)
                ->with('error', __('Payment processing failed'));
        }
    }


    /**
     * Activate the pending plan order named by txnid when the response hash checks
     * out against the platform's Easebuzz salt and the paid amount covers the order.
     */
    private function completeVerifiedPlanPayment(Request $request): bool
    {
        try {
            $settings = platformPaymentSettings();

            if (!$this->hasValidResponseHash($request, $settings) || $request->input('status') !== 'success') {
                return false;
            }

            $order = PlanOrder::where('payment_id', (string) $request->input('txnid'))
                ->where('payment_method', 'easebuzz')
                ->first();

            return $order
                && completePlanOrderPayment($order, (float) $request->input('amount'), $request->input('easepayid'));
        } catch (\Exception $e) {
            \Log::error('Easebuzz plan payment verification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /** Record a hash-verified Easebuzz invoice payment as completed. */
    private function recordVerifiedInvoicePayment(Request $request, \App\Models\Invoice $invoice): bool
    {
        $settings = \App\Models\PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();

        if (!$this->hasValidResponseHash($request, $settings) || $request->input('status') !== 'success') {
            return false;
        }

        $amount = (float) $request->input('amount');
        if ($amount <= 0 || $amount > (float) $invoice->remaining_amount + 0.009) {
            return false;
        }

        $invoice->createPaymentRecord($amount, 'easebuzz', (string) $request->input('easepayid'), verified: true);

        return true;
    }

    /**
     * Easebuzz response ("reverse") hash:
     * sha512(salt|status|udf10|udf9|...|udf1|email|firstname|productinfo|amount|txnid|key)
     */
    private function hasValidResponseHash(Request $request, array $settings): bool
    {
        $key = $settings['easebuzz_merchant_key'] ?? null;
        $salt = $settings['easebuzz_salt_key'] ?? null;
        $received = strtolower((string) $request->input('hash'));

        if (!$key || !$salt || $received === '' || (string) $request->input('key') !== (string) $key) {
            return false;
        }

        $fields = ['udf10', 'udf9', 'udf8', 'udf7', 'udf6', 'udf5', 'udf4', 'udf3', 'udf2', 'udf1',
            'email', 'firstname', 'productinfo', 'amount', 'txnid', 'key'];
        $sequence = $salt . '|' . $request->input('status');
        foreach ($fields as $field) {
            $sequence .= '|' . $request->input($field, '');
        }

        return hash_equals(strtolower(hash('sha512', $sequence)), $received);
    }
}
