<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanOrder;
use App\Models\Invoice;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class XenditPaymentController extends Controller
{
    public function createPayment(Request $request)
    {
        $validated = validatePaymentRequest($request);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['xendit_api_key'])) {
                return response()->json(['error' => __('Xendit not configured')], 400);
            }

            $user = auth()->user();
            $externalId = 'plan_' . $plan->id . '_' . $user->id . '_' . time();

            $invoiceData = [
                'external_id' => $externalId,
                'amount' => $pricing['final_price'],
                'description' => 'Plan Subscription: ' . $plan->name,
                'invoice_duration' => 86400,
                'currency' => 'PHP',
                'customer' => [
                    'given_names' => $user->name ?? 'Customer',
                    'email' => $user->email
                ],
                'success_redirect_url' => route('xendit.success', [
                    'plan_id' => $plan->id,
                    'user_id' => $user->id,
                    'billing_cycle' => $validated['billing_cycle'],
                    'coupon_code' => $validated['coupon_code'] ?? ''
                ]),
                'failure_redirect_url' => route('plans.index')
            ];

            $response = \Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($settings['payment_settings']['xendit_api_key'] . ':'),
                'Content-Type' => 'application/json'
            ])->post('https://api.xendit.co/v2/invoices', $invoiceData);

            if ($response->successful()) {
                $result = $response->json();
                if (isset($result['invoice_url'])) {
                    return response()->json([
                        'success' => true,
                        'payment_url' => $result['invoice_url'],
                        'external_id' => $externalId
                    ]);
                }
            }

            return response()->json(['error' => $response->body()], 500);

        } catch (\Exception $e) {
            return response()->json(['error' => __('Payment creation failed')], 500);
        }
    }

    public function success(Request $request)
    {
        try {
            $planId = $request->input('plan_id');
            $userId = $request->input('user_id');
            $billingCycle = $request->input('billing_cycle', 'monthly');
            $couponCode = $request->input('coupon_code');
            
            if ($planId && $userId) {
                $plan = Plan::find($planId);
                $user = \App\Models\User::find($userId);
                
                if ($plan && $user) {
                    processPaymentSuccess([
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'billing_cycle' => $billingCycle,
                        'payment_method' => 'xendit',
                        'coupon_code' => $couponCode,
                        'payment_id' => $request->input('external_id', 'xendit_' . time()),
                    ]);
                    
                    if (!auth()->check()) {
                        auth()->login($user);
                    }
                    
                    return redirect()->route('plans.index')->with('success', __('Payment completed successfully and plan activated'));
                }
            }
            
            return redirect()->route('plans.index')->with('error', __('Payment verification failed'));
            
        } catch (\Exception $e) {
            return redirect()->route('plans.index')->with('error', __('Payment processing failed'));
        }
    }

    public function processPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'external_id' => 'required|string',
            'customer_details' => 'required|array',
        ]);

        try {
            $settings = getPaymentMethodConfig('xendit');
            
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            
            createPlanOrder([
                'user_id' => auth()->id(),
                'plan_id' => $validated['plan_id'],
                'billing_cycle' => $validated['billing_cycle'],
                'payment_method' => 'xendit',
                'coupon_code' => $validated['coupon_code'] ?? null,
                'payment_id' => $validated['external_id'],
                'status' => 'pending'
            ]);
            
            $invoiceData = [
                'external_id' => $validated['external_id'],
                'amount' => $pricing['final_price'],
                'description' => 'Plan Subscription - ' . $plan->name,
                'invoice_duration' => 86400,
                'customer' => [
                    'given_names' => $validated['customer_details']['firstName'],
                    'surname' => $validated['customer_details']['lastName'],
                    'email' => $validated['customer_details']['email']
                ],
                'customer_notification_preference' => [
                    'invoice_created' => ['email'],
                    'invoice_reminder' => ['email'],
                    'invoice_paid' => ['email']
                ],
                'success_redirect_url' => route('plans.index'),
                'failure_redirect_url' => route('plans.index')
            ];
            
            $response = \Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($settings['secret_key'] . ':'),
                'Content-Type' => 'application/json'
            ])->post('https://api.xendit.co/v2/invoices', $invoiceData);
            
            if ($response->successful()) {
                $result = $response->json();
                if (isset($result['invoice_url'])) {
                    return redirect($result['invoice_url']);
                }
            }
            
            processPaymentSuccess([
                'user_id' => auth()->id(),
                'plan_id' => $validated['plan_id'],
                'billing_cycle' => $validated['billing_cycle'],
                'payment_method' => 'xendit',
                'coupon_code' => $validated['coupon_code'] ?? null,
                'payment_id' => $validated['external_id'],
            ]);
            
            return redirect()->route('plans.index')->with('success', 'Xendit payment completed (Demo)');
        } catch (\Exception $e) {
            return handlePaymentError($e, 'xendit');
        }
    }
    
    public function callback(Request $request)
    {
        $externalId = $request->input('external_id');
        $status = $request->input('status');
        
        if ($status === 'PAID') {
            $planOrder = PlanOrder::where('payment_id', $externalId)->first();
            
            if ($planOrder && $planOrder->status === 'pending') {
                $planOrder->update(['status' => 'approved']);
                $planOrder->activateSubscription();
            }
        }
        
        return response('OK', 200);
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
            
            if (!isset($settings['xendit_api_key'])) {
                return response()->json(['error' => __('Xendit not configured')], 400);
            }

            $externalId = 'invoice_' . $invoice->id . '_' . time();

            $invoiceData = [
                'external_id' => $externalId,
                'amount' => $request->amount,
                'description' => 'Invoice #' . $invoice->invoice_number . ' Payment',
                'invoice_duration' => 86400,
                'currency' => 'PHP',
                'customer' => [
                    'given_names' => 'Customer',
                    'email' => 'customer@example.com'
                ],
                'success_redirect_url' => route('xendit.invoice.success', [
                    'invoice_id' => $invoice->id,
                    'amount' => $request->amount
                ]),
                'failure_redirect_url' => route('invoices.show', $invoice->id)
            ];

            $response = \Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($settings['xendit_api_key'] . ':'),
                'Content-Type' => 'application/json'
            ])->post('https://api.xendit.co/v2/invoices', $invoiceData);

            if ($response->successful()) {
                $result = $response->json();
                if (isset($result['invoice_url'])) {
                    return response()->json([
                        'success' => true,
                        'redirect_url' => $result['invoice_url']
                    ]);
                }
            }
            
            return response()->json(['error' => __('Payment creation failed')], 500);

        } catch (\Exception $e) {
            return response()->json(['error' => __('Payment creation failed')], 500);
        }
    }

    public function processInvoicePayment(Request $request)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'external_id' => 'required|string',
            ]);
            
            $invoice = Invoice::where('payment_token', $request->route('token'))->firstOrFail();
            $settings = PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            
            if (!isset($settings['is_xendit_enabled']) || $settings['is_xendit_enabled'] !== '1') {
                return back()->withErrors(['error' => 'Xendit not enabled']);
            }

            // For demo purposes, simulate successful payment
            $invoice->createPaymentRecord(
                $request->amount,
                'xendit',
                $request->external_id
            );
            
            return redirect()->route('invoices.show', $invoice->id)
                ->with('success', 'Xendit payment processed successfully.');
            
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
            $externalId = $request->input('external_id');
            
            if ($invoiceId && $amount) {
                $invoice = Invoice::find($invoiceId);
                
                if ($invoice) {
                    $invoice->createPaymentRecord(
                        $amount,
                        'xendit',
                        $externalId ?: 'xendit_' . time()
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

    public function processInvoicePaymentFromLink(Request $request, $token)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01'
            ]);
            
            $invoice = Invoice::where('payment_token', $token)->firstOrFail();
            $settings = PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            
            if (!isset($settings['is_xendit_enabled']) || $settings['is_xendit_enabled'] !== '1') {
                return response()->json(['error' => 'Xendit not enabled'], 400);
            }

            if (!isset($settings['xendit_api_key'])) {
                return response()->json(['error' => 'Xendit not configured'], 400);
            }

            $externalId = 'invoice_' . $invoice->id . '_' . time();

            $invoiceData = [
                'external_id' => $externalId,
                'amount' => $request->amount,
                'description' => 'Invoice #' . $invoice->invoice_number . ' Payment',
                'invoice_duration' => 86400,
                'currency' => 'PHP',
                'customer' => [
                    'given_names' => 'Customer',
                    'email' => 'customer@example.com'
                ],
                'success_redirect_url' => route('xendit.invoice.success.link', [
                    'token' => $token,
                    'amount' => $request->amount
                ]),
                'failure_redirect_url' => route('invoices.payment', $token)
            ];

            $response = \Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($settings['xendit_api_key'] . ':'),
                'Content-Type' => 'application/json'
            ])->post('https://api.xendit.co/v2/invoices', $invoiceData);

            if ($response->successful()) {
                $result = $response->json();
                if (isset($result['invoice_url'])) {
                    return response()->json([
                        'success' => true,
                        'redirect_url' => $result['invoice_url']
                    ]);
                }
            }
            
            return response()->json(['error' => 'Payment creation failed'], 500);
            
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
            $externalId = $request->input('external_id');
            
            if ($amount) {
                $invoice = Invoice::where('payment_token', $token)->firstOrFail();
                
                $invoice->createPaymentRecord(
                    $amount,
                    'xendit',
                    $externalId ?: 'xendit_' . time()
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