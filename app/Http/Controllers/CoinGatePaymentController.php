<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanOrder;
use App\Models\PaymentSetting;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Libraries\Coingate\Coingate;
use CoinGate\Client;
use Illuminate\Support\Facades\Log;

class CoinGatePaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
            'coupon_code' => 'nullable|string'
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $user = auth()->user();
            
            // Get payment settings exactly like reference project
            $settings = getPaymentGatewaySettings();
                 
            
            if (!$settings['payment_settings']['is_coingate_enabled'] || !$settings['payment_settings']['coingate_api_token']) {
                return redirect()->route('plans.index')->with('error', __('CoinGate payment is not available'));
            }
            
            if (!isset($settings['payment_settings']['coingate_api_token']) || empty($settings['payment_settings']['coingate_api_token'])) {
                return redirect()->route('plans.index')->with('error', __('CoinGate API token not configured'));
            }
            
            // Calculate price
            $price = $validated['billing_cycle'] === 'yearly' ? $plan->yearly_price : $plan->price;
            
            // Create plan order
            $orderId = time();
            $planOrder = PlanOrder::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $validated['billing_cycle'],
                'payment_method' => 'coingate',
                'coupon_code' => $validated['coupon_code'],
                'payment_id' => $orderId,
                'original_price' => $price,
                'final_price' => $price,
                'status' => 'pending'
            ]);
            
            // Use official CoinGate package
            $client = new Client(
                $settings['payment_settings']['coingate_api_token'], 
                ($settings['payment_settings']['coingate_mode'] ?? 'sandbox') === 'sandbox'
            );
            
            $orderParams = [
                'order_id' => $orderId,
                'price_amount' => $price,
                'price_currency' => $settings['general_settings']['defaultCurrency'] ?? 'USD',
                'receive_currency' => $settings['general_settings']['defaultCurrency'] ?? 'USD',
                'callback_url' => route('coingate.callback'),
                'cancel_url' => route('plans.index'),
                'success_url' => route('coingate.callback'),
                'title' => 'Plan #' . $orderId,
            ];
            
            $orderResponse = $client->order->create($orderParams);
            
            if ($orderResponse && isset($orderResponse->payment_url)) {
                // Store in session like reference project
                session(['coingate_data' => $orderResponse]);
                
                // Store gateway response
                $planOrder->payment_id = $orderResponse->order_id;
                $planOrder->save();
                
                return redirect($orderResponse->payment_url);
            } else {
                $planOrder->update(['status' => 'cancelled']);
                return redirect()->route('plans.index')->with('error', 'Payment initialization failed');
            }
            
        } catch (\Exception $e) {
            return redirect()->route('plans.index')->with('error', 'Payment failed: ' . $e->getMessage());
        }
    }
    
    public function processInvoicePayment(Request $request)
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01'
        ]);

        try {
            $invoice = Invoice::findOrFail($validated['invoice_id']);
            
            // Get payment settings for the invoice creator
            $settings = PaymentSetting::where('user_id', $invoice->created_by)
                ->whereIn('key', ['coingate_api_token', 'is_coingate_enabled', 'coingate_mode'])
                ->pluck('value', 'key')
                ->toArray();

            if (($settings['is_coingate_enabled'] ?? '0') !== '1') {
                throw new \Exception('CoinGate payment method is not enabled');
            }

            if (empty($settings['coingate_api_token'])) {
                throw new \Exception('CoinGate API token is not configured');
            }
            
            $client = new Client(
                $settings['coingate_api_token'],
                ($settings['coingate_mode'] ?? 'sandbox') === 'sandbox'
            );

            $orderId = 'invoice_' . $invoice->id . '_' . time();
            
            $orderParams = [
                'order_id' => $orderId,
                'price_amount' => $validated['amount'],
                'price_currency' => 'USD',
                'receive_currency' => 'USD',
                'callback_url' => route('coingate.callback'),
                'cancel_url' => route('invoices.show', $invoice->id),
                'success_url' => route('coingate.callback'),
                'title' => 'Invoice Payment - ' . $invoice->invoice_number,
            ];

            $orderResponse = $client->order->create($orderParams);
            
            if ($orderResponse && isset($orderResponse->payment_url)) {
                session(['coingate_invoice_data' => [
                    'order_response' => $orderResponse,
                    'invoice_id' => $invoice->id,
                    'payment_amount' => $validated['amount']
                ]]);

                return redirect()->away($orderResponse->payment_url);
            } else {
                throw new \Exception('Payment initialization failed');
            }
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return back()->withErrors(['error' => __('Invoice not found. Please check the link and try again.')]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => __('Payment processing failed. Please try again or contact support.')]);
        }
    }

    public function callback(Request $request)
    {
        try {
            // Handle invoice payment from link callback
            $coingateInvoiceData = session('coingate_invoice_data');
            if ($coingateInvoiceData && isset($coingateInvoiceData['from_link'])) {
                $orderResponse = $coingateInvoiceData['order_response'];
                $orderId = is_object($orderResponse) ? $orderResponse->order_id : $orderResponse['order_id'];
                
                $paymentAmount = is_object($orderResponse) ? $orderResponse->price_amount : $orderResponse['price_amount'];
                if (!$paymentAmount && isset($coingateInvoiceData['payment_amount'])) {
                    $paymentAmount = $coingateInvoiceData['payment_amount'];
                }

                $invoice = Invoice::findOrFail($coingateInvoiceData['invoice_id']);
                $invoice->createPaymentRecord($paymentAmount, 'coingate', $orderId);
                
                session()->forget('coingate_invoice_data');

                return redirect()->route('invoices.payment', $coingateInvoiceData['invoice_token'])
                    ->with('success', 'Payment processed successfully.');
            }
            
            // Handle invoice payment from show page callback
            if ($coingateInvoiceData && !isset($coingateInvoiceData['from_link'])) {
                $orderResponse = $coingateInvoiceData['order_response'];
                $orderId = is_object($orderResponse) ? $orderResponse->order_id : $orderResponse['order_id'];
                
                $paymentAmount = is_object($orderResponse) ? $orderResponse->price_amount : $orderResponse['price_amount'];
                if (!$paymentAmount && isset($coingateInvoiceData['payment_amount'])) {
                    $paymentAmount = $coingateInvoiceData['payment_amount'];
                }

                $invoice = Invoice::findOrFail($coingateInvoiceData['invoice_id']);
                $invoice->createPaymentRecord($paymentAmount, 'coingate', $orderId);
                
                session()->forget('coingate_invoice_data');

                return redirect()->route('invoices.show', $invoice->id)
                    ->with('success', 'Payment processed successfully.');
            }

            // Handle plan payment callback
            $user = auth()->user();
            $coingateData = session('coingate_data');
            
            if (!$coingateData) {
                Log::error('CoinGate data not found in session');
                return redirect()->route('plans.index')->with('error', 'Payment session expired');
            }
            
            $orderId = is_object($coingateData) ? $coingateData->order_id : $coingateData['order_id'];
            $planOrder = PlanOrder::where('payment_id', $orderId)->first();
            
            if (!$planOrder) {
                Log::error('Plan order not found', ['order_id' => $orderId]);
                return redirect()->route('plans.index')->with('error', 'Order not found');
            }
            
            // Mark as successful and activate subscription
            $planOrder->update([
                'status' => 'approved',
                'processed_at' => now()
            ]);
            
            $planOrder->activateSubscription();
            
            // Clear session
            session()->forget('coingate_data');
            
            return redirect()->route('plans.index')->with('success', 'Plan activated successfully!');
            
        } catch (\Exception $e) {
            Log::error('CoinGate callback error: ' . $e->getMessage());
            return redirect()->route('plans.index')->with('error', 'Payment processing failed');
        }
    }


    public function createInvoicePaymentFromLink(Request $request)
    {
        $validated = $request->validate([
            'invoice_token' => 'required|string',
            'amount' => 'required|numeric|min:0.01'
        ]);

        try {
            $invoice = Invoice::where('payment_token', $validated['invoice_token'])->firstOrFail();
            
            $paymentSettings = PaymentSetting::where('user_id', $invoice->created_by)
                ->whereIn('key', ['coingate_api_token', 'is_coingate_enabled', 'coingate_mode'])
                ->pluck('value', 'key')
                ->toArray();

            if (($paymentSettings['is_coingate_enabled'] ?? '0') !== '1') {
                return back()->withErrors(['error' => __('CoinGate payment method is not enabled')]);
            }

            if (empty($paymentSettings['coingate_api_token'])) {
                return back()->withErrors(['error' => __('CoinGate API token is not configured')]);
            }
            
            $client = new Client(
                $paymentSettings['coingate_api_token'],
                ($paymentSettings['coingate_mode'] ?? 'sandbox') === 'sandbox'
            );

            $orderId = 'invoice_' . $invoice->id . '_' . time();
            
            $orderParams = [
                'order_id' => $orderId,
                'price_amount' => $validated['amount'],
                'price_currency' => 'USD',
                'receive_currency' => 'USD',
                'callback_url' => route('coingate.callback'),
                'cancel_url' => route('invoices.payment', $validated['invoice_token']),
                'success_url' => route('coingate.callback'),
                'title' => 'Invoice Payment - ' . $invoice->invoice_number,
            ];

            $orderResponse = $client->order->create($orderParams);
            
            if ($orderResponse && isset($orderResponse->payment_url)) {
                session(['coingate_invoice_data' => [
                    'order_response' => $orderResponse,
                    'invoice_id' => $invoice->id,
                    'invoice_token' => $validated['invoice_token'],
                    'payment_amount' => $validated['amount'],
                    'from_link' => true
                ]]);

                return redirect()->away($orderResponse->payment_url);
            } else {
                return back()->withErrors(['error' => __('Payment initialization failed')]);
            }
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return back()->withErrors(['error' => __('Invoice not found. Please check the link and try again.')]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => __('Payment processing failed. Please try again or contact support.')]);
        }
    }
}
