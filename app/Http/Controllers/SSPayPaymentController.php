<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SSPayPaymentController extends Controller
{
    // SSPay exposes the same bill API as ToyyibPay (createBill / getBillTransactions).
    private const API_BASE = 'https://sspay.my/index.php/api/';
    private const PAY_BASE = 'https://sspay.my/';

    /**
     * Legacy browser endpoint. A status posted by the browser proves nothing, so
     * this never activates a plan; it only reports the order's state.
     */
    public function processPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'order_id' => 'required|string',
        ]);

        $order = PlanOrder::where('user_id', auth()->id())
            ->where('payment_method', 'sspay')
            ->where('notes', 'like', '%sspay_ref:' . $validated['order_id'] . '%')
            ->first();

        if ($order?->status === 'approved') {
            return back()->with('success', __('Payment successful and plan activated'));
        }

        return back()->with('warning', __('Your plan will be activated once the payment is confirmed.'));
    }

    /**
     * Create the SSPay bill on the server - the merchant secret key must never be
     * sent to the browser - and return the hosted bill page to redirect to.
     */
    public function createPayment(Request $request)
    {
        $validated = validatePaymentRequest($request);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            $settings = platformPaymentSettings();

            if (empty($settings['sspay_secret_key']) || empty($settings['sspay_category_code'])) {
                return response()->json(['error' => __('SSPay not configured')], 400);
            }

            $user = auth()->user();
            $reference = 'plan_' . $plan->id . '_' . $user->id . '_' . time();

            $response = Http::asForm()->timeout(30)->post(self::API_BASE . 'createBill', [
                'userSecretKey' => $settings['sspay_secret_key'],
                'categoryCode' => $settings['sspay_category_code'],
                'billName' => \Illuminate\Support\Str::limit($plan->name, 30, ''),
                'billDescription' => 'Plan: ' . $plan->name,
                'billPriceSetting' => 1,
                'billPayorInfo' => 1,
                'billAmount' => (int) round($pricing['final_price'] * 100), // cents
                'billReturnUrl' => route('sspay.success'),
                'billCallbackUrl' => route('sspay.callback'),
                'billExternalReferenceNo' => $reference,
                'billTo' => $user->name ?? $user->email,
                'billEmail' => $user->email,
                'billPhone' => '60123456789',
            ]);

            $billCode = $response->json('0.BillCode');
            if (!$response->successful() || !$billCode) {
                return response()->json(['error' => __('Payment creation failed')], 400);
            }

            // Pending until SSPay's transaction API confirms the bill was paid.
            createPendingPlanOrder([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $validated['billing_cycle'],
                'payment_method' => 'sspay',
                'coupon_code' => $validated['coupon_code'] ?? null,
                'payment_id' => $billCode,
            ])->update(['notes' => 'sspay_ref:' . $reference]);

            return response()->json([
                'success' => true,
                'redirect_url' => self::PAY_BASE . $billCode,
                'order_id' => $reference,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => __('Payment creation failed')], 500);
        }
    }

    /** Browser return from SSPay: confirmed against SSPay's API, not the query string. */
    public function success(Request $request)
    {
        $order = $this->orderForBill((string) $request->input('billcode'));

        if ($order && $this->confirmPlanOrder($order)) {
            return redirect()->route('plans.index')->with('success', __('Payment completed successfully!'));
        }

        return redirect()->route('plans.index')->with('warning', __('Your plan will be activated once the payment is confirmed.'));
    }

    /** SSPay server notification; the bill is re-checked with SSPay's API. */
    public function callback(Request $request)
    {
        try {
            $order = $this->orderForBill((string) $request->input('billcode'));
            $confirmed = $order && $this->confirmPlanOrder($order);

            return response()->json(['status' => $confirmed ? 'success' : 'pending']);
        } catch (\Exception $e) {
            return response()->json(['error' => __('Callback processing failed')], 500);
        }
    }


    private function orderForBill(string $billCode): ?PlanOrder
    {
        return $billCode === ''
            ? null
            : PlanOrder::where('payment_id', $billCode)->where('payment_method', 'sspay')->first();
    }

    /**
     * Ask SSPay whether the bill was paid (billpaymentStatus 1) and for how much.
     * If that can't be confirmed the order simply stays pending for a superadmin
     * to approve by hand from Plan Orders.
     */
    private function confirmPlanOrder(PlanOrder $order): bool
    {
        if ($order->status === 'approved') {
            return true;
        }

        $settings = platformPaymentSettings();
        if (empty($settings['sspay_secret_key'])) {
            return false;
        }

        $response = Http::asForm()->timeout(30)->post(self::API_BASE . 'getBillTransactions', [
            'billCode' => $order->payment_id,
            'userSecretKey' => $settings['sspay_secret_key'],
        ]);

        $paid = collect($response->successful() ? ($response->json() ?? []) : [])
            ->first(fn ($transaction) => (string) ($transaction['billpaymentStatus'] ?? '') === '1');

        if (!$paid) {
            return false;
        }

        // getBillTransactions reports the amount in ringgit (e.g. "10.00").
        return completePlanOrderPayment($order, (float) ($paid['billpaymentAmount'] ?? 0), $paid['billpaymentInvoiceNo'] ?? null);
    }
}
