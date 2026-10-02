<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use YooKassa\Client;

class YooKassaPaymentController extends Controller
{
    public function createPayment(Request $request)
    {
        $validated = validatePaymentRequest($request);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['yookassa_shop_id'])) {
                return response()->json(['error' => 'YooKassa not configured'], 400);
            }

            $client = new Client();
            $client->setAuth((int)$settings['payment_settings']['yookassa_shop_id'], $settings['payment_settings']['yookassa_secret_key']);
            
            $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
            $user = auth()->user();
            
            $payment = $client->createPayment([
                'amount' => [
                    'value' => number_format($pricing['final_price'], 2, '.', ''),
                    'currency' => 'RUB',
                ],
                'confirmation' => [
                    'type' => 'redirect',
                    'return_url' => route('yookassa.success', [
                        'plan_id' => $plan->id, 
                        'order_id' => $orderID, 
                        'billing_cycle' => $validated['billing_cycle'],
                        'coupon_code' => $validated['coupon_code'] ?? null
                    ]),
                ],
                'capture' => true,
                'description' => 'Plan: ' . $plan->name,
                'metadata' => [
                    'plan_id' => $plan->id,
                    'user_id' => $user->id,
                    'billing_cycle' => $validated['billing_cycle'],
                    'coupon_code' => $validated['coupon_code'] ?? null,
                    'order_id' => $orderID
                ]
            ], uniqid('', true));

            if ($payment['confirmation']['confirmation_url'] != null) {
                return response()->json([
                    'success' => true,
                    'payment_url' => $payment['confirmation']['confirmation_url'],
                    'payment_id' => $payment['id']
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
        try {
            $planId = $request->input('plan_id');
            $billingCycle = $request->input('billing_cycle');
            $couponCode = $request->input('coupon_code');
            $orderId = $request->input('order_id');
            
            if ($planId && $orderId) {
                $plan = Plan::find($planId);
                
                // Find user by session or create temporary assignment
                $user = null;
                if (auth()->check()) {
                    $user = auth()->user();
                } else {
                    // Try to find user from recent plan orders
                    $recentOrder = \App\Models\PlanOrder::where('payment_id', 'like', '%' . substr($orderId, -8))
                        ->where('created_at', '>=', now()->subHours(1))
                        ->first();
                    if ($recentOrder) {
                        $user = \App\Models\User::find($recentOrder->user_id);
                    }
                }
                
                if ($plan && $user) {
                    // Assign plan to user immediately
                    $user->plan_id = $plan->id;
                    $user->plan_expire_date = $billingCycle === 'yearly' ? now()->addYear() : now()->addMonth();
                    $user->save();
                    
                    // Create plan order record
                    processPaymentSuccess([
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'billing_cycle' => $billingCycle,
                        'payment_method' => 'yookassa',
                        'coupon_code' => $couponCode,
                        'payment_id' => $orderId,
                    ]);
                    
                    return redirect()->route('plans.index')->with('success', 'Payment successful and plan activated');
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
            $paymentId = $request->input('object.id');
            $status = $request->input('object.status');
            $metadata = $request->input('object.metadata');
            
            if ($paymentId && $status === 'succeeded' && $metadata) {
                $planId = $metadata['plan_id'];
                $userId = $metadata['user_id'];
                
                $plan = Plan::find($planId);
                $user = \App\Models\User::find($userId);
                
                if ($plan && $user) {
                    // Assign plan to user
                    $user->plan_id = $plan->id;
                    $user->plan_expire_date = $metadata['billing_cycle'] === 'yearly' ? now()->addYear() : now()->addMonth();
                    $user->save();
                    
                    processPaymentSuccess([
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'billing_cycle' => $metadata['billing_cycle'] ?? 'monthly',
                        'payment_method' => 'yookassa',
                        'coupon_code' => $metadata['coupon_code'] ?? null,
                        'payment_id' => $paymentId,
                    ]);
                }
            }
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['error' => __('Callback processing failed')], 500);
        }
    }

    public function createInvoicePayment(Request $request)
    {
        try {
            $request->validate([
                'invoice_id' => 'required|exists:invoices,id',
                'amount' => 'required|numeric|min:0.01'
            ]);

            $invoice = \App\Models\Invoice::findOrFail($request->invoice_id);
            $settings = \App\Models\PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            
            if (!isset($settings['yookassa_shop_id']) || !isset($settings['is_yookassa_enabled']) || $settings['is_yookassa_enabled'] !== '1') {
                return response()->json(['error' => __('YooKassa not configured')], 400);
            }

            $client = new Client();
            $client->setAuth((int)$settings['yookassa_shop_id'], $settings['yookassa_secret_key']);

            $orderId = 'invoice_' . $invoice->id . '_' . time();

            $payment = $client->createPayment([
                'amount' => [
                    'value' => number_format($request->amount, 2, '.', ''),
                    'currency' => 'RUB',
                ],
                'confirmation' => [
                    'type' => 'redirect',
                    'return_url' => route('yookassa.invoice.success', [
                        'invoice_id' => $invoice->id,
                        'amount' => $request->amount
                    ]),
                ],
                'capture' => true,
                'description' => 'Invoice Payment - ' . $invoice->invoice_number,
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'order_id' => $orderId,
                    'amount' => $request->amount
                ]
            ], uniqid('', true));

            if ($payment['confirmation']['confirmation_url'] != null) {
                return response()->json([
                    'success' => true,
                    'redirect_url' => $payment['confirmation']['confirmation_url'],
                    'payment_id' => $payment['id']
                ]);
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
                'payment_id' => 'required|string',
            ]);
            
            $invoice = \App\Models\Invoice::where('payment_token', $request->route('token'))->firstOrFail();
            $settings = \App\Models\PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            
            if (!isset($settings['is_yookassa_enabled']) || $settings['is_yookassa_enabled'] !== '1') {
                return back()->withErrors(['error' => 'YooKassa not enabled']);
            }

            $invoice->createPaymentRecord(
                $request->amount,
                'yookassa',
                $request->payment_id
            );
            
            return redirect()->route('invoices.show', $invoice->id)
                ->with('success', 'YooKassa payment processed successfully.');
            
        } catch (\Illuminate\Validation\ValidationException $e) {
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
            
            if ($invoiceId && $amount) {
                $invoice = \App\Models\Invoice::find($invoiceId);
                
                if ($invoice) {
                    $invoice->createPaymentRecord(
                        $amount,
                        'yookassa',
                        'yookassa_' . time()
                    );
                    
                    return redirect()->route('invoices.show', $invoice->id)
                        ->with('success', 'Payment completed successfully!`');
                }
            }
            
            return redirect()->route('invoices.show', $invoiceId ?: 1)
                ->with('error', 'Payment verification failed');
            
        } catch (\Exception $e) {
            return redirect()->route('invoices.show', $request->input('invoice_id') ?: 1)
                ->with('error', 'Payment processing failed');
        }
    }

    public function invoiceCallback(Request $request)
    {
        try {
            $paymentId = $request->input('object.id');
            $status = $request->input('object.status');
            $metadata = $request->input('object.metadata');

            if ($paymentId && $status === 'succeeded' && $metadata) {
                $invoiceId = $metadata['invoice_id'];
                $amount = $metadata['amount'];

                $invoice = \App\Models\Invoice::find($invoiceId);

                if ($invoice) {
                    $invoice->createPaymentRecord($amount, 'yookassa', $paymentId);
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
            
            $invoice = \App\Models\Invoice::where('payment_token', $token)->firstOrFail();
            
            $paymentSettings = \App\Models\PaymentSetting::where('user_id', $invoice->created_by)
                ->whereIn('key', ['yookassa_shop_id', 'yookassa_secret_key', 'is_yookassa_enabled'])
                ->pluck('value', 'key')
                ->toArray();

            if (($paymentSettings['is_yookassa_enabled'] ?? '0') !== '1') {
                return response()->json(['error' => 'YooKassa payment method is not enabled'], 400);
            }

            if (empty($paymentSettings['yookassa_shop_id']) || empty($paymentSettings['yookassa_secret_key'])) {
                return response()->json(['error' => 'YooKassa credentials not configured'], 400);
            }

            $client = new Client();
            $client->setAuth((int)$paymentSettings['yookassa_shop_id'], $paymentSettings['yookassa_secret_key']);

            $orderId = 'invoice_' . $invoice->id . '_' . time();

            $payment = $client->createPayment([
                'amount' => [
                    'value' => number_format($request->amount, 2, '.', ''),
                    'currency' => 'RUB',
                ],
                'confirmation' => [
                    'type' => 'redirect',
                    'return_url' => route('yookassa.invoice.success.link', $token) . '?order_id=' . $orderId . '&amount=' . $request->amount,
                ],
                'capture' => true,
                'description' => 'Invoice Payment - ' . $invoice->invoice_number,
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'invoice_token' => $token,
                    'order_id' => $orderId,
                    'amount' => $request->amount
                ]
            ], uniqid('', true));

            if ($payment['confirmation']['confirmation_url'] != null) {
                return response()->json([
                    'success' => true,
                    'redirect_url' => $payment['confirmation']['confirmation_url'],
                    'payment_id' => $payment['id']
                ]);
            }

            return response()->json(['error' => 'Payment creation failed'], 500);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function invoiceSuccessFromLink(Request $request, $token)
    {
        try {
            $orderId = $request->input('order_id');
            $amount = $request->input('amount');
            
            $invoice = \App\Models\Invoice::where('payment_token', $token)->firstOrFail();

            if ($orderId && $amount) {
                $invoice->createPaymentRecord(
                    (float)$amount,
                    'yookassa',
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