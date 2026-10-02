<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Invoice;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FlutterwavePaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'payment_id' => 'required',
            'tx_ref' => 'required|string',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['flutterwave_secret_key'])) {
                return back()->withErrors(['error' => __('Flutterwave not configured')]);
            }

            // Verify payment with Flutterwave API
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://api.flutterwave.com/v3/transactions/" . $validated['payment_id'] . "/verify",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer " . $settings['payment_settings']['flutterwave_secret_key'],
                    "Content-Type: application/json",
                ],
            ));

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($httpCode !== 200) {
                return back()->withErrors(['error' => __('Payment verification failed - API error')]);
            }

            $result = json_decode($response, true);
            
            if (!$result) {
                return back()->withErrors(['error' => __('Payment verification failed - Invalid response')]);
            }

            if ($result['status'] === 'success' && $result['data']['status'] === 'successful') {
                // Check if payment amount matches plan price
                $expectedAmount = $plan->price;
                $paidAmount = $result['data']['amount'];
                
                if (abs($paidAmount - $expectedAmount) > 0.01) {
                    return back()->withErrors(['error' => __('Payment amount verification failed')]);
                }
                
                processPaymentSuccess([
                    'user_id' => auth()->id(),
                    'plan_id' => $plan->id,
                    'billing_cycle' => $validated['billing_cycle'],
                    'payment_method' => 'flutterwave',
                    'coupon_code' => $validated['coupon_code'] ?? null,
                    'payment_id' => $validated['payment_id'],
                ]);

                return redirect()->route('plans.index')->with('success', __('Payment successful! Your plan has been activated.'));
            }

            return back()->withErrors(['error' => __('Payment verification failed')]);

        } catch (\Exception $e) {
            return handlePaymentError($e, 'flutterwave');
        }
    }

    public function processInvoicePayment(Request $request)
    {
        try {
            $request->validate([
                'invoice_id' => 'required|exists:invoices,id',
                'amount' => 'required|numeric|min:0.01',
                'payment_id' => 'required',
                'tx_ref' => 'required|string',
            ]);
            
            $invoice = Invoice::findOrFail($request->invoice_id);
            $settings = PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            
            if (!isset($settings['is_flutterwave_enabled']) || $settings['is_flutterwave_enabled'] !== '1') {
                return back()->withErrors(['error' => 'Flutterwave not enabled']);
            }

            // Verify payment with Flutterwave API
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://api.flutterwave.com/v3/transactions/" . $request->payment_id . "/verify",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer " . $settings['flutterwave_secret_key'],
                    "Content-Type: application/json",
                ],
            ));

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($httpCode !== 200) {
                return back()->withErrors(['error' => 'Payment verification failed - API error']);
            }

            $result = json_decode($response, true);
            
            if (!$result) {
                return back()->withErrors(['error' => 'Payment verification failed - Invalid response']);
            }

            if ($result['status'] === 'success' && $result['data']['status'] === 'successful') {
                // Check if payment amount matches
                $expectedAmount = $request->amount;
                $paidAmount = $result['data']['amount'];
                
                if (abs($paidAmount - $expectedAmount) > 0.01) {
                    return back()->withErrors(['error' => 'Payment amount verification failed']);
                }
                
                $invoice->createPaymentRecord(
                    $request->amount,
                    'flutterwave',
                    $request->payment_id
                );
                
                return redirect()->route('invoices.show', $invoice->id)
                    ->with('success', 'Payment processed successfully!');
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
                'payment_id' => 'required',
                'tx_ref' => 'required|string',
                'amount' => 'required|numeric|min:0.01',
            ]);
            
            $invoice = Invoice::where('payment_token', $token)->firstOrFail();
            $settings = PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            
            if (!isset($settings['is_flutterwave_enabled']) || $settings['is_flutterwave_enabled'] !== '1') {
                return back()->withErrors(['error' => 'Flutterwave not enabled']);
            }

            // Verify payment with Flutterwave API
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://api.flutterwave.com/v3/transactions/" . $request->payment_id . "/verify",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer " . $settings['flutterwave_secret_key'],
                    "Content-Type: application/json",
                ],
            ));

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($httpCode !== 200) {
                return back()->withErrors(['error' => 'Payment verification failed - API error']);
            }

            $result = json_decode($response, true);
            
            if (!$result) {
                return back()->withErrors(['error' => 'Payment verification failed - Invalid response']);
            }

            if ($result['status'] === 'success' && $result['data']['status'] === 'successful') {
                // Check if payment amount matches
                $expectedAmount = $request->amount;
                $paidAmount = $result['data']['amount'];
                
                if (abs($paidAmount - $expectedAmount) > 0.01) {
                    return back()->withErrors(['error' => 'Payment amount verification failed']);
                }
                
                $invoice->createPaymentRecord(
                    $request->amount,
                    'flutterwave',
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