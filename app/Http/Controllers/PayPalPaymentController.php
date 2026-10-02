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
use Illuminate\Validation\ValidationException;

class PayPalPaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'order_id' => 'required|string',
            'payment_id' => 'required|string',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            
            processPaymentSuccess([
                'user_id' => auth()->id(),
                'plan_id' => $plan->id,
                'billing_cycle' => $validated['billing_cycle'],
                'payment_method' => 'paypal',
                'coupon_code' => $validated['coupon_code'] ?? null,
                'payment_id' => $validated['payment_id'],
            ]);

            return back()->with('success', __('Payment successful and plan activated'));

        } catch (\Exception $e) {
            return handlePaymentError($e, 'paypal');
        }
    }

    public function processInvoicePayment(Request $request)
    {
        try {
            $request->validate([
                'invoice_id' => 'required|exists:invoices,id',
                'amount' => 'required|numeric|min:0.01',
                'order_id' => 'required|string',
                'payment_id' => 'required|string',
            ]);
            
            $invoice = Invoice::findOrFail($request->invoice_id);
            $settings = PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            
            if (!isset($settings['is_paypal_enabled']) || $settings['is_paypal_enabled'] !== '1') {
                return back()->withErrors(['error' => 'PayPal not enabled']);
            }

            $invoice->createPaymentRecord(
                $request->amount,
                'paypal',
                $request->payment_id
            );
            return redirect()->route('invoices.show', $invoice->id)
                ->with('success', 'Payment processed successfully.');
            
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Payment processing failed. Please try again or contact support.']);
        }
    }

    public function processInvoicePaymentFromLink(Request $request, $token)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'order_id' => 'required|string',
                'payment_id' => 'required|string',
            ]);
            
            $invoice = Invoice::where('payment_token', $token)->firstOrFail();
            
            Payment::create([
                'invoice_id' => $invoice->id,
                'amount' => $request->amount,
                'payment_method' => 'paypal',
                'payment_date' => now(),
                'created_by' => $invoice->created_by
            ]);
            
            return redirect()->route('invoices.payment', $invoice->payment_token)
                ->with('success', 'Payment processed successfully.');
            
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return back()->withErrors(['error' => 'Invoice not found. Please check the link and try again.']);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Payment processing failed. Please try again or contact support.']);
        }
    }
}