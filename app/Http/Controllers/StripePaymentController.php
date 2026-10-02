<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use App\Models\Setting;
use App\Models\PlanOrder;
use App\Models\PaymentSetting;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class StripePaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'payment_method_id' => 'required|string',
            'cardholder_name' => 'required|string',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            $settings = getPaymentGatewaySettings(true);
            
            if (!isset($settings['payment_settings']['stripe_secret']) || !isset($settings['payment_settings']['stripe_key'])) {
                return back()->withErrors(['error' => __('Stripe not configured')]);
            }

            $stripeSecret = $settings['payment_settings']['stripe_secret'];
            if (!str_starts_with($stripeSecret, 'sk_')) {
                return back()->withErrors(['error' => __('Invalid Stripe secret key format')]);
            }

            Stripe::setApiKey($stripeSecret);

            $paymentIntent = PaymentIntent::create([
                'amount' => $pricing['final_price'] * 100,
                'currency' => strtolower($settings['general_settings']['defaultCurrency'] ?? 'usd'),
                'payment_method' => $validated['payment_method_id'],
                'confirmation_method' => 'manual',
                'confirm' => true,
                'return_url' => route('plans.index'),
                'description' => 'Plan subscription: ' . $plan->name . ' (' . ($validated['billing_cycle'] ?? 'monthly') . ')',
                'shipping' => [
                    'name' => $request->cardholder_name,
                    'address' => [
                        'line1' => 'N/A',
                        'city' => 'N/A',
                        'country' => 'US',
                        'postal_code' => '00000'
                    ]
                ],
                'metadata' => [
                    'plan_name' => $plan->name,
                    'billing_cycle' => $validated['billing_cycle'] ?? 'monthly',
                    'user_id' => auth()->id(),
                    'export_type' => 'software_service'
                ],
            ]);

            if ($paymentIntent->status === 'succeeded') {
                processPaymentSuccess([
                    'user_id' => auth()->id(),
                    'plan_id' => $plan->id,
                    'billing_cycle' => $validated['billing_cycle'],
                    'payment_method' => 'stripe',
                    'coupon_code' => $validated['coupon_code'] ?? null,
                    'payment_id' => $paymentIntent->id,
                ]);

                return back()->with('success', __('Payment successful and plan activated'));
            }

            return back()->withErrors(['error' => __('Payment failed')]);

        } catch (\Exception $e) {
            return handlePaymentError($e, 'stripe');
        }
    }

    public function processInvoicePayment(Request $request)
    {
        try {
            $request->validate([
                'invoice_id' => 'required|exists:invoices,id',
                'amount' => 'required|numeric|min:0.01',
                'payment_method_id' => 'required|string',
                'cardholder_name' => 'required|string',
            ]);
            
            $invoice = Invoice::findOrFail($request->invoice_id);


            $settings = PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            
            if (!isset($settings['is_stripe_enabled']) || $settings['is_stripe_enabled'] !== '1') {
                return back()->withErrors(['error' => 'Stripe not enabled']);
            }
            
            if (!isset($settings['stripe_secret']) || empty($settings['stripe_secret'])) {
                return back()->withErrors(['error' => 'Stripe not configured']);
            }

            Stripe::setApiKey($settings['stripe_secret']);

            $paymentIntent = PaymentIntent::create([
                'amount' => $request->amount * 100,
                'currency' => 'usd',
                'payment_method' => $request->payment_method_id,
                'confirmation_method' => 'manual',
                'confirm' => true,
                'return_url' => route('invoices.show', $invoice->id),
                'description' => 'Invoice payment: ' . $invoice->invoice_number,
                'shipping' => [
                    'name' => $request->cardholder_name,
                    'address' => [
                        'line1' => 'N/A',
                        'city' => 'N/A',
                        'country' => 'US',
                        'postal_code' => '00000'
                    ]
                ],
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'type' => 'invoice_payment'
                ],
            ]);
            if ($paymentIntent->status === 'succeeded') {
                $invoice->createPaymentRecord(
                    $request->amount,
                    'stripe',
                    $paymentIntent->id
                );

                return redirect()->route('invoices.show', $invoice->id)
                    ->with('success', 'Payment processed successfully.');
            }

            return back()->withErrors(['error' => 'Payment failed']);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Stripe\Exception\CardException $e) {
            return back()->withErrors(['error' => 'Card payment failed: ' . $e->getError()->message]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Payment processing failed: ' . $e->getMessage()]);
        }
    }

    public function processInvoicePaymentFromLink(Request $request, $token)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'payment_method_id' => 'required|string',
                'cardholder_name' => 'required|string',
            ]);
            
            $invoice = Invoice::where('payment_token', $token)->firstOrFail();
            $settings = PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            
            if (!isset($settings['is_stripe_enabled']) || $settings['is_stripe_enabled'] !== '1') {
                return back()->withErrors(['error' => 'Stripe not enabled']);
            }
            
            if (!isset($settings['stripe_secret']) || empty($settings['stripe_secret'])) {
                return back()->withErrors(['error' => 'Stripe not configured']);
            }

            Stripe::setApiKey($settings['stripe_secret']);

            $paymentIntent = PaymentIntent::create([
                'amount' => $request->amount * 100,
                'currency' => 'usd',
                'payment_method' => $request->payment_method_id,
                'confirmation_method' => 'manual',
                'confirm' => true,
                'return_url' => route('invoices.payment', $invoice->payment_token),
                'description' => 'Invoice payment: ' . $invoice->invoice_number,
                'shipping' => [
                    'name' => $request->cardholder_name,
                    'address' => [
                        'line1' => 'N/A',
                        'city' => 'N/A',
                        'country' => 'US',
                        'postal_code' => '00000'
                    ]
                ],
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'type' => 'invoice_payment'
                ],
            ]);

            if ($paymentIntent->status === 'succeeded') {
                Payment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => $request->amount,
                    'payment_method' => 'stripe',
                    'payment_date' => now(),
                    'created_by' => $invoice->created_by
                ]);

                return redirect()->route('invoices.payment', $invoice->payment_token)
                    ->with('success', 'Payment processed successfully.');
            }

            return back()->withErrors(['error' => 'Payment failed']);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Stripe\Exception\CardException $e) {
            return back()->withErrors(['error' => 'Card payment failed: ' . $e->getError()->message]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Payment processing failed: ' . $e->getMessage()]);
        }
    }
}