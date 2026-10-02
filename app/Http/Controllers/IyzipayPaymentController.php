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
use Iyzipay\Options;
use Iyzipay\Model\CheckoutForm;
use Iyzipay\Model\CheckoutFormInitialize;
use Iyzipay\Request\CreateCheckoutFormInitializeRequest;
use Iyzipay\Model\Locale;
use Iyzipay\Model\Currency;
use Iyzipay\Model\PaymentGroup;
use Iyzipay\Model\BasketItemType;
use Iyzipay\Model\BasketItem;
use Iyzipay\Model\Buyer;
use Iyzipay\Model\Address;
use Iyzipay\Request\RetrieveCheckoutFormRequest;

class IyzipayPaymentController extends Controller
{
    private function getIyzipayOptions($settings)
    {
        $options = new Options();
        $options->setApiKey($settings['iyzipay_public_key']);
        $options->setSecretKey($settings['iyzipay_secret_key']);
        $options->setBaseUrl($settings['iyzipay_mode'] === 'live' 
            ? 'https://api.iyzipay.com' 
            : 'https://sandbox-api.iyzipay.com');
        
        return $options;
    }

    public function processPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'token' => 'required|string',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['iyzipay_secret_key']) || !isset($settings['payment_settings']['iyzipay_public_key'])) {
                return back()->withErrors(['error' => __('Iyzipay not configured')]);
            }

            // Retrieve payment result from Iyzipay
            $paymentResult = $this->retrieveIyzipayPayment($validated['token'], $settings['payment_settings']);

            if ($paymentResult && $paymentResult->getPaymentStatus() === 'SUCCESS') {
                processPaymentSuccess([
                    'user_id' => auth()->id(),
                    'plan_id' => $plan->id,
                    'billing_cycle' => $validated['billing_cycle'],
                    'payment_method' => 'iyzipay',
                    'coupon_code' => $validated['coupon_code'] ?? null,
                    'payment_id' => $paymentResult->getPaymentId(),
                ]);

                return back()->with('success', __('Payment successful and plan activated'));
            }

            return back()->withErrors(['error' => __('Payment failed or cancelled')]);

        } catch (\Exception $e) {
            return handlePaymentError($e, 'iyzipay');
        }
    }

    public function createPaymentForm(Request $request)
    {
        $validated = validatePaymentRequest($request);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['iyzipay_secret_key']) || !isset($settings['payment_settings']['iyzipay_public_key'])) {
                return response()->json(['error' => __('Iyzipay not configured')], 400);
            }

            $user = auth()->user();
            $conversationId = 'plan_' . $plan->id . '_' . $user->id . '_' . time();
            $options = $this->getIyzipayOptions($settings['payment_settings']);

            // Create checkout form initialize request
            $checkoutRequest = new CreateCheckoutFormInitializeRequest();
            $checkoutRequest->setLocale(Locale::EN);
            $checkoutRequest->setConversationId($conversationId);
            $checkoutRequest->setPrice(number_format($pricing['final_price'], 2, '.', ''));
            $checkoutRequest->setPaidPrice(number_format($pricing['final_price'], 2, '.', ''));
            $checkoutRequest->setCurrency(Currency::USD);
            $checkoutRequest->setBasketId('plan_' . $plan->id);
            $checkoutRequest->setPaymentGroup(PaymentGroup::SUBSCRIPTION);
            $checkoutRequest->setCallbackUrl(route('iyzipay.success', [
                'plan_id' => $plan->id,
                'user_id' => $user->id,
                'billing_cycle' => $validated['billing_cycle'],
                'coupon_code' => $validated['coupon_code'] ?? ''
            ]));
            $checkoutRequest->setEnabledInstallments([1]);

            // Set buyer information
            $buyer = new Buyer();
            $buyer->setId($user->id);
            $buyer->setName($user->name ?? 'Customer');
            $buyer->setSurname('User');
            $buyer->setGsmNumber('+1234567890');
            $buyer->setEmail($user->email);
            $buyer->setIdentityNumber('11111111111');
            $buyer->setLastLoginDate(now()->format('Y-m-d H:i:s'));
            $buyer->setRegistrationDate($user->created_at->format('Y-m-d H:i:s'));
            $buyer->setRegistrationAddress('123 Main Street');
            $buyer->setIp($request->ip());
            $buyer->setCity('New York');
            $buyer->setCountry('United States');
            $buyer->setZipCode('10001');
            $checkoutRequest->setBuyer($buyer);

            // Set shipping address
            $shippingAddress = new Address();
            $shippingAddress->setContactName($user->name ?? 'Customer User');
            $shippingAddress->setCity('New York');
            $shippingAddress->setCountry('United States');
            $shippingAddress->setAddress('123 Main Street');
            $shippingAddress->setZipCode('10001');
            $checkoutRequest->setShippingAddress($shippingAddress);

            // Set billing address
            $billingAddress = new Address();
            $billingAddress->setContactName($user->name ?? 'Customer User');
            $billingAddress->setCity('New York');
            $billingAddress->setCountry('United States');
            $billingAddress->setAddress('123 Main Street');
            $billingAddress->setZipCode('10001');
            $checkoutRequest->setBillingAddress($billingAddress);

            // Set basket items
            $basketItem = new BasketItem();
            $basketItem->setId($plan->id);
            $basketItem->setName($plan->name);
            $basketItem->setCategory1('Subscription');
            $basketItem->setItemType(BasketItemType::VIRTUAL);
            $basketItem->setPrice(number_format($pricing['final_price'], 2, '.', ''));
            $basketItems = [$basketItem];
            $checkoutRequest->setBasketItems($basketItems);

            // Initialize checkout form
            $checkoutFormInitialize = CheckoutFormInitialize::create($checkoutRequest, $options);

            if ($checkoutFormInitialize->getStatus() === 'success') {
                return response()->json([
                    'success' => true,
                    'redirect_url' => $checkoutFormInitialize->getPaymentPageUrl(),
                    'token' => $checkoutFormInitialize->getToken()
                ]);
            } else {
                return response()->json(['error' => $checkoutFormInitialize->getErrorMessage()], 400);
            }

        } catch (\Exception $e) {
            return response()->json(['error' => __('Payment form creation failed')], 500);
        }
    }

    public function success(Request $request)
    {
        try {
            $token = $request->input('token');
            $planId = $request->input('plan_id');
            $userId = $request->input('user_id');
            $billingCycle = $request->input('billing_cycle', 'monthly');
            $couponCode = $request->input('coupon_code');
            
            if (!$token || !$planId || !$userId) {
                return redirect()->route('plans.index')->withErrors(['error' => __('Invalid payment response')]);
            }

            $plan = Plan::find($planId);
            $user = User::find($userId);
            
            if (!$plan || !$user) {
                return redirect()->route('plans.index')->withErrors(['error' => __('Invalid plan or user')]);
            }

            // Get settings without authentication dependency
            $superAdmin = User::where('type', 'superadmin')->first();
            $settings = $superAdmin ? getPaymentGatewaySettings($superAdmin->id) : getPaymentGatewaySettings();
            
            // Retrieve payment result from Iyzipay
            $paymentResult = $this->retrieveIyzipayPayment($token, $settings['payment_settings']);
            
            if ($paymentResult && $paymentResult->getPaymentStatus() === 'SUCCESS') {
                processPaymentSuccess([
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'billing_cycle' => $billingCycle,
                    'payment_method' => 'iyzipay',
                    'coupon_code' => $couponCode,
                    'payment_id' => $paymentResult->getPaymentId(),
                ]);
                
                // Log the user in if not already authenticated
                if (!auth()->check()) {
                    auth()->login($user);
                }
                
                return redirect()->route('plans.index')->with('success', __('Payment completed successfully and plan activated'));
            }

            return redirect()->route('plans.index')->withErrors(['error' => __('Payment failed or cancelled')]);

        } catch (\Exception $e) {
            return redirect()->route('plans.index')->withErrors(['error' => __('Payment processing failed')]);
        }
    }

    public function callback(Request $request)
    {
        try {
            $token = $request->input('token');
            
            if ($token) {
                // Simple callback acknowledgment
                return response()->json(['status' => 'success']);
            }

            return response()->json(['error' => 'Invalid token'], 400);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Callback processing failed'], 500);
        }
    }

    public function createInvoicePayment(Request $request)
    {
        $validated = $request->validate([
            'invoice_token' => 'required|string',
            'amount' => 'required|numeric|min:0.01'
        ]);

        try {
            $invoice = Invoice::where('payment_token', $validated['invoice_token'])->firstOrFail();

            $paymentSettings = PaymentSetting::where('user_id', $invoice->created_by)
                ->whereIn('key', ['iyzipay_public_key', 'iyzipay_secret_key', 'iyzipay_mode', 'is_iyzipay_enabled'])
                ->pluck('value', 'key')
                ->toArray();

            if (($paymentSettings['is_iyzipay_enabled'] ?? '0') !== '1') {
                throw new \Exception('Iyzipay payment method is not enabled');
            }

            if (empty($paymentSettings['iyzipay_public_key']) || empty($paymentSettings['iyzipay_secret_key'])) {
                throw new \Exception('Iyzipay credentials not configured');
            }

            $conversationId = 'invoice_' . $invoice->id . '_' . time();
            $options = $this->getIyzipayOptions($paymentSettings);

            $checkoutRequest = new CreateCheckoutFormInitializeRequest();
            $checkoutRequest->setLocale(Locale::EN);
            $checkoutRequest->setConversationId($conversationId);
            $checkoutRequest->setPrice(number_format($validated['amount'], 2, '.', ''));
            $checkoutRequest->setPaidPrice(number_format($validated['amount'], 2, '.', ''));
            $checkoutRequest->setCurrency(Currency::USD);
            $checkoutRequest->setBasketId('invoice_' . $invoice->id);
            $checkoutRequest->setPaymentGroup(PaymentGroup::PRODUCT);
            $checkoutRequest->setCallbackUrl(route('iyzipay.invoice.success') . '?invoice_id=' . $invoice->id);
            $checkoutRequest->setEnabledInstallments([1]);

            $buyer = new Buyer();
            $buyer->setId($invoice->client->id ?? 1);
            $buyer->setName($invoice->client->first_name ?? 'Customer');
            $buyer->setSurname($invoice->client->last_name ?? 'User');
            $buyer->setGsmNumber('+1234567890');
            $buyer->setEmail($invoice->client->email ?? 'customer@example.com');
            $buyer->setIdentityNumber('11111111111');
            $buyer->setLastLoginDate(now()->format('Y-m-d H:i:s'));
            $buyer->setRegistrationDate(now()->format('Y-m-d H:i:s'));
            $buyer->setRegistrationAddress('123 Main Street');
            $buyer->setIp($request->ip());
            $buyer->setCity('New York');
            $buyer->setCountry('United States');
            $buyer->setZipCode('10001');
            $checkoutRequest->setBuyer($buyer);

            $shippingAddress = new Address();
            $shippingAddress->setContactName(($invoice->client->first_name ?? 'Customer') . ' ' . ($invoice->client->last_name ?? 'User'));
            $shippingAddress->setCity('New York');
            $shippingAddress->setCountry('United States');
            $shippingAddress->setAddress('123 Main Street');
            $shippingAddress->setZipCode('10001');
            $checkoutRequest->setShippingAddress($shippingAddress);

            $billingAddress = new Address();
            $billingAddress->setContactName(($invoice->client->first_name ?? 'Customer') . ' ' . ($invoice->client->last_name ?? 'User'));
            $billingAddress->setCity('New York');
            $billingAddress->setCountry('United States');
            $billingAddress->setAddress('123 Main Street');
            $billingAddress->setZipCode('10001');
            $checkoutRequest->setBillingAddress($billingAddress);

            $basketItem = new BasketItem();
            $basketItem->setId($invoice->id);
            $basketItem->setName('Invoice #' . $invoice->invoice_number);
            $basketItem->setCategory1('Invoice');
            $basketItem->setItemType(BasketItemType::VIRTUAL);
            $basketItem->setPrice(number_format($validated['amount'], 2, '.', ''));
            $checkoutRequest->setBasketItems([$basketItem]);

            $checkoutFormInitialize = CheckoutFormInitialize::create($checkoutRequest, $options);

            if ($checkoutFormInitialize->getStatus() === 'success') {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => true,
                        'redirect_url' => $checkoutFormInitialize->getPaymentPageUrl()
                    ]);
                }
                
                return redirect()->away($checkoutFormInitialize->getPaymentPageUrl());
            }

            throw new \Exception($checkoutFormInitialize->getErrorMessage() ?? 'Payment initialization failed');

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

    public function invoiceSuccess(Request $request)
    {
        try {
            $requestToken = $request->input('token');
            $invoiceId = $request->input('invoice_id');
            
            if (!$invoiceId) {
                return $this->redirectToInvoicePayment($invoiceId, 'Invalid invoice');
            }
            
            $invoice = Invoice::findOrFail($invoiceId);

            if (!$requestToken) {
                return $this->redirectToInvoicePayment($invoiceId, 'Invalid payment token');
            }

            $paymentSettings = PaymentSetting::where('user_id', $invoice->created_by)
                ->whereIn('key', ['iyzipay_public_key', 'iyzipay_secret_key', 'iyzipay_mode'])
                ->pluck('value', 'key')
                ->toArray();

            $paymentResult = $this->retrieveIyzipayPayment($requestToken, $paymentSettings);

            if ($paymentResult && $paymentResult->getPaymentStatus() === 'SUCCESS') {
                $existingPayment = Payment::where('invoice_id', $invoice->id)
                    ->where('payment_method', 'iyzipay')
                    ->where('transaction_id', $paymentResult->getPaymentId())
                    ->first();
                    
                if (!$existingPayment) {
                    $invoice->createPaymentRecord(
                        $paymentResult->getPaidPrice(),
                        'iyzipay',
                        $paymentResult->getPaymentId()
                    );
                }

                return $this->redirectToInvoicePayment($invoiceId, 'Payment completed successfully!', 'success');
            }

            return $this->redirectToInvoicePayment($invoiceId, 'Payment verification failed');
                
        } catch (\Exception $e) {
            return $this->redirectToInvoicePayment($invoiceId ?? null, 'Payment processing failed');
        }
    }

    private function redirectToInvoicePayment($invoiceId, $message, $type = 'error')
    {
        $url = $invoiceId 
            ? url("/invoices/{$invoiceId}?payment_status={$type}&message=" . urlencode($message))
            : url('/');
            
        return redirect()->away($url);
    }

    public function invoiceCallback(Request $request)
    {
        try {
            $requestToken = $request->input('token');
            $invoiceId = $request->input('invoice_id');
            
            if (!$requestToken || !$invoiceId) {
                return response('Invalid parameters', 400);
            }

            $invoice = Invoice::find($invoiceId);
            if (!$invoice) {
                return response('Invoice not found', 404);
            }

            $paymentSettings = PaymentSetting::where('user_id', $invoice->created_by)
                ->whereIn('key', ['iyzipay_public_key', 'iyzipay_secret_key', 'iyzipay_mode'])
                ->pluck('value', 'key')
                ->toArray();

            $paymentResult = $this->retrieveIyzipayPayment($requestToken, $paymentSettings);

            if ($paymentResult && $paymentResult->getPaymentStatus() === 'SUCCESS') {
                // Check for duplicate payments
                $existingPayment = Payment::where('invoice_id', $invoice->id)
                    ->where('payment_method', 'iyzipay')
                    ->where('transaction_id', $paymentResult->getPaymentId())
                    ->first();
                    
                if (!$existingPayment) {
                    $invoice->createPaymentRecord(
                        $paymentResult->getPaidPrice(),
                        'iyzipay',
                        $paymentResult->getPaymentId()
                    );
                }
            }

            return response('OK', 200);
        } catch (\Exception $e) {
            return response('Error', 500);
        }
    }

    private function retrieveIyzipayPayment($token, $settings)
    {
        try {
            $options = $this->getIyzipayOptions($settings);
            
            $request = new RetrieveCheckoutFormRequest();
            $request->setToken($token);
            
            $checkoutForm = CheckoutForm::retrieve($request, $options);
            
            return $checkoutForm;
        } catch (\Exception $e) {
            return null;
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
                ->whereIn('key', ['iyzipay_public_key', 'iyzipay_secret_key', 'iyzipay_mode', 'is_iyzipay_enabled'])
                ->pluck('value', 'key')
                ->toArray();

            if (($paymentSettings['is_iyzipay_enabled'] ?? '0') !== '1') {
                return response()->json(['error' => 'Iyzipay payment method is not enabled'], 400);
            }

            if (empty($paymentSettings['iyzipay_public_key']) || empty($paymentSettings['iyzipay_secret_key'])) {
                return response()->json(['error' => 'Iyzipay credentials not configured'], 400);
            }

            $conversationId = 'invoice_' . $invoice->id . '_' . time();
            $options = $this->getIyzipayOptions($paymentSettings);

            $checkoutRequest = new CreateCheckoutFormInitializeRequest();
            $checkoutRequest->setLocale(Locale::EN);
            $checkoutRequest->setConversationId($conversationId);
            $checkoutRequest->setPrice(number_format($request->amount, 2, '.', ''));
            $checkoutRequest->setPaidPrice(number_format($request->amount, 2, '.', ''));
            $checkoutRequest->setCurrency(Currency::USD);
            $checkoutRequest->setBasketId('invoice_' . $invoice->id);
            $checkoutRequest->setPaymentGroup(PaymentGroup::PRODUCT);
            $checkoutRequest->setCallbackUrl(route('iyzipay.invoice.success.link', ['token' => $token]));
            $checkoutRequest->setEnabledInstallments([1]);

            $buyer = new Buyer();
            $buyer->setId($invoice->client->id ?? 1);
            $buyer->setName($invoice->client->first_name ?? 'Customer');
            $buyer->setSurname($invoice->client->last_name ?? 'User');
            $buyer->setGsmNumber('+1234567890');
            $buyer->setEmail($invoice->client->email ?? 'customer@example.com');
            $buyer->setIdentityNumber('11111111111');
            $buyer->setLastLoginDate(now()->format('Y-m-d H:i:s'));
            $buyer->setRegistrationDate(now()->format('Y-m-d H:i:s'));
            $buyer->setRegistrationAddress('123 Main Street');
            $buyer->setIp($request->ip());
            $buyer->setCity('New York');
            $buyer->setCountry('United States');
            $buyer->setZipCode('10001');
            $checkoutRequest->setBuyer($buyer);

            $shippingAddress = new Address();
            $shippingAddress->setContactName(($invoice->client->first_name ?? 'Customer') . ' ' . ($invoice->client->last_name ?? 'User'));
            $shippingAddress->setCity('New York');
            $shippingAddress->setCountry('United States');
            $shippingAddress->setAddress('123 Main Street');
            $shippingAddress->setZipCode('10001');
            $checkoutRequest->setShippingAddress($shippingAddress);

            $billingAddress = new Address();
            $billingAddress->setContactName(($invoice->client->first_name ?? 'Customer') . ' ' . ($invoice->client->last_name ?? 'User'));
            $billingAddress->setCity('New York');
            $billingAddress->setCountry('United States');
            $billingAddress->setAddress('123 Main Street');
            $billingAddress->setZipCode('10001');
            $checkoutRequest->setBillingAddress($billingAddress);

            $basketItem = new BasketItem();
            $basketItem->setId($invoice->id);
            $basketItem->setName('Invoice #' . $invoice->invoice_number);
            $basketItem->setCategory1('Invoice');
            $basketItem->setItemType(BasketItemType::VIRTUAL);
            $basketItem->setPrice(number_format($request->amount, 2, '.', ''));
            $checkoutRequest->setBasketItems([$basketItem]);

            $checkoutFormInitialize = CheckoutFormInitialize::create($checkoutRequest, $options);

            if ($checkoutFormInitialize->getStatus() === 'success') {
                return response()->json([
                    'success' => true,
                    'redirect_url' => $checkoutFormInitialize->getPaymentPageUrl()
                ]);
            }

            return response()->json(['error' => $checkoutFormInitialize->getErrorMessage()], 400);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function invoiceSuccessFromLink(Request $request, $token)
    {
        try {
            $requestToken = $request->input('token');
            $invoice = Invoice::where('payment_token', $token)->firstOrFail();

            if ($requestToken) {
                $paymentSettings = PaymentSetting::where('user_id', $invoice->created_by)
                    ->whereIn('key', ['iyzipay_public_key', 'iyzipay_secret_key', 'iyzipay_mode'])
                    ->pluck('value', 'key')
                    ->toArray();

                $paymentResult = $this->retrieveIyzipayPayment($requestToken, $paymentSettings);

                if ($paymentResult && $paymentResult->getPaymentStatus() === 'SUCCESS') {
                    $invoice->createPaymentRecord(
                        $paymentResult->getPaidPrice(),
                        'iyzipay',
                        $paymentResult->getPaymentId()
                    );

                    return redirect()->route('invoices.payment', $token)
                        ->with('success', 'Payment processed successfully.');
                }
            }

            return redirect()->route('invoices.payment', $token)
                ->with('error', 'Payment verification failed');
        } catch (\Exception $e) {
            return redirect()->route('invoices.payment', $token)
                ->with('error', 'Payment processing failed');
        }
    }
}