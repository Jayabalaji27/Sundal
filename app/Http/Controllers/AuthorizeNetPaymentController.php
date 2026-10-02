<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Invoice;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

class AuthorizeNetPaymentController extends Controller
{
    private const SUPPORTED_COUNTRIES = ['US', 'CA', 'GB', 'AU'];
    private const SUPPORTED_CURRENCIES = [
        'USD', 'CAD', 'CHF', 'DKK', 'EUR', 'GBP', 'NOK', 'PLN', 'SEK', 'AUD', 'NZD'
    ];

    public function createPaymentForm(Request $request)
    {
        $validated = validatePaymentRequest($request);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['authorizenet_merchant_id']) || 
                !isset($settings['payment_settings']['authorizenet_transaction_key'])) {
                return response()->json(['error' => 'AuthorizeNet not properly configured'], 400);
            }

            $currency = $settings['general_settings']['currency'] ?? 'USD';
            
            if (!in_array($currency, self::SUPPORTED_CURRENCIES)) {
                $currency = 'USD';
            }

            return response()->json([
                'success' => true,
                'merchant_id' => $settings['payment_settings']['authorizenet_merchant_id'],
                'amount' => number_format($pricing['final_price'], 2, '.', ''),
                'currency' => $currency,
                'is_sandbox' => $settings['payment_settings']['authorizenet_mode'] === 'sandbox',
                'supported_countries' => self::SUPPORTED_COUNTRIES,
                'supported_currencies' => self::SUPPORTED_CURRENCIES,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => __('Payment form creation failed')], 500);
        }
    }

    public function processPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'card_number' => 'required|string',
            'expiry_month' => 'required|string|size:2',
            'expiry_year' => 'required|string|size:2',
            'cvv' => 'required|string|min:3|max:4',
            'cardholder_name' => 'required|string|min:2|max:50',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['authorizenet_merchant_id']) || 
                !isset($settings['payment_settings']['authorizenet_transaction_key'])) {
                return back()->withErrors(['error' => __('AuthorizeNet not properly configured')]);
            }

            if ($pricing['final_price'] < 0.50) {
                return back()->withErrors(['error' => __('Minimum payment amount is $0.50')]);
            }

            $result = $this->createAuthorizeNetTransaction($validated, $pricing, $settings);

            if ($result['success']) {
                processPaymentSuccess([
                    'user_id' => auth()->id(),
                    'plan_id' => $plan->id,
                    'billing_cycle' => $validated['billing_cycle'],
                    'payment_method' => 'authorizenet',
                    'coupon_code' => $validated['coupon_code'] ?? null,
                    'payment_id' => $result['transaction_id'],
                ]);

                return back()->with('success', __('Payment successful and plan activated'));
            }
            
            return back()->withErrors(['error' => $result['error']]);

        } catch (\Exception $e) {
            return back()->withErrors(['error' => __('Payment processing failed. Please try again.')]);
        }
    }

    private function createAuthorizeNetTransaction($paymentData, $pricing, $settings)
    {
        try {
            $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
            $merchantAuthentication->setName($settings['payment_settings']['authorizenet_merchant_id']);
            $merchantAuthentication->setTransactionKey($settings['payment_settings']['authorizenet_transaction_key']);

            $creditCard = new AnetAPI\CreditCardType();
            $creditCard->setCardNumber(preg_replace('/\s+/', '', $paymentData['card_number']));
            
            $expiryYear = 2000 + intval($paymentData['expiry_year']);
            $expiryMonth = str_pad($paymentData['expiry_month'], 2, '0', STR_PAD_LEFT);
            $creditCard->setExpirationDate($expiryYear . '-' . $expiryMonth);
            $creditCard->setCardCode($paymentData['cvv']);

            $paymentOne = new AnetAPI\PaymentType();
            $paymentOne->setCreditCard($creditCard);

            $order = new AnetAPI\OrderType();
            $order->setInvoiceNumber('INV-' . time());
            $order->setDescription('Plan Subscription Payment');

            $customer = new AnetAPI\CustomerDataType();
            $customer->setType('individual');
            $customer->setId(auth()->id());
            $customer->setEmail(auth()->user()->email);

            $billTo = new AnetAPI\CustomerAddressType();
            $billTo->setFirstName(explode(' ', $paymentData['cardholder_name'])[0]);
            $billTo->setLastName(implode(' ', array_slice(explode(' ', $paymentData['cardholder_name']), 1)) ?: 'Customer');
            $billTo->setCompany(auth()->user()->name ?? '');
            $billTo->setAddress('N/A');
            $billTo->setCity('N/A');
            $billTo->setState('N/A');
            $billTo->setZip('00000');
            $billTo->setCountry('US');

            $transactionRequestType = new AnetAPI\TransactionRequestType();
            $transactionRequestType->setTransactionType('authCaptureTransaction');
            $transactionRequestType->setAmount(number_format($pricing['final_price'], 2, '.', ''));
            $transactionRequestType->setPayment($paymentOne);
            $transactionRequestType->setOrder($order);
            $transactionRequestType->setBillTo($billTo);
            $transactionRequestType->setCustomer($customer);

            $merchantDefinedField1 = new AnetAPI\UserFieldType();
            $merchantDefinedField1->setName('plan_id');
            $merchantDefinedField1->setValue($paymentData['plan_id']);
            
            $merchantDefinedField2 = new AnetAPI\UserFieldType();
            $merchantDefinedField2->setName('user_id');
            $merchantDefinedField2->setValue(auth()->id());
            
            $transactionRequestType->setUserFields([$merchantDefinedField1, $merchantDefinedField2]);

            $request = new AnetAPI\CreateTransactionRequest();
            $request->setMerchantAuthentication($merchantAuthentication);
            $request->setTransactionRequest($transactionRequestType);

            $controller = new AnetController\CreateTransactionController($request);
            
            $environment = ($settings['payment_settings']['authorizenet_mode'] === 'sandbox') 
                ? \net\authorize\api\constants\ANetEnvironment::SANDBOX 
                : \net\authorize\api\constants\ANetEnvironment::PRODUCTION;
                
            $response = $controller->executeWithApiResponse($environment);

            return $this->handleAuthorizeNetResponse($response);
            
        } catch (\Exception $e) {
            \Log::error('AuthorizeNet transaction error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => __('Transaction processing failed. Please check your card details and try again.'),
                'transaction_id' => null
            ];
        }
    }

    private function handleAuthorizeNetResponse($response)
    {
        if ($response === null) {
            return [
                'success' => false,
                'error' => __('No response received from payment gateway'),
                'transaction_id' => null
            ];
        }

        $messages = $response->getMessages();
        
        if ($messages->getResultCode() !== 'Ok') {
            $errorMessage = __('Payment gateway error');
            if ($messages->getMessage() && count($messages->getMessage()) > 0) {
                $errorMessage = $messages->getMessage()[0]->getText();
            }
            
            return [
                'success' => false,
                'error' => $this->getFriendlyErrorMessage($errorMessage),
                'transaction_id' => null
            ];
        }

        $tresponse = $response->getTransactionResponse();
        
        if ($tresponse === null) {
            return [
                'success' => false,
                'error' => __('Invalid transaction response'),
                'transaction_id' => null
            ];
        }

        $responseCode = $tresponse->getResponseCode();
        
        switch ($responseCode) {
            case '1':
                $transId = $tresponse->getTransId();
                if (!$transId || $transId === '0') {
                    $transId = 'authnet_' . time();
                }
                return [
                    'success' => true,
                    'error' => null,
                    'transaction_id' => $transId
                ];
                
            case '2':
                $errorMessage = 'Transaction declined';
                if ($tresponse->getErrors() && count($tresponse->getErrors()) > 0) {
                    $errorMessage = $tresponse->getErrors()[0]->getErrorText();
                }
                                
                return [
                    'success' => false,
                    'error' => $this->getFriendlyErrorMessage($errorMessage),
                    'transaction_id' => null
                ];
                
            case '3':
                $errorMessage = 'Transaction error';
                if ($tresponse->getErrors() && count($tresponse->getErrors()) > 0) {
                    $errorMessage = $tresponse->getErrors()[0]->getErrorText();
                }
                                
                return [
                    'success' => false,
                    'error' => $this->getFriendlyErrorMessage($errorMessage),
                    'transaction_id' => null
                ];
                
            case '4':
                return [
                    'success' => false,
                    'error' => __('Transaction is being reviewed. Please contact support.'),
                    'transaction_id' => $tresponse->getTransId()
                ];
                
            default:
                return [
                    'success' => false,
                    'error' => __('Unknown transaction response'),
                    'transaction_id' => null
                ];
        }
    }

    private function getFriendlyErrorMessage($errorMessage)
    {
        $friendlyMessages = [
            __('The credit card number is invalid') => __('Please check your card number and try again.'),
            __('The credit card has expired') => __('Your card has expired. Please use a different card.'),
            __('The credit card expiration date is invalid') => __('Please check the expiration date and try again.'),
            __('The transaction cannot be found') => __('Transaction not found. Please try again.'),
            __('A duplicate transaction has been submitted') => __('This transaction was already processed.'),
            __('The amount is invalid') => __('Invalid payment amount.'),
            __('This transaction has been declined') => __('Your card was declined. Please try a different payment method.'),
            __('Insufficient funds') => __('Insufficient funds. Please try a different card.'),
            __('The merchant does not accept this type of credit card') => __('This card type is not accepted.'),
            __('The transaction has been declined because of an AVS mismatch') => __('Address verification failed. Please check your billing address.'),
            __('The transaction has been declined because the CVV2 value is invalid') => __('Invalid security code. Please check your CVV.'),
        ];
        
        foreach ($friendlyMessages as $original => $friendly) {
            if (stripos($errorMessage, $original) !== false) {
                return $friendly;
            }
        }
        
        return __('Payment processing failed. Please check your card details and try again.');
    }

    private function createInvoiceAuthorizeNetTransaction($paymentData, $pricing, $settings)
    {
        try {
            $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
            $merchantAuthentication->setName($settings['authorizenet_merchant_id']);
            $merchantAuthentication->setTransactionKey($settings['authorizenet_transaction_key']);

            $creditCard = new AnetAPI\CreditCardType();
            $creditCard->setCardNumber(preg_replace('/\s+/', '', $paymentData['card_number']));
            
            $expiryYear = 2000 + intval($paymentData['expiry_year']);
            $expiryMonth = str_pad($paymentData['expiry_month'], 2, '0', STR_PAD_LEFT);
            $creditCard->setExpirationDate($expiryYear . '-' . $expiryMonth);
            $creditCard->setCardCode($paymentData['cvv']);

            $paymentOne = new AnetAPI\PaymentType();
            $paymentOne->setCreditCard($creditCard);

            $order = new AnetAPI\OrderType();
            $order->setInvoiceNumber('INV-' . time());
            $order->setDescription('Invoice Payment');

            $customer = new AnetAPI\CustomerDataType();
            $customer->setType('individual');
            
            if (auth()->check()) {
                $customer->setId(auth()->id());
                $customer->setEmail(auth()->user()->email);
            } else {
                $customer->setId('guest_' . time());
                $customer->setEmail('guest@example.com');
            }

            $billTo = new AnetAPI\CustomerAddressType();
            $billTo->setFirstName(explode(' ', $paymentData['cardholder_name'])[0]);
            $billTo->setLastName(implode(' ', array_slice(explode(' ', $paymentData['cardholder_name']), 1)) ?: 'Customer');
            $billTo->setCompany(auth()->check() ? (auth()->user()->name ?? '') : 'Guest');
            $billTo->setAddress('N/A');
            $billTo->setCity('N/A');
            $billTo->setState('N/A');
            $billTo->setZip('00000');
            $billTo->setCountry('US');

            $transactionRequestType = new AnetAPI\TransactionRequestType();
            $transactionRequestType->setTransactionType('authCaptureTransaction');
            $transactionRequestType->setAmount(number_format($pricing['final_price'], 2, '.', ''));
            $transactionRequestType->setPayment($paymentOne);
            $transactionRequestType->setOrder($order);
            $transactionRequestType->setBillTo($billTo);
            $transactionRequestType->setCustomer($customer);

            $merchantDefinedField1 = new AnetAPI\UserFieldType();
            $merchantDefinedField1->setName('invoice_token');
            $merchantDefinedField1->setValue($paymentData['invoice_token']);
            
            $merchantDefinedField2 = new AnetAPI\UserFieldType();
            $merchantDefinedField2->setName('user_id');
            $merchantDefinedField2->setValue(auth()->id() ?? 'guest');
            
            $transactionRequestType->setUserFields([$merchantDefinedField1, $merchantDefinedField2]);

            $request = new AnetAPI\CreateTransactionRequest();
            $request->setMerchantAuthentication($merchantAuthentication);
            $request->setTransactionRequest($transactionRequestType);

            $controller = new AnetController\CreateTransactionController($request);
            
            $environment = ($settings['authorizenet_mode'] === 'sandbox') 
                ? \net\authorize\api\constants\ANetEnvironment::SANDBOX 
                : \net\authorize\api\constants\ANetEnvironment::PRODUCTION;
                
            $response = $controller->executeWithApiResponse($environment);

            return $this->handleAuthorizeNetResponse($response);
            
        } catch (\Exception $e) {
            \Log::error('AuthorizeNet invoice error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return [
                'success' => false,
                'error' => __('Transaction processing failed. Please check your card details and try again.'),
                'transaction_id' => null
            ];
        }
    }

    public function processInvoicePayment(Request $request)
    {
        try {
            $validated = $request->validate([
                'invoice_token' => 'required|string',
                'amount' => 'required|numeric|min:0.50',
                'card_number' => 'required|string',
                'expiry_month' => 'required|string|size:2',
                'expiry_year' => 'required|string|size:2',
                'cvv' => 'required|string|min:3|max:4',
                'cardholder_name' => 'required|string|min:2|max:50',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 400);
            }
            throw $e;
        }

        try {
            $invoice = Invoice::with(['creator', 'client'])->where('payment_token', $validated['invoice_token'])->firstOrFail();

            $settings = PaymentSetting::where('user_id', $invoice->created_by)
                ->pluck('value', 'key')
                ->toArray();

            if (!isset($settings['authorizenet_merchant_id']) || !isset($settings['authorizenet_transaction_key'])) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => __('Authorize.Net payment not configured')
                    ], 400);
                }
                return back()->withErrors(['error' => __('Authorize.Net payment not configured')]);
            }

            $pricing = ['final_price' => $validated['amount']];
            
            $result = $this->createInvoiceAuthorizeNetTransaction($validated, $pricing, $settings);

            if ($result['success'] && isset($result['transaction_id'])) {
                $invoice->createPaymentRecord($validated['amount'], 'authorizenet', $result['transaction_id']);

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => __('Payment completed successfully!'),
                        'redirect_url' => route('invoices.show', $invoice->id) . '?payment_success=true'
                    ]);
                }

                return redirect()->route('invoices.show', $invoice->id)
                    ->with('success', __('Payment completed successfully!'));
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'] ?? __('Payment processing failed')
                ], 400);
            }

            return back()->withErrors(['error' => $result['error'] ?? __('Payment processing failed')]);

        } catch (\Exception $e) {
            \Log::error('Invoice payment error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('Payment processing failed. Please try again or contact support.')
                ], 500);
            }
            
            return back()->withErrors(['error' => __('Payment processing failed. Please try again or contact support.')]);
        }
    }

    public function createInvoicePayment(Request $request)
    {
        $validated = $request->validate([
            'invoice_token' => 'required|string',
            'amount' => 'required|numeric|min:0.50'
        ]);

        try {
            $invoice = Invoice::with(['creator', 'client'])->where('payment_token', $validated['invoice_token'])->firstOrFail();
            
            $settings = PaymentSetting::where('user_id', $invoice->created_by)
                ->pluck('value', 'key')
                ->toArray();

            if (!isset($settings['authorizenet_merchant_id']) || !isset($settings['authorizenet_transaction_key'])) {
                return response()->json(['error' => 'AuthorizeNet not configured'], 400);
            }

            return response()->json([
                'success' => true,
                'merchant_id' => $settings['authorizenet_merchant_id'],
                'amount' => number_format($validated['amount'], 2, '.', ''),
                'currency' => 'USD',
                'is_sandbox' => $settings['authorizenet_mode'] === 'sandbox',
                'supported_countries' => self::SUPPORTED_COUNTRIES,
                'supported_currencies' => self::SUPPORTED_CURRENCIES,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function invoiceSuccess(Request $request)
    {
        try {
            $invoiceToken = $request->input('invoice_token');
            $amount = $request->input('amount');

            if ($invoiceToken && $amount) {
                $invoice = Invoice::with(['creator', 'client'])->where('payment_token', $invoiceToken)->first();

                if ($invoice && $invoice->creator) {
                    $invoice->createPaymentRecord($amount, 'authorizenet', 'authnet_' . time());

                    return redirect()->route('invoices.payment', $invoiceToken)
                        ->with('success', __('Payment successful'));
                }
            }

            return redirect()->route('home')
                ->with('error', __('Payment verification failed'));

        } catch (\Exception $e) {
            return redirect()->route('home')
                ->with('error', __('Payment processing failed'));
        }
    }

    public function testConnection(Request $request)
    {
        try {
            $settings = getPaymentGatewaySettings();
            
            if (!isset($settings['payment_settings']['authorizenet_merchant_id']) || 
                !isset($settings['payment_settings']['authorizenet_transaction_key'])) {
                return response()->json([
                    'success' => false,
                    'message' => __('AuthorizeNet credentials not configured')
                ]);
            }

            $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
            $merchantAuthentication->setName($settings['payment_settings']['authorizenet_merchant_id']);
            $merchantAuthentication->setTransactionKey($settings['payment_settings']['authorizenet_transaction_key']);

            $request = new AnetAPI\AuthenticateTestRequest();
            $request->setMerchantAuthentication($merchantAuthentication);

            $controller = new AnetController\AuthenticateTestController($request);
            
            $environment = ($settings['payment_settings']['authorizenet_mode'] === 'sandbox') 
                ? \net\authorize\api\constants\ANetEnvironment::SANDBOX 
                : \net\authorize\api\constants\ANetEnvironment::PRODUCTION;
                
            $response = $controller->executeWithApiResponse($environment);

            if ($response && $response->getMessages()->getResultCode() === 'Ok') {
                return response()->json([
                    'success' => true,
                    'message' => __('AuthorizeNet connection successful'),
                    'mode' => $settings['payment_settings']['authorizenet_mode']
                ]);
            } else {
                $errorMessage = __('Connection failed');
                if ($response && $response->getMessages()->getMessage()) {
                    $errorMessage = $response->getMessages()->getMessage()[0]->getText();
                }
                
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ]);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('Connection test failed: ') . $e->getMessage()
            ]);
        }
    }

    public function processInvoicePaymentFromLink(Request $request)
    {
        try {
            $validated = $request->validate([
                'invoice_token' => 'required|string',
                'amount' => 'required|numeric|min:0.50',
                'card_number' => 'required|string',
                'expiry_month' => 'required|string',
                'expiry_year' => 'required|string',
                'cvv' => 'required|string',
                'cardholder_name' => 'required|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        }

        try {
            $invoice = Invoice::with(['creator', 'client'])->where('payment_token', $validated['invoice_token'])->firstOrFail();

            if (!$invoice->creator) {
                return response()->json(['success' => false, 'message' => 'Invoice creator not found'], 400);
            }

            $paymentSettings = PaymentSetting::where('user_id', $invoice->created_by)
                ->pluck('value', 'key')
                ->toArray();

            $settings = [
                'authorizenet_merchant_id' => $paymentSettings['authorizenet_merchant_id'],
                'authorizenet_transaction_key' => $paymentSettings['authorizenet_transaction_key'],
                'authorizenet_mode' => $paymentSettings['authorizenet_mode'] ?? 'sandbox'
            ];

            $pricing = ['final_price' => $validated['amount']];
            
            $result = $this->createInvoiceAuthorizeNetTransaction($validated, $pricing, $settings);

            if ($result && $result['success'] && $result['transaction_id']) {
                $invoice->createPaymentRecord($validated['amount'], 'authorizenet', $result['transaction_id']);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment completed successfully.',
                    'redirect_url' => route('invoices.payment', $invoice->payment_token)
                ]);
            }

            return response()->json(['success' => false, 'message' => ($result['error'] ?? 'Payment processing failed')], 400);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Invoice not found'], 404);
        } catch (\Exception $e) {
            \Log::error('processInvoicePaymentFromLink error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function createInvoicePaymentFromLink(Request $request)
    {
        $validated = $request->validate([
            'invoice_token' => 'required|string',
            'amount' => 'required|numeric|min:0.50'
        ]);

        try {
            $invoice = Invoice::with(['creator', 'client'])->where('payment_token', $validated['invoice_token'])->firstOrFail();
            
            if (!$invoice->creator) {
                return response()->json(['error' => 'Invoice creator not found'], 400);
            }
            
            $paymentSettings = PaymentSetting::where('user_id', $invoice->created_by)
                ->whereIn('key', ['authorizenet_merchant_id', 'authorizenet_transaction_key', 'authorizenet_mode', 'is_authorizenet_enabled'])
                ->pluck('value', 'key')
                ->toArray();

            if (empty($paymentSettings['authorizenet_merchant_id']) || 
                empty($paymentSettings['authorizenet_transaction_key']) ||
                $paymentSettings['is_authorizenet_enabled'] !== '1') {
                return response()->json(['error' => 'AuthorizeNet not configured'], 400);
            }

            return response()->json([
                'success' => true,
                'merchant_id' => $paymentSettings['authorizenet_merchant_id'],
                'amount' => number_format($validated['amount'], 2, '.', ''),
                'currency' => 'USD',
                'is_sandbox' => ($paymentSettings['authorizenet_mode'] ?? 'sandbox') === 'sandbox',
                'supported_countries' => self::SUPPORTED_COUNTRIES,
                'supported_currencies' => self::SUPPORTED_CURRENCIES,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
