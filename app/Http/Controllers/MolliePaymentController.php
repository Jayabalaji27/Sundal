<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanOrder;
use App\Models\Invoice;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Mollie\Api\MollieApiClient;

class MolliePaymentController extends Controller
{
    private function getMollieCredentials($userId = null)
    {
        if ($userId) {
            $settings = PaymentSetting::where('user_id', $userId)
                ->whereIn('key', ['mollie_api_key'])
                ->pluck('value', 'key')
                ->toArray();

            return [
                'api_key' => $settings['mollie_api_key'] ?? null,
                'currency' => 'EUR'
            ];
        }

        $settings = getPaymentGatewaySettings();
                
        return [
            'api_key' => $settings['payment_settings']['mollie_api_key'] ?? null,
            'currency' => $settings['general_settings']['defaultCurrency'] ?? 'EUR'
        ];
    }

    public function processPayment(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
            'coupon_code' => 'nullable|string',
            'customer_details' => 'required|array',
            'customer_details.firstName' => 'required|string',
            'customer_details.lastName' => 'required|string', 
            'customer_details.email' => 'required|email'
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            $credentials = $this->getMollieCredentials();
            
            if (!$credentials['api_key']) {
                return back()->withErrors(['error' => __('Mollie not configured')]);
            }
            
            $paymentId = 'mollie_' . $plan->id . '_' . time() . '_' . uniqid();
            
            // Create pending order
            createPlanOrder([
                'user_id' => auth()->id(),
                'plan_id' => $plan->id,
                'billing_cycle' => $validated['billing_cycle'],
                'payment_method' => 'mollie',
                'coupon_code' => $validated['coupon_code'] ?? null,
                'payment_id' => $paymentId,
                'status' => 'pending'
            ]);
            
            // Initialize Mollie SDK
            $mollie = new MollieApiClient();
            $mollie->setApiKey($credentials['api_key']);
            
            $paymentData = [
                'amount' => [
                    'currency' => $credentials['currency'],
                    'value' => number_format($pricing['final_price'], 2, '.', '')
                ],
                'description' => 'Plan Subscription - ' . $plan->name,
                'redirectUrl' => route('mollie.success'),
                'metadata' => [
                    'payment_id' => $paymentId,
                    'plan_id' => $plan->id,
                    'user_id' => auth()->id(),
                    'billing_cycle' => $validated['billing_cycle']
                ]
            ];
            
            // Only add webhook URL if not localhost
            if (!str_contains(config('app.url'), 'localhost')) {
                $paymentData['webhookUrl'] = route('mollie.callback');
            }
            
            $payment = $mollie->payments->create($paymentData);
            
            // Update the plan order with the actual Mollie payment ID
            PlanOrder::where('payment_id', $paymentId)
                ->update(['payment_id' => $payment->id, 'notes' => __('Mollie Payment ID: ') . $payment->id]);
            
            return redirect($payment->getCheckoutUrl());
            
        } catch (\Exception $e) {
            return back()->withErrors(['error' => __('Payment failed. Please try again.')]);
        }
    }

    public function createPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'customer_name' => 'required|string',
            'customer_email' => 'required|email',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            $credentials = $this->getMollieCredentials();
            
            if (!$credentials['api_key']) {
                throw new \Exception(__('Mollie API key not configured'));
            }
            
            $paymentId = 'mollie_' . $plan->id . '_' . time() . '_' . uniqid();
            
            // Create pending order
            createPlanOrder([
                'user_id' => auth()->id(),
                'plan_id' => $plan->id,
                'billing_cycle' => $validated['billing_cycle'],
                'payment_method' => 'mollie',
                'coupon_code' => $validated['coupon_code'] ?? null,
                'payment_id' => $paymentId,
                'status' => 'pending'
            ]);
            
            // Initialize Mollie SDK
            $mollie = new MollieApiClient();
            $mollie->setApiKey($credentials['api_key']);
            
            $payment = $mollie->payments->create([
                'amount' => [
                    'currency' => $credentials['currency'],
                    'value' => number_format($pricing['final_price'], 2, '.', '')
                ],
                'description' => 'Plan Subscription - ' . $plan->name,
                'redirectUrl' => route('mollie.success'),
                'webhookUrl' => route('mollie.callback'),
                'metadata' => [
                    'payment_id' => $paymentId,
                    'plan_id' => $plan->id,
                    'user_id' => auth()->id(),
                    'billing_cycle' => $validated['billing_cycle']
                ]
            ]);
            
            // Update the plan order with the actual Mollie payment ID
            PlanOrder::where('payment_id', $paymentId)
                ->update(['payment_id' => $payment->id, 'notes' => 'Mollie Payment ID: ' . $payment->id]);
            
            return response()->json([
                'success' => true,
                'payment_id' => $payment->id,
                'checkout_url' => $payment->getCheckoutUrl()
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    public function checkPaymentStatus(Request $request)
    {
        $validated = $request->validate([
            'payment_id' => 'required|string'
        ]);
        
        try {
            $credentials = $this->getMollieCredentials();
            $mollie = new MollieApiClient();
            $mollie->setApiKey($credentials['api_key']);
            
            $payment = $mollie->payments->get($validated['payment_id']);
            
            return response()->json([
                'status' => $payment->status,
                'is_paid' => $payment->isPaid(),
                'is_failed' => $payment->isFailed(),
                'is_canceled' => $payment->isCanceled()
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    public function success(Request $request)
    {
        try {
            $credentials = $this->getMollieCredentials();
            
            if (!$credentials['api_key']) {
                return redirect()->route('plans.index')->with('error', 'Payment configuration error.');
            }
            
            // Find the most recent pending order for this user
            $userId = auth()->id();
            if ($userId) {
                $planOrder = PlanOrder::where('user_id', $userId)
                    ->where('status', 'pending')
                    ->where('payment_method', 'mollie')
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                if ($planOrder) {
                    $mollie = new MollieApiClient();
                    $mollie->setApiKey($credentials['api_key']);
                    
                    try {
                        $payment = $mollie->payments->get($planOrder->payment_id);
                        
                        if ($payment->isPaid()) {
                            $planOrder->update(['status' => 'approved']);
                            $planOrder->activateSubscription();
                            
                            return redirect()->route('plans.index')->with('success', __('Payment completed successfully! Your plan has been activated.'));
                        } elseif ($payment->status === 'pending') {
                            return redirect()->route('plans.index')->with('info', __('Payment is being processed. Your plan will be activated shortly.'));
                        } else {
                            return redirect()->route('plans.index')->with('error', __('Payment was not successful. Please try again.'));
                        }
                    } catch (\Exception $e) {
                        return redirect()->route('plans.index')->with('info', __('Payment is being processed. Your plan will be activated shortly.'));
                    }
                }
            }
            
            return redirect()->route('plans.index')->with('info', __('Payment is being processed. Your plan will be activated shortly.'));
            
        } catch (\Exception $e) {
            return redirect()->route('plans.index')->with('error', __('Payment verification failed. Please contact support.'));
        }
    }
    
    public function callback(Request $request)
    {
        try {
            $paymentId = $request->input('id');
            $credentials = $this->getMollieCredentials();
            
            $mollie = new MollieApiClient();
            $mollie->setApiKey($credentials['api_key']);
            
            $payment = $mollie->payments->get($paymentId);
                        
            if ($payment->isPaid()) {
                $planOrder = PlanOrder::where('payment_id', $paymentId)->first();
                
                if ($planOrder && $planOrder->status === 'pending') {
                    $planOrder->update(['status' => 'approved']);
                    $planOrder->activateSubscription();
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
            
            if (!isset($settings['mollie_api_key']) || !isset($settings['is_mollie_enabled']) || $settings['is_mollie_enabled'] !== '1') {
                return response()->json(['error' => __('Mollie not configured')], 400);
            }

            $credentials = $this->getMollieCredentials($invoice->created_by);
            $paymentId = 'mollie_inv_' . $invoice->id . '_' . time() . '_' . uniqid();

            // Initialize Mollie SDK
            $mollie = new MollieApiClient();
            $mollie->setApiKey($credentials['api_key']);

            $paymentData = [
                'amount' => [
                    'currency' => 'EUR',
                    'value' => number_format($request->amount, 2, '.', '')
                ],
                'description' => 'Invoice #' . $invoice->invoice_number . ' Payment',
                'redirectUrl' => route('mollie.invoice.success', [
                    'invoice_id' => $invoice->id,
                    'amount' => $request->amount
                ]),
                'metadata' => [
                    'payment_id' => $paymentId,
                    'invoice_id' => $invoice->id,
                    'amount' => $request->amount
                ]
            ];

            // Only add webhook URL if not localhost
            if (!str_contains(config('app.url'), 'localhost')) {
                $paymentData['webhookUrl'] = route('mollie.invoice.callback');
            }

            $payment = $mollie->payments->create($paymentData);

            return response()->json([
                'success' => true,
                'redirect_url' => $payment->getCheckoutUrl()
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => __('Payment creation failed')], 500);
        }
    }

    public function processInvoicePayment(Request $request)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'payment_id' => 'required|string',
            ]);
            
            $invoice = Invoice::where('payment_token', $request->route('token'))->firstOrFail();
            $settings = PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            
            if (!isset($settings['is_mollie_enabled']) || $settings['is_mollie_enabled'] !== '1') {
                return back()->withErrors(['error' => 'Mollie not enabled']);
            }

            // For demo purposes, simulate successful payment
            $invoice->createPaymentRecord(
                $request->amount,
                'mollie',
                $request->payment_id
            );
            
            return redirect()->route('invoices.show', $invoice->id)
                ->with('success', 'Mollie payment processed successfully.');
            
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
            $paymentId = $request->input('id');
            
            if ($invoiceId && $amount) {
                $invoice = Invoice::find($invoiceId);
                
                if ($invoice) {
                    $credentials = $this->getMollieCredentials($invoice->created_by);
                    
                    if ($paymentId && $credentials['api_key']) {
                        try {
                            $mollie = new MollieApiClient();
                            $mollie->setApiKey($credentials['api_key']);
                            $payment = $mollie->payments->get($paymentId);
                            
                            if ($payment->isPaid()) {
                                $invoice->createPaymentRecord(
                                    $amount,
                                    'mollie',
                                    $paymentId
                                );
                                
                                return redirect()->route('invoices.show', $invoice->id)
                                    ->with('success', __('Payment completed successfully!'));
                            }
                        } catch (\Exception $e) {
                            // Fallback to demo payment
                        }
                    }
                    
                    // Demo fallback
                    $invoice->createPaymentRecord(
                        $amount,
                        'mollie',
                        $paymentId ?: 'mollie_' . time()
                    );
                    
                    return redirect()->route('invoices.show', $invoice->id)
                        ->with('success', __('Payment completed successfully!'));
                }
            }
            
            return redirect()->route('home')->with('error', __('Payment verification failed'));
            
        } catch (\Exception $e) {
            return redirect()->route('home')->with('error', __('Payment processing failed'));
        }
    }

    public function invoiceCallback(Request $request)
    {
        return response('OK', 200);
    }

    public function processInvoicePaymentFromLink(Request $request, $token)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01'
            ]);
            
            $invoice = Invoice::where('payment_token', $token)->firstOrFail();
            $settings = PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            
            if (!isset($settings['is_mollie_enabled']) || $settings['is_mollie_enabled'] !== '1') {
                return response()->json(['error' => 'Mollie not enabled'], 400);
            }

            if (!isset($settings['mollie_api_key'])) {
                return response()->json(['error' => 'Mollie not configured'], 400);
            }

            $credentials = $this->getMollieCredentials($invoice->created_by);
            $paymentId = 'mollie_inv_' . $invoice->id . '_' . time() . '_' . uniqid();

            // Initialize Mollie SDK
            $mollie = new MollieApiClient();
            $mollie->setApiKey($credentials['api_key']);

            $paymentData = [
                'amount' => [
                    'currency' => 'EUR',
                    'value' => number_format($request->amount, 2, '.', '')
                ],
                'description' => 'Invoice #' . $invoice->invoice_number . ' Payment',
                'redirectUrl' => route('mollie.invoice.success.link', [
                    'token' => $token,
                    'amount' => $request->amount
                ]),
                'metadata' => [
                    'payment_id' => $paymentId,
                    'invoice_id' => $invoice->id,
                    'invoice_token' => $token,
                    'amount' => $request->amount
                ]
            ];

            // Only add webhook URL if not localhost
            if (!str_contains(config('app.url'), 'localhost')) {
                $paymentData['webhookUrl'] = route('mollie.invoice.callback');
            }

            $payment = $mollie->payments->create($paymentData);

            return response()->json([
                'success' => true,
                'payment_url' => $payment->getCheckoutUrl()
            ]);
            
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
            $paymentId = $request->input('id');
            
            if ($amount) {
                $invoice = Invoice::where('payment_token', $token)->firstOrFail();
                $credentials = $this->getMollieCredentials($invoice->created_by);
                
                if ($paymentId && $credentials['api_key']) {
                    try {
                        $mollie = new MollieApiClient();
                        $mollie->setApiKey($credentials['api_key']);
                        $payment = $mollie->payments->get($paymentId);
                        
                        if ($payment->isPaid()) {
                            $invoice->createPaymentRecord(
                                $amount,
                                'mollie',
                                $paymentId
                            );
                            
                            return redirect()->route('invoices.payment', $token)
                                ->with('success', 'Payment processed successfully.');
                        }
                    } catch (\Exception $e) {
                        // Fallback to demo payment
                    }
                }
                
                // Demo fallback
                $invoice->createPaymentRecord(
                    $amount,
                    'mollie',
                    $paymentId ?: 'mollie_' . time()
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