<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Invoice;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;

class KhaltiPaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'token' => 'required|string',
            'amount' => 'required|numeric',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['khalti_secret_key'])) {
                return back()->withErrors(['error' => __('Khalti not configured')]);
            }

            // Verify payment with Khalti API
            $isValid = $this->verifyKhaltiPayment($validated['token'], $validated['amount'], $settings['payment_settings']);

            if ($isValid) {
                processPaymentSuccess([
                    'user_id' => auth()->id(),
                    'plan_id' => $plan->id,
                    'billing_cycle' => $validated['billing_cycle'],
                    'payment_method' => 'khalti',
                    'coupon_code' => $validated['coupon_code'] ?? null,
                    'payment_id' => $validated['token'],
                ]);

                return back()->with('success', __('Payment successful and plan activated'));
            }

            return back()->withErrors(['error' => __('Payment verification failed')]);

        } catch (\Exception $e) {
            return handlePaymentError($e, 'khalti');
        }
    }

    public function createPayment(Request $request)
    {
        $validated = validatePaymentRequest($request);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['khalti_public_key'])) {
                return response()->json(['error' => __('Khalti not configured')], 400);
            }

            return response()->json([
                'success' => true,
                'public_key' => $settings['payment_settings']['khalti_public_key'],
                'amount' => $pricing['final_price'] * 100, // Khalti uses paisa
                'product_identity' => 'plan_' . $plan->id,
                'product_name' => $plan->name,
                'product_url' => route('plans.index'),
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => __('Payment creation failed')], 500);
        }
    }

    private function verifyKhaltiPayment($token, $amount, $settings)
    {
        try {
            $url = 'https://khalti.com/api/v2/payment/verify/';
            
            $data = [
                'token' => $token,
                'amount' => $amount * 100, // Convert to paisa
            ];

            $headers = [
                'Authorization: Key ' . $settings['khalti_secret_key'],
                'Content-Type: application/json',
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            curl_close($ch);

            $result = json_decode($response, true);
            
            return isset($result['state']['name']) && $result['state']['name'] === 'Completed';

        } catch (\Exception $e) {
            return false;
        }
    }

    public function processInvoicePayment(Request $request)
    {
        $request->validate([
            'invoice_token' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'token' => 'required|string'
        ]);

        try {
            $invoice = Invoice::where('payment_token', $request->invoice_token)->firstOrFail();
            
            $settings = PaymentSetting::where('user_id', $invoice->created_by)
                ->pluck('value', 'key')
                ->toArray();

            if (!isset($settings['khalti_secret_key'])) {
                return back()->withErrors(['error' => __('Khalti not configured')]);
            }

            $isValid = $this->verifyKhaltiPayment($request->token, $request->amount, $settings);

            if ($isValid) {
                $invoice->createPaymentRecord($request->amount, 'khalti', $request->token);

                return redirect()->route('invoices.show', $invoice->id)
                    ->with('success', __('Payment completed successfully!'));
            }

            return back()->withErrors(['error' => __('Payment verification failed')]);

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

            $settings = PaymentSetting::where('user_id', $invoice->created_by)
                ->pluck('value', 'key')
                ->toArray();

            if (!isset($settings['khalti_public_key']) || $settings['is_khalti_enabled'] !== '1') {
                return response()->json(['error' => 'Khalti payment not configured'], 400);
            }

            return response()->json([
                'success' => true,
                'public_key' => $settings['khalti_public_key'],
                'amount' => (int)($request->amount * 100), // Convert to paisa
                'product_identity' => 'invoice_' . $invoice->id,
                'product_name' => 'Invoice Payment - ' . $invoice->invoice_number,
                'product_url' => route('invoices.payment', $invoice->payment_token),
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function processInvoicePaymentFromLink(Request $request, $token)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01'
            ]);
            
            $invoice = Invoice::where('payment_token', $token)->firstOrFail();
            
            $settings = PaymentSetting::where('user_id', $invoice->created_by)
                ->pluck('value', 'key')
                ->toArray();

            if (!isset($settings['khalti_public_key']) || $settings['is_khalti_enabled'] !== '1') {
                return response()->json(['error' => 'Khalti payment not configured'], 400);
            }

            return response()->json([
                'success' => true,
                'public_key' => $settings['khalti_public_key'],
                'amount' => (int)($request->amount * 100),
                'product_identity' => 'invoice_' . $invoice->id,
                'product_name' => 'Invoice Payment - ' . $invoice->invoice_number,
                'product_url' => route('invoices.payment', $invoice->payment_token),
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function invoiceSuccessFromLink(Request $request, $token)
    {
        try {
            $khaltiToken = $request->input('token');
            $amount = $request->input('amount');
            
            $invoice = Invoice::where('payment_token', $token)->firstOrFail();

            if ($khaltiToken && $amount) {
                $settings = PaymentSetting::where('user_id', $invoice->created_by)
                    ->pluck('value', 'key')
                    ->toArray();

                $isValid = $this->verifyKhaltiPayment($khaltiToken, $amount / 100, $settings);

                if ($isValid) {
                    $invoice->createPaymentRecord($amount / 100, 'khalti', $khaltiToken);

                    return redirect()->route('invoices.payment', $token)
                        ->with('success', 'Payment processed successfully.');
                }
            }

            return redirect()->route('invoices.payment', $token)
                ->with('error', 'Payment verification failed');
        } catch (\Exception $e) {
            return redirect()->route('invoices.payment', $token)
                ->with('error', 'Payment processing failed');
        }
    }
}