<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use App\Models\Invoice;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ToyyibPayPaymentController extends Controller
{
    private $secretKey;
    private $categoryCode;
    private $callBackUrl;
    private $returnUrl;

    public function __construct()
    {
        $userID = User::where('type', 'superadmin')->first()?->id;
        $settings = getPaymentMethodConfig('toyyibpay', $userID);
        $this->secretKey = $settings['secret_key'] ?? '';
        $this->categoryCode = $settings['category_code'] ?? '';
    }

    private function formatPhoneNumber($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) < 10) {
            $phone = '123456789';
        }
        if (!str_starts_with($phone, '60')) {
            $phone = '60' . ltrim($phone, '0');
        }
        return $phone;
    }

    public function processPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'billName' => 'required|string',
            'billAmount' => 'required|numeric|min:0.01',
            'billTo' => 'required|string',
            'billEmail' => 'required|email',
            'billPhone' => 'required|string',
            'billDescription' => 'nullable|string',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $user = Auth::user();

            if (!$this->secretKey || !$this->categoryCode) {
                return back()->withErrors(['error' => __('ToyyibPay payment gateway not configured properly')]);
            }

            // Calculate final amount with coupon
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            $finalAmount = $pricing['final_price'];

            // Set callback and return URLs
            $this->callBackUrl = route('toyyibpay.callback');
            $this->returnUrl = route('toyyibpay.success');

            // Generate unique payment reference
            $paymentId = 'toyyib_' . $plan->id . '_' . time() . '_' . uniqid();

            // Create plan order before payment
            createPlanOrder([
                'user_id' => $user->id,
                'plan_id' => $validated['plan_id'],
                'billing_cycle' => $validated['billing_cycle'],
                'payment_method' => 'toyyibpay',
                'coupon_code' => $validated['coupon_code'] ?? null,
                'payment_id' => $paymentId,
                'status' => 'pending'
            ]);

            // Format phone number for Malaysian format
            $phone = preg_replace('/[^0-9]/', '', $validated['billPhone']);
            if (!str_starts_with($phone, '60')) {
                $phone = '60' . ltrim($phone, '0');
            }

            // Prepare bill data
            $billData = [
                'userSecretKey' => $this->secretKey,
                'categoryCode' => $this->categoryCode,
                'billName' => $validated['billName'],
                'billDescription' => $validated['billDescription'] ?? $plan->description ?? $plan->name,
                'billPriceSetting' => 1,
                'billPayorInfo' => 1,
                'billAmount' => intval($finalAmount * 100), // Convert to cents
                'billReturnUrl' => $this->returnUrl,
                'billCallbackUrl' => $this->callBackUrl,
                'billExternalReferenceNo' => $paymentId,
                'billTo' => $validated['billTo'],
                'billEmail' => $validated['billEmail'],
                'billPhone' => $phone,
                'billSplitPayment' => 0,
                'billSplitPaymentArgs' => '',
                'billPaymentChannel' => '0',
                'billContentEmail' => 'Thank you for your subscription!',
                'billChargeToCustomer' => 1,
                'billExpiryDate' => date('d-m-Y', strtotime('+3 days')),
                'billExpiryDays' => 3
            ];

            // Make API call to ToyyibPay
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_URL, 'https://toyyibpay.com/index.php/api/createBill');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $billData);
            curl_setopt($curl, CURLOPT_TIMEOUT, 30);

            $result = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);

            if ($curlError) {
                throw new \Exception('cURL Error: ' . $curlError);
            }

            if ($httpCode !== 200) {
                throw new \Exception('HTTP Error: ' . $httpCode);
            }

            // Handle response
            if (str_contains($result, 'KEY-DID-NOT-EXIST-OR-USER-IS-NOT-ACTIVE')) {
                throw new \Exception(__('Invalid ToyyibPay credentials or inactive account'));
            }

            $responseData = json_decode($result, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception(__('Invalid JSON response from ToyyibPay'));
            }

            if (isset($responseData[0]['BillCode'])) {
                $redirectUrl = 'https://toyyibpay.com/' . $responseData[0]['BillCode'];
                return redirect()->away($redirectUrl);
            } else {
                $errorMsg = $responseData[0]['msg'] ?? __('Failed to create payment bill');
                throw new \Exception($errorMsg);
            }

        } catch (\Exception $e) {
            return handlePaymentError($e, 'ToyyibPay');
        }
    }

    public function callback(Request $request)
    {
        try {
            $billcode = $request->input('billcode');
            $status_id = $request->input('status_id');
            $order_id = $request->input('order_id');
            $transaction_id = $request->input('transaction_id');

            if ($status_id == '1') { // Payment successful
                $planOrder = \App\Models\PlanOrder::where('payment_id', $order_id)->first();

                if ($planOrder && $planOrder->status === 'pending') {
                    processPaymentSuccess([
                        'user_id' => $planOrder->user_id,
                        'plan_id' => $planOrder->plan_id,
                        'billing_cycle' => $planOrder->billing_cycle,
                        'payment_method' => 'toyyibpay',
                        'coupon_code' => $planOrder->coupon_code,
                        'payment_id' => $order_id,
                    ]);
                }
            }
            return response('OK', 200);
        } catch (\Exception $e) {
            return response('ERROR', 500);
        }
    }

    public function success(Request $request)
    {
        $status_id = $request->input('status_id');
        $order_id = $request->input('order_id');

        if ($status_id == '1') {
            return redirect()->route('plans.index')->with('success', __('Payment completed successfully!'));
        } else {
            return redirect()->route('plans.index')->with('error', __('Payment was not completed. Please try again.'));
        }
    }

    public function createInvoicePayment(Request $request)
    {
        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'billTo' => 'required|string|max:255',
            'billEmail' => 'required|email|max:255',
            'billPhone' => 'required|string|max:20'
        ]);

        try {
            $invoice = Invoice::findOrFail($request->invoice_id);
            $settings = PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            
            if (!isset($settings['toyyibpay_secret_key']) || !isset($settings['is_toyyibpay_enabled']) || $settings['is_toyyibpay_enabled'] !== '1') {
                return response()->json(['error' => __('ToyyibPay not configured')], 400);
            }

            $paymentId = 'inv_' . $invoice->id . '_' . time() . '_' . uniqid();

            $billData = [
                'userSecretKey' => $settings['toyyibpay_secret_key'],
                'categoryCode' => $settings['toyyibpay_category_code'] ?? '',
                'billName' => 'Invoice #' . $invoice->invoice_number,
                'billDescription' => 'Payment for Invoice #' . $invoice->invoice_number,
                'billPriceSetting' => 1,
                'billPayorInfo' => 1,
                'billAmount' => intval($request->amount * 100),
                'billReturnUrl' => route('toyyibpay.invoice.success', [
                    'invoice_id' => $invoice->id,
                    'amount' => $request->amount
                ]),
                'billCallbackUrl' => route('toyyibpay.invoice.callback'),
                'billExternalReferenceNo' => $paymentId,
                'billTo' => $request->billTo,
                'billEmail' => $request->billEmail,
                'billPhone' => $this->formatPhoneNumber($request->billPhone),
                'billSplitPayment' => 0,
                'billPaymentChannel' => '0',
                'billContentEmail' => 'Thank you for your payment!',
                'billChargeToCustomer' => 1,
                'billExpiryDate' => date('d-m-Y', strtotime('+3 days')),
                'billExpiryDays' => 3
            ];

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_URL, 'https://toyyibpay.com/index.php/api/createBill');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $billData);
            curl_setopt($curl, CURLOPT_TIMEOUT, 30);

            $result = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($httpCode !== 200) {
                return response()->json(['error' => __('ToyyibPay API connection failed')], 500);
            }

            $responseData = json_decode($result, true);

            if (isset($responseData[0]['BillCode'])) {
                return response()->json([
                    'success' => true,
                    'redirect_url' => 'https://toyyibpay.com/' . $responseData[0]['BillCode']
                ]);
            }

            $errorMsg = $responseData[0]['msg'] ?? __('Failed to create payment bill');
            return response()->json(['error' => $errorMsg], 500);

        } catch (\Exception $e) {
            return response()->json(['error' => __('Payment creation failed')], 500);
        }
    }

    public function processInvoicePayment(Request $request)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'order_id' => 'required|string',
            ]);
            
            $invoice = Invoice::where('payment_token', $request->route('token'))->firstOrFail();
            $settings = PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            
            if (!isset($settings['is_toyyibpay_enabled']) || $settings['is_toyyibpay_enabled'] !== '1') {
                return back()->withErrors(['error' => 'ToyyibPay not enabled']);
            }

            // For demo purposes, simulate successful payment
            $invoice->createPaymentRecord(
                $request->amount,
                'toyyibpay',
                $request->order_id
            );
            
            return redirect()->route('invoices.show', $invoice->id)
                ->with('success', 'ToyyibPay payment processed successfully.');
            
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Payment processing failed. Please try again or contact support.']);
        }
    }

    public function invoiceSuccess(Request $request)
    {
        try {
            $invoiceId = $request->input('invoice_id');
            $amount = $request->input('amount');
            $status_id = $request->input('status_id');
            $order_id = $request->input('order_id');
            
            if ($status_id == '1' && $invoiceId && $amount) {
                $invoice = Invoice::find($invoiceId);
                
                if ($invoice) {
                    $invoice->createPaymentRecord(
                        $amount,
                        'toyyibpay',
                        $order_id ?: 'toyyibpay_' . time()
                    );
                    
                    return redirect()->route('invoices.show', $invoice->id)
                        ->with('success', __('ToyyibPay payment completed successfully'));
                }
            }
            
            return redirect()->route('home')->with('error', __('Payment verification failed'));
            
        } catch (\Exception $e) {
            return redirect()->route('home')->with('error', __('Payment processing failed'));
        }
    }

    public function invoiceCallback(Request $request)
    {
        try {
            $status_id = $request->input('status_id');
            $order_id = $request->input('order_id');

            if ($status_id == '1' && $order_id && str_starts_with($order_id, 'inv_')) {
                $parts = explode('_', $order_id);
                if (count($parts) >= 2) {
                    $invoiceId = $parts[1];
                    $invoice = Invoice::find($invoiceId);

                    if ($invoice) {
                        $invoice->createPaymentRecord($invoice->total_amount, 'toyyibpay', $order_id);
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
            $settings = PaymentSetting::where('user_id', $invoice->created_by)->pluck('value', 'key')->toArray();
            
            if (!isset($settings['is_toyyibpay_enabled']) || $settings['is_toyyibpay_enabled'] !== '1') {
                return response()->json(['error' => 'ToyyibPay not enabled'], 400);
            }

            if (!isset($settings['toyyibpay_secret_key'])) {
                return response()->json(['error' => 'ToyyibPay not configured'], 400);
            }

            $paymentId = 'inv_' . $invoice->id . '_' . time() . '_' . uniqid();

            $billData = [
                'userSecretKey' => $settings['toyyibpay_secret_key'],
                'categoryCode' => $settings['toyyibpay_category_code'] ?? '',
                'billName' => 'Invoice #' . $invoice->invoice_number,
                'billDescription' => 'Payment for Invoice #' . $invoice->invoice_number,
                'billPriceSetting' => 1,
                'billPayorInfo' => 1,
                'billAmount' => intval($request->amount * 100),
                'billReturnUrl' => route('toyyibpay.invoice.success.link', [
                    'token' => $token,
                    'amount' => $request->amount
                ]),
                'billCallbackUrl' => route('toyyibpay.invoice.callback'),
                'billExternalReferenceNo' => $paymentId,
                'billTo' => 'Customer',
                'billEmail' => 'customer@example.com',
                'billPhone' => $this->formatPhoneNumber('123456789'),
                'billSplitPayment' => 0,
                'billPaymentChannel' => '0',
                'billContentEmail' => 'Thank you for your payment!',
                'billChargeToCustomer' => 1,
                'billExpiryDate' => date('d-m-Y', strtotime('+3 days')),
                'billExpiryDays' => 3
            ];

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_URL, 'https://toyyibpay.com/index.php/api/createBill');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $billData);
            curl_setopt($curl, CURLOPT_TIMEOUT, 30);

            $result = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($httpCode !== 200) {
                return response()->json(['error' => 'ToyyibPay API connection failed'], 400);
            }

            $responseData = json_decode($result, true);

            if (isset($responseData[0]['BillCode'])) {
                return response()->json([
                    'success' => true,
                    'redirect_url' => 'https://toyyibpay.com/' . $responseData[0]['BillCode']
                ]);
            }

            $errorMsg = $responseData[0]['msg'] ?? 'Failed to create payment bill';
            return response()->json(['error' => $errorMsg], 400);
            
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Payment processing failed. Please try again.'], 500);
        }
    }

    public function invoiceSuccessFromLink(Request $request, $token)
    {
        try {
            $amount = $request->input('amount');
            $status_id = $request->input('status_id');
            $order_id = $request->input('order_id');
            
            if ($status_id == '1' && $amount) {
                $invoice = Invoice::where('payment_token', $token)->firstOrFail();
                
                $invoice->createPaymentRecord(
                    $amount,
                    'toyyibpay',
                    $order_id ?: 'toyyibpay_' . time()
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