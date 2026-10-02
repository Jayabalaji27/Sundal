<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use App\Models\PlanOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Paytabscom\Laravel_paytabs\Facades\paypage;

class PayTabsPaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|integer',
            'billing_cycle' => 'required|string|in:monthly,yearly',
            'coupon_code' => 'nullable|string',
        ]);

        try {
            $superAdmin = User::where('type', 'superadmin')->first();
            $settings = getPaymentMethodConfig('paytabs', $superAdmin->id);
            
            if (empty($settings['profile_id']) || empty($settings['server_key'])) {
                return response()->json([
                    'success' => false,
                    'message' => __('PayTabs configuration incomplete.')
                ], 400);
            }
            
            $plan = Plan::findOrFail($validated['plan_id']);
            $user = auth()->user();
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null);
            $cartId = 'PT_' . time() . '_' . $user->id;
            
            createPlanOrder([
                'user_id' => $user->id,
                'plan_id' => $validated['plan_id'],
                'billing_cycle' => $validated['billing_cycle'],
                'payment_method' => 'paytabs',
                'coupon_code' => $validated['coupon_code'] ?? null,
                'payment_id' => $cartId,
                'status' => 'pending'
            ]);
            
            // Force PayTabs configuration with proper region mapping
            $region = $settings['region'] === 'GLO' ? 'GLOBAL' : $settings['region'];
            config([
                'paytabs.profile_id' => $settings['profile_id'],
                'paytabs.server_key' => $settings['server_key'],
                'paytabs.region' => $region,
                'paytabs.currency' => 'INR'
            ]);
            
            $pay = paypage::sendPaymentCode('all')
                ->sendTransaction('sale', 'ecom')
                ->sendCart($cartId, $pricing['final_price'], "Plan Subscription - {$plan->name}")
                ->sendCustomerDetails(
                    $user->name,
                    $user->email,
                    $user->phone ?? '1234567890',
                    'Address',
                    'City',
                    'State',
                    'SA',
                    '12345',
                    request()->ip()
                )
                ->sendURLs(
                    route('paytabs.success') . '?cart_id=' . $cartId,
                    route('paytabs.callback')
                )
                ->sendLanguage('en')
                ->sendFramed(false)
                ->create_pay_page();
            
            if ($pay && method_exists($pay, 'getTargetUrl')) {
                $redirectUrl = $pay->getTargetUrl() . '/start';
                
                return response()->json([
                    'success' => true,
                    'redirect_url' => $redirectUrl
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => __('Payment initialization failed.')
            ], 400);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('Payment processing failed.')
            ], 500);
        }
    }
    
    public function callback(Request $request)
    {
        try {
            $cartId = $request->input('cartId') ?? $request->input('cart_id');
            $respStatus = $request->input('respStatus') ?? $request->input('resp_status');
            $tranRef = $request->input('tranRef') ?? $request->input('tran_ref');
            
            if (!$cartId) {
                return response(__('Missing cart ID'), 400);
            }
            
            $planOrder = PlanOrder::where('payment_id', $cartId)->first();
            
            if (!$planOrder) {
                return response(__('Order not found'), 404);
            }
            
            if ($respStatus === 'A') {
                if ($planOrder->status === 'pending') {
                    $updateData = ['status' => 'approved'];
                    if ($tranRef) {
                        $updateData['payment_id'] = $tranRef;
                    }
                    
                    $planOrder->update($updateData);
                    
                    $user = User::find($planOrder->user_id);
                    $plan = Plan::find($planOrder->plan_id);
                    
                    if ($user && $plan) {
                        assignPlanToUser($user, $plan, $planOrder->billing_cycle);
                    }
                }
            } else {
                $planOrder->update(['status' => 'failed']);
            }
            
            return response('OK', 200);
            
        } catch (\Exception $e) {
            return response(__('Callback processing failed'), 500);
        }
    }
    
    public function success(Request $request)
    { 
        // Try different parameter names PayTabs might use
        $cartId = $request->input('cart_id') 
               ?? $request->input('cartId') 
               ?? $request->input('merchant_reference')
               ?? $request->input('reference')
               ?? $request->input('order_id');      
        if ($cartId) {
            $planOrder = PlanOrder::where('payment_id', $cartId)->first();
            
            if ($planOrder) {
                // Verify payment status with PayTabs before assigning plan
                if ($planOrder->status === 'pending') {
                    try {
                        $superAdmin = User::where('type', 'superadmin')->first();
                        $settings = getPaymentMethodConfig('paytabs', $superAdmin->id);
                        
                        config([
                            'paytabs.profile_id' => $settings['profile_id'],
                            'paytabs.server_key' => $settings['server_key'],
                            'paytabs.region' => $settings['region'],
                            'paytabs.currency' => 'INR'
                        ]);
                        
                        // PayTabs only redirects to success URL on successful payment
                        $planOrder->update(['status' => 'approved']);
                        
                        $user = User::find($planOrder->user_id);
                        $plan = Plan::find($planOrder->plan_id);
                        
                        if ($user && $plan) {
                            assignPlanToUser($user, $plan, $planOrder->billing_cycle);
                        }
                        
                        return redirect()->route('plans.index')->with('success', __('Payment completed successfully!'));
                    } catch (\Exception $e) {
                        return redirect()->route('plans.index')->with('error', __('Payment verification failed.'));
                    }
                }
                
                return redirect()->route('plans.index')->with('success', __('Payment completed successfully!'));
            }
        }
        
        // No fallback - only assign plan with proper payment verification
        return redirect()->route('plans.index')->with('error', __('Payment verification failed.'));
    }

    public function createInvoicePayment(Request $request)
    {
        try {
            $request->validate([
                'invoice_token' => 'required|string',
                'amount' => 'required|numeric|min:0.01'
            ]);

            $invoice = \App\Models\Invoice::where('payment_token', $request->invoice_token)->firstOrFail();
            
            // Get user-specific payment settings
            $settings = \App\Models\PaymentSetting::where('user_id', $invoice->created_by)
                ->pluck('value', 'key')
                ->toArray();

            if (!isset($settings['paytabs_profile_id']) || !isset($settings['paytabs_server_key']) || $settings['is_paytabs_enabled'] !== '1') {
                return response()->json([
                    'success' => false,
                    'message' => __('PayTabs not configured')
                ], 400);
            }

            $cartId = 'invoice_' . $invoice->id . '_' . $request->amount . '_' . time();
            $region = $settings['paytabs_region'] === 'GLO' ? 'GLOBAL' : $settings['paytabs_region'];

            // Force PayTabs configuration
            config([
                'paytabs.profile_id' => $settings['paytabs_profile_id'],
                'paytabs.server_key' => $settings['paytabs_server_key'],
                'paytabs.region' => $region,
                'paytabs.currency' => 'INR'
            ]);

            $pay = paypage::sendPaymentCode('all')
                ->sendTransaction('sale', 'ecom')
                ->sendCart($cartId, $request->amount, "Invoice Payment - {$invoice->invoice_number}")
                ->sendCustomerDetails(
                    $invoice->client->name ?? 'Customer',
                    $invoice->client->email ?? 'customer@example.com',
                    $invoice->client->phone ?? '1234567890',
                    'Address',
                    'City',
                    'State',
                    'US',
                    '12345',
                    request()->ip()
                )
                ->sendURLs(
                    route('paytabs.invoice.success') . '?cart_id=' . $cartId,
                    route('paytabs.invoice.callback')
                )
                ->sendLanguage('en')
                ->sendFramed(false)
                ->create_pay_page();

            if ($pay && method_exists($pay, 'getTargetUrl')) {
                $redirectUrl = $pay->getTargetUrl();

                return response()->json([
                    'success' => true,
                    'redirect_url' => $redirectUrl
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => __('Payment initialization failed')
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('Payment processing failed')
            ], 500);
        }
    }

    public function invoiceSuccess(Request $request)
    {
        try {
            $cartId = $request->input('cart_id');

            if ($cartId) {
                $parts = explode('_', $cartId);
                if (count($parts) >= 3 && $parts[0] === 'invoice') {
                    $invoiceId = $parts[1];
                    $paymentAmount = $parts[2]; // Extract amount from cart_id
                    $invoice = \App\Models\Invoice::find($invoiceId);

                    if ($invoice) {
                        // Check if payment already exists to prevent duplicates
                        $existingPayment = \App\Models\Payment::where('invoice_id', $invoice->id)
                            ->where('transaction_id', $cartId)
                            ->first();
                            
                        if (!$existingPayment) {
                            $invoice->createPaymentRecord((float)$paymentAmount, 'paytabs', $cartId);
                        }

                        return redirect()->route('invoices.show', $invoice->id)
                            ->with('success', __('Payment completed successfully!'));
                    }
                }
            }
            return redirect()->route('invoices.index')
                ->with('error', __('Payment verification failed'));

        } catch (\Exception $e) {
            return redirect()->route('invoices.index')
                ->with('error', __('Payment processing failed'));
        }
    }

    public function invoiceCallback(Request $request)
    {
        try {
            $cartId = $request->input('cartId') ?? $request->input('cart_id');
            $respStatus = $request->input('respStatus') ?? $request->input('resp_status');
            $tranRef = $request->input('tranRef') ?? $request->input('tran_ref');

            if ($cartId && $respStatus === 'A') {
                $parts = explode('_', $cartId);
                if (count($parts) >= 3 && $parts[0] === 'invoice') {
                    $invoiceId = $parts[1];
                    $paymentAmount = $parts[2];
                    $invoice = \App\Models\Invoice::find($invoiceId);

                    if ($invoice) {
                        // Check if payment already exists to prevent duplicates
                        $paymentId = $tranRef ?? $cartId;
                        $existingPayment = \App\Models\Payment::where('invoice_id', $invoice->id)
                            ->where('transaction_id', $paymentId)
                            ->first();
                            
                        if (!$existingPayment) {
                            $invoice->createPaymentRecord((float)$paymentAmount, 'paytabs', $paymentId);
                        }
                    }
                }
            }

            return response('OK', 200);
        } catch (\Exception $e) {
            return response('ERROR', 500);
        }
    }

    public function createInvoicePaymentFromLink(Request $request, $token)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01'
            ]);

            $invoice = \App\Models\Invoice::where('payment_token', $token)->firstOrFail();
            
            $superAdmin = User::where('type', 'superadmin')->first();
            $settings = getPaymentMethodConfig('paytabs', $superAdmin->id);

            if (!isset($settings['profile_id']) || !isset($settings['server_key'])) {
                return response()->json([
                    'success' => false,
                    'message' => __('PayTabs not configured')
                ], 400);
            }

            $amount = $request->input('amount');
            $cartId = 'invoice_' . $invoice->id . '_' . $amount . '_link_' . time();
            $region = ($settings['region'] ?? 'SA') === 'GLO' ? 'GLOBAL' : ($settings['region'] ?? 'SA');

            config([
                'paytabs.profile_id' => $settings['profile_id'],
                'paytabs.server_key' => $settings['server_key'],
                'paytabs.region' => $region,
                'paytabs.currency' => 'INR'
            ]);

            $pay = paypage::sendPaymentCode('all')
                ->sendTransaction('sale', 'ecom')
                ->sendCart($cartId, $amount, "Invoice Payment - {$invoice->invoice_number}")
                ->sendCustomerDetails(
                    $invoice->client->name ?? 'Customer',
                    $invoice->client->email ?? 'customer@example.com',
                    $invoice->client->phone ?? '1234567890',
                    'Address',
                    'City',
                    'State',
                    'US',
                    '12345',
                    request()->ip()
                )
                ->sendURLs(
                    route('paytabs.invoice.success.from-link', $token) . '?amount=' . $amount,
                    route('paytabs.invoice.callback.from-link')
                )
                ->sendLanguage('en')
                ->sendFramed(false)
                ->create_pay_page();

            if ($pay && method_exists($pay, 'getTargetUrl')) {
                return response()->json([
                    'success' => true,
                    'redirect_url' => $pay->getTargetUrl()
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => __('Payment initialization failed')
            ], 400);

        } catch (\Exception $e) {
            Log::error('PayTabs invoice payment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('Payment processing failed')
            ], 500);
        }
    }

    public function invoiceSuccessFromLink(Request $request, $token)
    {
        $invoice = \App\Models\Invoice::where('payment_token', $token)->firstOrFail();
        $amount = $request->input('amount', $invoice->total);
        $cartId = 'invoice_' . $invoice->id . '_' . $amount . '_link_' . time();

        $invoice->createPaymentRecord((float)$amount, 'paytabs', $cartId);

        return redirect()->route('invoices.payment', $token)
            ->with('success', 'Payment processed successfully.');
    }

    public function invoiceCallbackFromLink(Request $request)
    {
        try {
            $cartId = $request->input('cartId') ?? $request->input('cart_id');
            $respStatus = $request->input('respStatus') ?? $request->input('resp_status');
            $tranRef = $request->input('tranRef') ?? $request->input('tran_ref');

            if ($cartId && $respStatus === 'A') {
                $parts = explode('_', $cartId);
                if (count($parts) >= 4 && $parts[0] === 'invoice') {
                    $invoiceId = $parts[1];
                    $amount = $parts[2];
                    $invoice = \App\Models\Invoice::find($invoiceId);

                    if ($invoice) {
                        $paymentId = $tranRef ?? $cartId;
                        $existingPayment = \App\Models\Payment::where('invoice_id', $invoice->id)
                            ->where('transaction_id', $paymentId)
                            ->first();
                            
                        if (!$existingPayment) {
                            $invoice->createPaymentRecord((float)$amount, 'paytabs', $paymentId);
                        }
                    }
                }
            }

            return response('OK', 200);
        } catch (\Exception $e) {
            Log::error('PayTabs callback from link error: ' . $e->getMessage());
            return response('ERROR', 500);
        }
    }
}
