<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanOrder;
use App\Models\PaymentSetting;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;

class RazorpayController extends Controller
{
    /**
     * Get Razorpay API credentials
     * 
     * @return array
     */
    private function getRazorpayCredentials()
    {
        $settings = getPaymentGatewaySettings();
                        
        return [
            'key' => $settings['payment_settings']['razorpay_key'] ?? null,
            'secret' => $settings['payment_settings']['razorpay_secret'] ?? null,
            'currency' => $settings['general_settings']['defaultCurrency'] ?? 'INR'
        ];
    }

    /**
     * Create a Razorpay order
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createOrder(Request $request)
    {
        $validated = validatePaymentRequest($request);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            
            $amountInSmallestUnit = $pricing['final_price'] * 100;
            
            // Get Razorpay credentials
            $credentials = $this->getRazorpayCredentials();
            
            if (!$credentials['key'] || !$credentials['secret']) {
                throw new \Exception(__('Razorpay API credentials not found'));
            }
            
            $api = new Api($credentials['key'], $credentials['secret']);
            
            $orderData = [
                'receipt' => 'plan_' . $plan->id . '_' . time(),
                'amount' => (int)$amountInSmallestUnit,
                'currency' => $credentials['currency'],
                'notes' => [
                    'plan_id' => $plan->id,
                    'billing_cycle' => $request->billing_cycle,
                ]
            ];
            
            $razorpayOrder = $api->order->create($orderData);
            
            return response()->json([
                'order_id' => $razorpayOrder->id,
                'amount' => (int)$amountInSmallestUnit,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => __('Failed to create payment order: ') . $e->getMessage()], 500);
        }
    }
    
    /**
     * Verify Razorpay payment
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'razorpay_payment_id' => 'required|string',
            'razorpay_order_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        try {
            $credentials = $this->getRazorpayCredentials();
            
            if (!$credentials['key'] || !$credentials['secret']) {
                throw new \Exception(__('Razorpay API credentials not found'));
            }
            
            $api = new Api($credentials['key'], $credentials['secret']);
            $api->utility->verifyPaymentSignature([
                'razorpay_order_id' => $validated['razorpay_order_id'],
                'razorpay_payment_id' => $validated['razorpay_payment_id'],
                'razorpay_signature' => $validated['razorpay_signature']
            ]);
            
            processPaymentSuccess([
                'user_id' => auth()->id(),
                'plan_id' => $validated['plan_id'],
                'billing_cycle' => $validated['billing_cycle'],
                'payment_method' => 'razorpay',
                'coupon_code' => $validated['coupon_code'] ?? null,
                'payment_id' => $validated['razorpay_payment_id'],
            ]);
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => __('Payment verification failed: ') . $e->getMessage()], 500);
        }
    }

    public function processInvoicePayment(Request $request)
    {
        try {
            $request->validate([
                'invoice_id' => 'required|exists:invoices,id',
                'amount' => 'required|numeric|min:0.01',
                'razorpay_payment_id' => 'required|string',
                'razorpay_order_id' => 'nullable|string',
                'razorpay_signature' => 'nullable|string',
            ]);
            
            $invoice = Invoice::findOrFail($request->invoice_id);
            $settings = PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            
            if (!isset($settings['is_razorpay_enabled']) || $settings['is_razorpay_enabled'] !== '1') {
                return back()->withErrors(['error' => 'Razorpay not enabled']);
            }

            $invoice->createPaymentRecord(
                $request->amount,
                'razorpay',
                $request->razorpay_payment_id
            );
            
            return redirect()->route('invoices.show', $invoice->id)
                ->with('success', 'Payment processed successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Payment processing failed. Please try again or contact support.']);
        }
    }

    public function createInvoiceOrder(Request $request)
    {
        $request->validate([
            'invoice_token' => 'required|string',
            'amount' => 'required|numeric|min:0.01'
        ]);

        try {
            $invoice = Invoice::where('payment_token', $request->invoice_token)->firstOrFail();
            $settings = PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();

            if (!isset($settings['is_razorpay_enabled']) || $settings['is_razorpay_enabled'] !== '1') {
                return response()->json(['error' => 'Razorpay payment not configured'], 400);
            }

            if (empty($settings['razorpay_key']) || empty($settings['razorpay_secret'])) {
                return response()->json(['error' => 'Razorpay credentials not found'], 400);
            }

            $api = new Api($settings['razorpay_key'], $settings['razorpay_secret']);

            $orderData = [
                'receipt' => 'invoice_' . $invoice->id . '_' . time(),
                'amount' => (int)($request->amount * 100),
                'currency' => 'INR',
                'notes' => [
                    'invoice_id' => $invoice->id,
                    'invoice_token' => $request->invoice_token,
                ]
            ];

            $razorpayOrder = $api->order->create($orderData);

            return response()->json([
                'order_id' => $razorpayOrder->id,
                'amount' => (int)($request->amount * 100),
                'key' => $settings['razorpay_key']
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function processInvoicePaymentFromLink(Request $request, $token)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'razorpay_payment_id' => 'required|string',
                'razorpay_order_id' => 'nullable|string',
            ]);
            
            $invoice = Invoice::where('payment_token', $token)->firstOrFail();
            
            Payment::create([
                'invoice_id' => $invoice->id,
                'amount' => $request->amount,
                'payment_method' => 'razorpay',
                'payment_date' => now(),
                'created_by' => $invoice->created_by
            ]);
            
            return redirect()->route('invoices.payment', $invoice->payment_token)
                ->with('success', 'Payment processed successfully.');
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return back()->withErrors(['error' => 'Invoice not found. Please check the link and try again.']);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Payment processing failed. Please try again or contact support.']);
        }
    }
}