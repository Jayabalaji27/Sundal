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

class SkrillPaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'transaction_id' => 'required|string',
            'email' => 'required|email',
        ]);

        try {
            $userID = User::where('type', 'superadmin')->first()?->id;
            $settings = getPaymentMethodConfig('skrill', $userID);

            createPlanOrder([
                'user_id' => auth()->id(),
                'plan_id' => $validated['plan_id'],
                'billing_cycle' => $validated['billing_cycle'],
                'payment_method' => 'skrill',
                'coupon_code' => $validated['coupon_code'] ?? null,
                'payment_id' => $validated['transaction_id'],
                'status' => 'pending'
            ]);

            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');

            $paymentData = [
                'pay_to_email' => $settings['merchant_email'] ?? $settings['merchant_id'],
                'transaction_id' => $validated['transaction_id'],
                'return_url' => route('plans.index'),
                'cancel_url' => route('plans.index'),
                'status_url' => route('skrill.callback'),
                'language' => 'EN',
                'amount' => $pricing['final_price'],
                'currency' => 'USD',
                'detail1_description' => 'Plan Subscription',
                'detail1_text' => $plan->name,
                'pay_from_email' => $validated['email']
            ];
            // Create form and auto-submit to Skrill
            $form = '<form id="skrill-form" method="POST" action="https://www.moneybookers.com/app/payment.pl">';
            foreach ($paymentData as $key => $value) {
                $form .= '<input type="hidden" name="' . $key . '" value="' . $value . '">';
            }
            $form .= '</form><script>document.getElementById("skrill-form").submit();</script>';

            return response($form);
        } catch (\Exception $e) {
            return handlePaymentError($e, 'skrill');
        }
    }

    public function processInvoicePayment(Request $request)
    {
        $validated = $request->validate([
            'invoice_token' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'email' => 'required|email'
        ]);

        try {
            $invoice = Invoice::where('payment_token', $validated['invoice_token'])->firstOrFail();
            
            // Get payment settings for the invoice creator
            $settings = PaymentSetting::where('user_id', $invoice->created_by)
                ->whereIn('key', ['skrill_merchant_id', 'is_skrill_enabled'])
                ->pluck('value', 'key')
                ->toArray();

            if (($settings['is_skrill_enabled'] ?? '0') !== '1') {
                throw new \Exception('Skrill payment method is not enabled');
            }
            
            $transactionId = 'SKRILL_INV_' . $invoice->id . '_' . time();
            
            $paymentData = [
                'pay_to_email' => $settings['skrill_merchant_id'],
                'transaction_id' => $transactionId,
                'return_url' => route('skrill.invoice.success') . '?invoice_token=' . $validated['invoice_token'],
                'cancel_url' => route('invoices.payment', $validated['invoice_token']),
                'status_url' => route('skrill.invoice.callback'),
                'language' => 'EN',
                'amount' => number_format($validated['amount'], 2, '.', ''),
                'currency' => 'USD',
                'detail1_description' => 'Invoice Payment',
                'detail1_text' => 'Invoice #' . $invoice->invoice_number,
                'pay_from_email' => $validated['email'],
                'custom' => $validated['invoice_token']
            ];
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'redirect_url' => 'https://www.moneybookers.com/app/payment.pl',
                    'method' => 'POST',
                    'data' => $paymentData
                ]);
            }
            
            // Create form and auto-submit to Skrill
            $form = '<form id="skrill-form" method="POST" action="https://www.moneybookers.com/app/payment.pl">';
            foreach ($paymentData as $key => $value) {
                $form .= '<input type="hidden" name="' . $key . '" value="' . htmlspecialchars($value) . '">';
            }
            $form .= '</form><script>document.getElementById("skrill-form").submit();</script>';
            
            return response($form);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'errors' => $e->errors()], 422);
            }
            return back()->withErrors($e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => __('Invoice not found. Please check the link and try again.')], 404);
            }
            return back()->withErrors(['error' => __('Invoice not found. Please check the link and try again.')]);
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => __('Payment processing failed. Please try again or contact support.')], 500);
            }
            return back()->withErrors(['error' => __('Payment processing failed. Please try again or contact support.')]);
        }
    }

    public function callback(Request $request)
    {
        $transactionId = $request->input('transaction_id');
        $status = $request->input('status');

        if ($status == '2') { // Payment processed
            $planOrder = PlanOrder::where('payment_id', $transactionId)->first();

            if ($planOrder && $planOrder->status === 'pending') {
                $planOrder->update(['status' => 'approved']);
                $planOrder->activateSubscription();
            }
        }

        return response('OK', 200);
    }

    public function invoiceSuccess(Request $request)
    {
        try {
            $invoiceToken = $request->input('invoice_token') ?? $request->input('custom');
            $transactionId = $request->input('transaction_id');
            $status = $request->input('status');
            $amount = $request->input('amount');
            
            if (!$invoiceToken) {
                return redirect()->route('home')->with('error', __('Invalid payment response'));
            }
            
            $invoice = Invoice::where('payment_token', $invoiceToken)->first();
            
            if (!$invoice) {
                return redirect()->route('home')->with('error', __('Invoice not found'));
            }
            
            // Check if payment already exists to prevent duplicates
            $existingPayment = Payment::where('invoice_id', $invoice->id)
                ->where('payment_method', 'skrill')
                ->where('transaction_id', $transactionId)
                ->first();
                
            if (!$existingPayment && $status == '2' && $amount) {
                $invoice->createPaymentRecord($amount, 'skrill', $transactionId);
            }
            
            return redirect()->route('invoices.payment', $invoiceToken)
                ->with('success', __('Payment completed successfully'));
                
        } catch (\Exception $e) {
            return redirect()->route('home')->with('error', __('Payment processing failed'));
        }
    }
    
    public function invoiceCallback(Request $request)
    {
        $transactionId = $request->input('transaction_id');
        $status = $request->input('status');
        $invoiceToken = $request->input('custom');
        $amount = $request->input('amount');
        
        if ($status == '2' && $invoiceToken) { // Payment processed
            $invoice = Invoice::where('payment_token', $invoiceToken)->first();
            
            if ($invoice) {
                // Check for duplicate payments
                $existingPayment = Payment::where('invoice_id', $invoice->id)
                    ->where('payment_method', 'skrill')
                    ->where('transaction_id', $transactionId)
                    ->first();
                    
                if (!$existingPayment) {
                    $invoice->createPaymentRecord($amount, 'skrill', $transactionId);
                }
            }
        }
        
        return response('OK', 200);
    }

    public function createInvoicePaymentFromLink(Request $request, $token)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'email' => 'required|email'
        ]);

        try {
            $invoice = Invoice::where('payment_token', $token)->firstOrFail();
            
            $settings = PaymentSetting::where('user_id', $invoice->created_by)
                ->whereIn('key', ['skrill_merchant_id', 'is_skrill_enabled'])
                ->pluck('value', 'key')
                ->toArray();
            
            if (($settings['is_skrill_enabled'] ?? '0') !== '1') {
                throw new \Exception('Skrill payment method is not enabled');
            }
            
            $transactionId = 'SKRILL_INV_' . $invoice->id . '_' . time();
            
            $paymentData = [
                'pay_to_email' => $settings['skrill_merchant_id'],
                'transaction_id' => $transactionId,
                'return_url' => route('skrill.invoice.success.from-link', $token),
                'cancel_url' => route('invoices.payment', $token),
                'status_url' => route('skrill.invoice.callback.from-link'),
                'language' => 'EN',
                'amount' => number_format($validated['amount'], 2, '.', ''),
                'currency' => 'USD',
                'detail1_description' => 'Invoice Payment',
                'detail1_text' => 'Invoice #' . $invoice->invoice_number,
                'email' => $validated['email'],
                'custom' => $token
            ];
            return response()->json([
                'success' => true,
                'redirect_url' => 'https://www.moneybookers.com/app/payment.pl',
                'method' => 'POST',
                'data' => $paymentData
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function invoiceSuccessFromLink(Request $request, $token)
    {
        try {
            $invoice = Invoice::where('payment_token', $token)->firstOrFail();
            return redirect()->route('invoices.payment', $token);
        } catch (\Exception $e) {
            return redirect()->route('invoices.payment', $token)->with('error', __('Payment processing failed'));
        }
    }

    public function invoiceCallbackFromLink(Request $request)
    {
        $transactionId = $request->input('transaction_id');
        $status = $request->input('status');
        $token = $request->input('custom');
        $amount = $request->input('amount');
        
        if ($status == '2' && $token) {
            $invoice = Invoice::where('payment_token', $token)->first();
            
            if ($invoice) {
                $existingPayment = Payment::where('invoice_id', $invoice->id)
                    ->where('payment_method', 'skrill')
                    ->where('transaction_id', $transactionId)
                    ->first();
                    
                if (!$existingPayment) {
                    $invoice->createPaymentRecord($amount, 'skrill', $transactionId);
                }
            }
        }
        
        return response('OK', 200);
    }
}
