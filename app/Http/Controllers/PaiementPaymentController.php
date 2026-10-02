<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use App\Models\Invoice;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;

class PaiementPaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'transaction_id' => 'required|string',
            'status' => 'required|string',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['paiement_merchant_id'])) {
                return back()->withErrors(['error' => __('Paiement Pro not configured')]);
            }

            if ($validated['status'] === 'success') {
                processPaymentSuccess([
                    'user_id' => auth()->id(),
                    'plan_id' => $plan->id,
                    'billing_cycle' => $validated['billing_cycle'],
                    'payment_method' => 'paiement',
                    'coupon_code' => $validated['coupon_code'] ?? null,
                    'payment_id' => $validated['transaction_id'],
                ]);

                return back()->with('success', __('Payment successful and plan activated'));
            }

            return back()->withErrors(['error' => __('Payment failed or cancelled')]);

        } catch (\Exception $e) {
            return handlePaymentError($e, 'paiement');
        }
    }

    public function createPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'mobile_number' => 'required|string',
            'channel' => 'required|string|in:ALL,MOBILE_MONEY,CARD,BANK_TRANSFER',
        ]);
        
        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['paiement_merchant_id'])) {
                return response()->json(['error' => __('Paiement Pro not configured')], 400);
            }

            $user = auth()->user();
            $referenceNumber = "REF-" . time();

            $data = [
                'merchantId' => $settings['payment_settings']['paiement_merchant_id'],
                'amount' => $pricing['final_price'],
                'description' => "Api PHP",
                'channel' => $validated['channel'],
                'countryCurrencyCode' => $settings['payment_settings']['currency_code'] ?? 'USD',
                'referenceNumber' => $referenceNumber,
                'customerEmail' => $user->email,
                'customerFirstName' => $user->name,
                'customerLastname' => $user->name,
                'customerPhoneNumber' => $validated['mobile_number'],
                'notificationURL' => route('paiement.callback'),
                'returnURL' => route('paiement.success'),
                'returnContext' => json_encode([
                    'plan_id' => $plan->id,
                    'user_id' => $user->id,
                    'billing_cycle' => $validated['billing_cycle'] ?? 'monthly',
                    'coupon_code' => $validated['coupon_code'] ?? null,
                    'mobile_number' => $validated['mobile_number'],
                    'channel' => $validated['channel'],
                ]),
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://www.paiementpro.net/webservice/onlinepayment/init/curl-init.php");
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);



            $response = json_decode($response);
            
            if (isset($response->success) && $response->success == true) {
                return response()->json([
                    'success' => true,
                    'payment_url' => $response->url,
                    'payment_data' => [],
                    'reference_number' => $referenceNumber
                ]);
            } else {
                return response()->json(['error' => $response->message ?? __('Payment creation failed')], 400);
            }

        } catch (\Exception $e) {
            return response()->json(['error' => __('Payment creation failed')], 500);
        }
    }

    public function success(Request $request)
    {
        return redirect()->route('plans.index')->with('success', __('Payment completed successfully'));
    }

    public function callback(Request $request)
    {
        try {
            if ($request->responsecode == 0) {
                $returnContext = json_decode($request->returnContext, true);
                
                if ($returnContext && isset($returnContext['plan_id'], $returnContext['user_id'])) {
                    $plan = Plan::find($returnContext['plan_id']);
                    $user = User::find($returnContext['user_id']);
                    
                    if ($plan && $user) {
                        processPaymentSuccess([
                            'user_id' => $user->id,
                            'plan_id' => $plan->id,
                            'billing_cycle' => $returnContext['billing_cycle'] ?? 'monthly',
                            'payment_method' => 'paiement',
                            'coupon_code' => $returnContext['coupon_code'] ?? null,
                            'payment_id' => $request->referenceNumber ?? $request->transactionId,
                        ]);
                    }
                }
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Callback processing failed'], 500);
        }
    }

    public function processInvoicePayment(Request $request, Invoice $invoice = null)
    {
        if (!$invoice) {
            $invoice = Invoice::where('payment_token', $request->invoice_token)->firstOrFail();
        }

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'mobile_number' => 'required|string',
            'channel' => 'required|string|in:ALL,MOBILE_MONEY,CARD,BANK_TRANSFER',
        ]);

        try {
            $paymentSettings = PaymentSetting::where('user_id', $invoice->created_by)
                ->whereIn('key', ['paiement_merchant_id', 'is_paiement_enabled'])
                ->pluck('value', 'key')
                ->toArray();

            if (empty($paymentSettings['paiement_merchant_id']) || $paymentSettings['is_paiement_enabled'] !== '1') {
                return back()->withErrors(['error' => __('Paiement Pro payment method is not enabled or configured')]);
            }

            $referenceNumber = "INV-" . $invoice->id . "-" . time();
            $client = $invoice->client;

            $data = [
                'merchantId' => $paymentSettings['paiement_merchant_id'],
                'amount' => $request->amount,
                'description' => "Invoice #" . $invoice->invoice_number,
                'channel' => $request->channel,
                'countryCurrencyCode' => 'USD',
                'referenceNumber' => $referenceNumber,
                'customerEmail' => $client->email ?? 'customer@example.com',
                'customerFirstName' => $client->name ?? 'Customer',
                'customerLastname' => 'User',
                'customerPhoneNumber' => $request->mobile_number,
                'notificationURL' => route('paiement.invoice.callback'),
                'returnURL' => route('paiement.invoice.success'),
                'returnContext' => json_encode([
                    'invoice_id' => $invoice->id,
                    'amount' => $request->amount,
                    'mobile_number' => $request->mobile_number,
                    'channel' => $request->channel,
                ]),
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://www.paiementpro.net/webservice/onlinepayment/init/curl-init.php");
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $response = json_decode($response);
            
            if (isset($response->success) && $response->success == true) {
                return response()->json([
                    'success' => true,
                    'payment_url' => $response->url,
                    'payment_data' => [],
                    'reference_number' => $referenceNumber
                ]);
            } else {
                return response()->json(['error' => $response->message ?? __('Payment creation failed')], 400);
            }

        } catch (\Exception $e) {
            return response()->json(['error' => __('Payment creation failed')], 500);
        }
    }

    public function createInvoicePayment(Request $request)
    {
        $request->validate([
            'invoice_token' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'mobile_number' => 'required|string',
            'channel' => 'required|string|in:ALL,MOBILE_MONEY,CARD,BANK_TRANSFER',
        ]);

        try {
            $invoice = Invoice::where('payment_token', $request->invoice_token)->firstOrFail();
            return $this->processInvoicePayment($request, $invoice);
        } catch (\Exception $e) {
            return response()->json(['error' => __('Invoice not found')], 404);
        }
    }

    public function invoiceSuccess(Request $request)
    {
        try {
            $returnContext = $request->input('returnContext');
            $reference = $request->input('referenceNumber');
            
            if ($returnContext) {
                $context = json_decode($returnContext, true);
                
                if (isset($context['invoice_id']) && isset($context['amount'])) {
                    $invoice = Invoice::find($context['invoice_id']);

                    if ($invoice) {
                        $invoice->createPaymentRecord(
                            $context['amount'],
                            'paiement',
                            $reference
                        );

                        return redirect()->route('invoices.show', $invoice->id)
                            ->with('success', __('Payment completed successfully!'));
                    }
                }
            }

            return redirect()->route('invoices.index')
                ->with('error', __('Payment verification failed.'));

        } catch (\Exception $e) {
            return redirect()->route('invoices.index')
                ->with('error', __('Payment processing failed.'));
        }
    }

    public function invoiceCallback(Request $request)
    {
        try {
            $returnContext = $request->input('returnContext');
            $reference = $request->input('referenceNumber');
            $status = $request->input('responsecode');

            if ($returnContext && $status == 0) {
                $context = json_decode($returnContext, true);
                
                if (isset($context['invoice_id']) && isset($context['amount'])) {
                    $invoice = Invoice::find($context['invoice_id']);

                    if ($invoice) {
                        $invoice->createPaymentRecord(
                            $context['amount'],
                            'paiement',
                            $reference
                        );
                    }
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
            
            $invoice = Invoice::where('payment_token', $token)->firstOrFail();
            
            $paymentSettings = PaymentSetting::where('user_id', $invoice->created_by)
                ->whereIn('key', ['paiement_merchant_id', 'is_paiement_enabled'])
                ->pluck('value', 'key')
                ->toArray();

            if (($paymentSettings['is_paiement_enabled'] ?? '0') !== '1') {
                return response()->json(['error' => 'Paiement Pro payment method is not enabled'], 400);
            }

            if (empty($paymentSettings['paiement_merchant_id'])) {
                return response()->json(['error' => 'Paiement Pro credentials not configured'], 400);
            }

            $transactionId = 'INV-' . $invoice->id . '-' . time();
            $client = $invoice->client;

            $data = [
                'merchantId' => $paymentSettings['paiement_merchant_id'],
                'amount' => (int)($request->amount * 549),
                'description' => 'Invoice #' . $invoice->invoice_number,
                'channel' => 'CARD',
                'countryCurrencyCode' => '952',
                'referenceNumber' => $transactionId,
                'customerEmail' => $client->email ?? 'customer@example.com',
                'customerFirstName' => $client->first_name ?? 'Customer',
                'customerLastname' => $client->last_name ?? 'User',
                'customerPhoneNumber' => $client->phone ?? '01234567',
                'notificationURL' => route('paiement.invoice.callback.link'),
                'returnURL' => route('paiement.invoice.success.link', ['token' => $token]) . '?reference=' . $transactionId . '&amount=' . $request->amount,
                'returnContext' => json_encode(['invoice_id' => $invoice->id, 'amount' => $request->amount, 'token' => $token])
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://www.paiementpro.net/webservice/onlinepayment/init/curl-init.php');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $responseData = json_decode($response, true);
                return response()->json([
                    'success' => true,
                    'payment_response' => $responseData,
                    'transaction_id' => $transactionId
                ]);
            }

            return response()->json(['error' => 'Payment initialization failed'], 500);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function invoiceSuccessFromLink(Request $request, $token)
    {
        try {
            $reference = $request->input('reference');
            $amount = $request->input('amount');
            
            $invoice = Invoice::where('payment_token', $token)->firstOrFail();

            if ($reference && $amount) {
                $invoice->createPaymentRecord(
                    (float)$amount,
                    'paiement',
                    $reference
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