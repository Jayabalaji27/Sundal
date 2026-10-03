<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use App\Models\Setting;
use App\Models\PlanOrder;
use App\Models\PaymentSetting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PayPalService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PayPalPaymentController extends Controller
{
    /**
     * Plan purchase. The PayPal buttons capture the order in the browser; the
     * order is confirmed with PayPal here (status, amount, currency) before the
     * plan is activated, and each PayPal order can only pay for one plan order.
     */
    public function processPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'order_id' => 'required|string|max:64',
            'payment_id' => 'nullable|string|max:64',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle']);
            $settings = platformPaymentSettings();

            if (($settings['is_paypal_enabled'] ?? null) !== '1') {
                return back()->withErrors(['error' => __('PayPal is not enabled.')]);
            }
            if (planPaymentAlreadyUsed($validated['order_id'])) {
                return back()->withErrors(['error' => __('This PayPal payment has already been used.')]);
            }

            PayPalService::fromSettings($settings)->confirmOrder(
                $validated['order_id'],
                (float) $pricing['final_price'],
                $settings['defaultCurrency'] ?? 'usd'
            );

            processPaymentSuccess([
                'user_id' => auth()->id(),
                'plan_id' => $plan->id,
                'billing_cycle' => $validated['billing_cycle'],
                'payment_method' => 'paypal',
                'coupon_code' => $validated['coupon_code'] ?? null,
                'payment_id' => $validated['order_id'],
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
                'order_id' => 'required|string|max:64',
            ]);

            $invoice = Invoice::findOrFail($request->invoice_id);

            return $this->recordVerifiedInvoicePayment($invoice, (float) $request->amount, $request->order_id)
                ?? redirect()->route('invoices.show', $invoice->id)->with('success', __('Payment processed successfully.'));

        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            return back()->withErrors(['error' => __('Payment processing failed: :message', ['message' => $e->getMessage()])]);
        }
    }

    public function processInvoicePaymentFromLink(Request $request, $token)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'order_id' => 'required|string|max:64',
            ]);

            $invoice = Invoice::where('payment_token', $token)->firstOrFail();

            return $this->recordVerifiedInvoicePayment($invoice, (float) $request->amount, $request->order_id)
                ?? redirect()->route('invoices.payment', $invoice->payment_token)->with('success', __('Payment processed successfully.'));

        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return back()->withErrors(['error' => __('Invoice not found. Please check the link and try again.')]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => __('Payment processing failed: :message', ['message' => $e->getMessage()])]);
        }
    }

    /**
     * Confirm the PayPal order with the invoice owner's credentials and record it
     * as a completed payment. Returns an error response, or null on success.
     */
    private function recordVerifiedInvoicePayment(Invoice $invoice, float $amount, string $orderId)
    {
        $settings = PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();

        if (($settings['is_paypal_enabled'] ?? null) !== '1') {
            return back()->withErrors(['error' => __('PayPal is not enabled.')]);
        }
        if (Payment::where('transaction_id', $orderId)->exists()) {
            return back()->withErrors(['error' => __('This PayPal payment has already been recorded.')]);
        }
        if ($amount > (float) $invoice->remaining_amount + 0.009) {
            return back()->withErrors(['error' => __('The amount is more than the balance due.')]);
        }

        // The invoice PayPal button (invoice-paypal-modal.tsx) creates USD orders.
        PayPalService::fromSettings($settings)->confirmOrder($orderId, $amount, 'USD');

        $invoice->createPaymentRecord($amount, 'paypal', $orderId, verified: true);

        return null;
    }
}
