<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use FedaPay\FedaPay;
use FedaPay\Transaction;

class FedaPayPaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'transaction_id' => 'required|string',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['fedapay_secret_key'])) {
                return back()->withErrors(['error' => 'FedaPay not configured']);
            }

            $this->configureFedaPay($settings['payment_settings']);
            
            $transaction = Transaction::retrieve($validated['transaction_id']);
            
            if ($transaction->status === 'approved') {
                processPaymentSuccess([
                    'user_id' => auth()->id(),
                    'plan_id' => $plan->id,
                    'billing_cycle' => $validated['billing_cycle'],
                    'payment_method' => 'fedapay',
                    'coupon_code' => $validated['coupon_code'] ?? null,
                    'payment_id' => $validated['transaction_id'],
                ]);

                return back()->with('success', __('Payment successful and plan activated'));
            }

            return back()->withErrors(['error' => __('Payment failed or cancelled')]);

        } catch (\Exception $e) {
            return back()->withErrors(['error' => __('Payment processing failed')]);
        }
    }

    public function createPayment(Request $request)
    {
        $validated = validatePaymentRequest($request);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['fedapay_secret_key'])) {
                return response()->json(['error' => __('FedaPay not configured')], 400);
            }

            $this->configureFedaPay($settings['payment_settings']);

            $user = auth()->user();
            
            $transaction = Transaction::create([
                'description' => 'Plan: ' . $plan->name,
                'amount' => $pricing['final_price'] * 100, // Amount in cents
                'currency' => ['iso' => 'XOF'],
                'callback_url' => route('fedapay.callback'),
                'customer' => [
                    'firstname' => $user->name ?? 'Customer',
                    'email' => $user->email,
                ],
                'custom_metadata' => [
                    'plan_id' => $plan->id,
                    'user_id' => $user->id,
                    'billing_cycle' => $validated['billing_cycle'],
                    'coupon_code' => $validated['coupon_code'] ?? null,
                ]
            ]);

            $token = $transaction->generateToken();

            return response()->json([
                'success' => true,
                'payment_url' => $token->url,
                'transaction_id' => $transaction->id,
                'token' => $token->token
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => __('Payment creation failed')], 500);
        }
    }

    public function callback(Request $request)
    {
        try {
            $settings = getPaymentGatewaySettings();
            $this->configureFedaPay($settings['payment_settings']);
            
            $transactionId = $request->input('id');
            $transaction = Transaction::retrieve($transactionId);
            
            if ($transaction->status === 'approved') {
                $metadata = $transaction->custom_metadata;
                
                processPaymentSuccess([
                    'user_id' => $metadata['user_id'],
                    'plan_id' => $metadata['plan_id'],
                    'billing_cycle' => $metadata['billing_cycle'],
                    'payment_method' => 'fedapay',
                    'coupon_code' => $metadata['coupon_code'] ?? null,
                    'payment_id' => $transactionId,
                ]);
                
                return redirect()->route('plans.index')->with('success', __('Payment successful and plan activated'));
            }

            return redirect()->route('plans.index')->with('error', __('Payment was not completed'));

        } catch (\Exception $e) {
            return response()->json(['error' => __('Callback processing failed')], 500);
        }
    }

    public function processInvoicePayment(Request $request)
    {
        $request->validate([
            'invoice_token' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'transaction_id' => 'required|string',
        ]);

        try {
            $invoice = \App\Models\Invoice::where('payment_token', $request->invoice_token)->firstOrFail();

            $settings = \App\Models\PaymentSetting::where('user_id', $invoice->created_by)
                ->pluck('value', 'key')
                ->toArray();

            if (!isset($settings['fedapay_secret_key'])) {
                return back()->withErrors(['error' => 'FedaPay not configured']);
            }

            $this->configureFedaPay($settings);

            $transaction = Transaction::retrieve($request->transaction_id);

            if ($transaction->status === 'approved') {
                $invoice->createPaymentRecord($request->amount, 'fedapay', $request->transaction_id);

                return redirect()->route('invoices.show', $invoice->id)
                    ->with('success', __('Payment successful!'));
            }

            return back()->withErrors(['error' => __('Payment failed or cancelled')]);

        } catch (\Exception $e) {
            return back()->withErrors(['error' => __('Payment processing failed')]);
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

            $paymentSettings = \App\Models\PaymentSetting::where('user_id', $invoice->created_by)
                ->whereIn('key', ['fedapay_secret_key', 'fedapay_mode', 'is_fedapay_enabled'])
                ->pluck('value', 'key')
                ->toArray();

            if (empty($paymentSettings['fedapay_secret_key']) || $paymentSettings['is_fedapay_enabled'] !== '1') {
                return response()->json(['error' => 'FedaPay payment not configured'], 400);
            }

            $this->configureFedaPay($paymentSettings);

            $transaction = Transaction::create([
                'description' => 'Invoice Payment - ' . $invoice->invoice_number,
                'amount' => $request->amount * 100,
                'currency' => ['iso' => $invoice->currency ?? 'XOF'],
                'callback_url' => route('fedapay.invoice.callback'),
                'customer' => [
                    'firstname' => 'Customer',
                    'email' => 'customer@example.com',
                ],
                'custom_metadata' => [
                    'invoice_token' => $invoice->payment_token,
                    'invoice_id' => $invoice->id,
                    'amount' => $request->amount,
                ]
            ]);

            $token = $transaction->generateToken();

            return response()->json([
                'success' => true,
                'payment_url' => $token->url,
                'transaction_id' => $transaction->id,
                'token' => $token->token
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function invoiceCallback(Request $request)
    {
        try {
            $transactionId = $request->input('id');

            if ($transactionId) {
                $allSettings = \App\Models\PaymentSetting::whereIn('key', ['fedapay_secret_key', 'fedapay_mode'])
                    ->get()
                    ->groupBy('user_id');

                foreach ($allSettings as $userId => $userSettings) {
                    $settings = $userSettings->pluck('value', 'key')->toArray();

                    if (isset($settings['fedapay_secret_key'])) {
                        try {
                            $this->configureFedaPay($settings);
                            $transaction = Transaction::retrieve($transactionId);

                            if ($transaction->status === 'approved') {
                                $metadata = $transaction->custom_metadata;
                                $invoiceToken = $metadata['invoice_token'];
                                $amount = $metadata['amount'];
                                $invoiceId = $metadata['invoice_id'];

                                $invoice = \App\Models\Invoice::find($invoiceId);

                                if ($invoice) {
                                    $invoice->createPaymentRecord($amount, 'fedapay', $transactionId);

                                    return redirect()->route('invoices.show', $invoice->id)
                                        ->with('success', 'Payment completed successfully!');
                                }
                            }
                            break;
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                }
            }

            return redirect()->route('invoices.index')
                ->with('error', 'Payment verification failed.');

        } catch (\Exception $e) {
            return response()->json(['error' => 'Callback processing failed'], 500);
        }
    }

    public function createInvoicePaymentFromLink(Request $request)
    {
        try {
            $request->validate([
                'invoice_token' => 'required|string',
                'amount' => 'required|numeric|min:0.01'
            ]);

            $invoice = \App\Models\Invoice::where('payment_token', $request->invoice_token)->firstOrFail();

            $paymentSettings = \App\Models\PaymentSetting::where('user_id', $invoice->created_by)
                ->whereIn('key', ['fedapay_secret_key', 'fedapay_mode', 'is_fedapay_enabled'])
                ->pluck('value', 'key')
                ->toArray();

            if (empty($paymentSettings['fedapay_secret_key']) || $paymentSettings['is_fedapay_enabled'] !== '1') {
                return response()->json(['error' => 'FedaPay payment not configured'], 400);
            }

            $this->configureFedaPay($paymentSettings);

            $transaction = Transaction::create([
                'description' => 'Invoice Payment - ' . $invoice->invoice_number,
                'amount' => $request->amount * 100,
                'currency' => ['iso' => $invoice->currency ?? 'XOF'],
                'callback_url' => route('fedapay.invoice.link.callback'),
                'customer' => [
                    'firstname' => 'Customer',
                    'email' => 'customer@example.com',
                ],
                'custom_metadata' => [
                    'invoice_token' => $invoice->payment_token,
                    'invoice_id' => $invoice->id,
                    'amount' => $request->amount,
                ]
            ]);

            $token = $transaction->generateToken();

            return response()->json([
                'success' => true,
                'payment_url' => $token->url,
                'transaction_id' => $transaction->id,
                'token' => $token->token
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function invoiceLinkCallback(Request $request)
    {
        try {
            $transactionId = $request->input('id');

            if ($transactionId) {
                $allSettings = \App\Models\PaymentSetting::whereIn('key', ['fedapay_secret_key', 'fedapay_mode'])
                    ->get()
                    ->groupBy('user_id');

                foreach ($allSettings as $userId => $userSettings) {
                    $settings = $userSettings->pluck('value', 'key')->toArray();

                    if (isset($settings['fedapay_secret_key'])) {
                        try {
                            $this->configureFedaPay($settings);
                            $transaction = Transaction::retrieve($transactionId);

                            if ($transaction->status === 'approved') {
                                $metadata = $transaction->custom_metadata;
                                $invoiceToken = $metadata['invoice_token'];
                                $amount = $metadata['amount'];
                                $invoiceId = $metadata['invoice_id'];

                                $invoice = \App\Models\Invoice::find($invoiceId);

                                if ($invoice) {
                                    $invoice->createPaymentRecord($amount, 'fedapay', $transactionId);

                                    return redirect()->route('invoices.payment', $invoiceToken)->with('success', 'Payment completed successfully.');
                                }
                            }
                            break;
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                }
            }

            return redirect()->route('invoices.payment', 'invalid')
                ->with('error', 'Payment verification failed.');

        } catch (\Exception $e) {
            return redirect()->route('invoices.payment', 'invalid')
                ->with('error', 'Payment processing failed.');
        }
    }

    private function configureFedaPay($settings)
    {
        FedaPay::setApiKey($settings['fedapay_secret_key']);
        FedaPay::setEnvironment($settings['fedapay_mode'] === 'live' ? 'live' : 'sandbox');
    }
}