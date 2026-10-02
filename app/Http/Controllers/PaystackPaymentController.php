<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Invoice;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaystackPaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'payment_id' => 'required|string',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['paystack_secret_key'])) {
                return back()->withErrors(['error' => __('Paystack not configured')]);
            }

            // Verify payment with Paystack API
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . $validated['payment_id'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer " . $settings['payment_settings']['paystack_secret_key'],
                    "Cache-Control: no-cache",
                ],
            ));

            $response = curl_exec($curl);
            curl_close($curl);

            $result = json_decode($response, true);

            if ($result['status'] && $result['data']['status'] === 'success') {
                processPaymentSuccess([
                    'user_id' => auth()->id(),
                    'plan_id' => $plan->id,
                    'billing_cycle' => $validated['billing_cycle'],
                    'payment_method' => 'paystack',
                    'coupon_code' => $validated['coupon_code'] ?? null,
                    'payment_id' => $validated['payment_id'],
                ]);

                return back()->with('success', __('Payment successful and plan activated'));
            }

            return back()->withErrors(['error' => __('Payment verification failed')]);

        } catch (\Exception $e) {
            return handlePaymentError($e, 'paystack');
        }
    }

    public function processInvoicePayment(Request $request)
    {
        try {
            $request->validate([
                'invoice_id' => 'required|exists:invoices,id',
                'amount' => 'required|numeric|min:0.01',
                'payment_id' => 'required|string',
            ]);
            
            $invoice = Invoice::findOrFail($request->invoice_id);
            $settings = PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            
            if (!isset($settings['is_paystack_enabled']) || $settings['is_paystack_enabled'] !== '1') {
                return back()->withErrors(['error' => 'Paystack not enabled']);
            }

            // Verify payment with Paystack API
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . $request->payment_id,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer " . $settings['paystack_secret_key'],
                    "Cache-Control: no-cache",
                ],
            ));

            $response = curl_exec($curl);
            curl_close($curl);

            $result = json_decode($response, true);

            if ($result['status'] && $result['data']['status'] === 'success') {
                $invoice->createPaymentRecord(
                    $request->amount,
                    'paystack',
                    $request->payment_id
                );
                
                return redirect()->route('invoices.show', $invoice->id)
                    ->with('success', 'Payment processed successfully.');
            }

            return back()->withErrors(['error' => 'Payment verification failed']);
            
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
                'payment_id' => 'required|string',
                'amount' => 'required|numeric|min:0.01',
            ]);
            
            $invoice = Invoice::where('payment_token', $token)->firstOrFail();
            $settings = PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            

            
            if (!isset($settings['is_paystack_enabled']) || $settings['is_paystack_enabled'] !== '1') {
                return back()->withErrors(['error' => 'Paystack not enabled']);
            }

            // Verify payment with Paystack API
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . $request->payment_id,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer " . $settings['paystack_secret_key'],
                    "Cache-Control: no-cache",
                ],
            ));

            $response = curl_exec($curl);
            curl_close($curl);

            $result = json_decode($response, true);

            if ($result['status'] && $result['data']['status'] === 'success') {
                $invoice->createPaymentRecord(
                    $request->amount,
                    'paystack',
                    $request->payment_id
                );
                
                return redirect()->route('invoices.payment', $token)
                    ->with('success', 'Payment processed successfully.');
            }

            return back()->withErrors(['error' => 'Payment verification failed']);
            
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Payment processing failed. Please try again or contact support.']);
        }
    }
}