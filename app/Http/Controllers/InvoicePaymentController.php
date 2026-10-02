<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvoicePaymentController extends Controller
{
    public function show($token, Request $request)
    {
        $invoice = Invoice::where('payment_token', $token)
            ->with(['client', 'project', 'creator', 'workspace', 'items.task', 'payments'])
            ->firstOrFail();



        // Get company information
        $company = \App\Models\User::where('id', $invoice->created_by)
            ->where('type', 'company')
            ->select('id', 'name', 'email')
            ->first();
            
        // Get favicon and app name from settings table
        $settings = \App\Models\Setting::where('user_id', $invoice->created_by)
            ->whereIn('key', ['favicon', 'app_name'])
            ->pluck('value', 'key')
            ->toArray();
            
        $favicon = $settings['favicon'] ?? null;
        $appName = $settings['app_name'] ?? 'Taskly SaaS';
        $enabledGateways = $this->getEnabledPaymentGateways($invoice->created_by, $invoice->workspace_id);
        
        $paymentSettings = PaymentSetting::where('user_id', $invoice->created_by)
            ->whereIn('key', ['paypal_client_id', 'paystack_public_key', 'flutterwave_public_key', 'tap_public_key'])
            ->pluck('value', 'key')
            ->toArray();

        // Get invoice settings including currency settings from invoice creator's workspace
        $invoiceSettings = \App\Models\Setting::where('user_id', $invoice->created_by)
            ->where('workspace_id', $invoice->workspace_id)
            ->whereIn('key', ['invoice_template', 'invoice_qr_display', 'invoice_color', 'invoice_footer_title', 'invoice_footer_notes', 'invoice_logo', 'currencySymbolPosition', 'currencySymbolSpace', 'decimalSeparator', 'thousandsSeparator', 'decimalFormat', 'floatNumber', 'defaultCurrency'])
            ->pluck('value', 'key')
            ->toArray();

        // Resolve currency symbol from Currency model using defaultCurrency code
        $currencyCode = $invoiceSettings['defaultCurrency'] ?? 'USD';
        $currency = \App\Models\Currency::where('code', $currencyCode)->first();
        $invoiceSettings['currencySymbol'] = $currency ? $currency->symbol : '$';
            


        return Inertia::render('invoice/payment', [
            'invoice' => $invoice,
            'enabledGateways' => $enabledGateways,
            'remainingAmount' => $invoice->remaining_amount,
            'company' => $company,
            'favicon' => $favicon,
            'appName' => $appName,
            'paypalClientId' => $paymentSettings['paypal_client_id'] ?? null,
            'paystackPublicKey' => $paymentSettings['paystack_public_key'] ?? null,
            'flutterwavePublicKey' => $paymentSettings['flutterwave_public_key'] ?? null,
            'tapPublicKey' => $paymentSettings['tap_public_key'] ?? null,
            'invoiceSettings' => $invoiceSettings,
        ]);
    }

    public function preview($token)
    {
        $invoice = Invoice::where('payment_token', $token)
            ->with(['client', 'project', 'creator', 'workspace', 'items.task', 'payments'])
            ->firstOrFail();

        $invoiceData = $invoice->toArray();
        $taxRate = $invoice->tax_rate;
        if (is_string($taxRate)) {
            $taxRate = json_decode($taxRate, true) ?: [];
        }
        $invoiceData['tax_rate'] = $taxRate;

        // Get invoice settings including currency settings from invoice creator's workspace
        $invoiceSettings = \App\Models\Setting::where('user_id', $invoice->created_by)
            ->where('workspace_id', $invoice->workspace_id)
            ->whereIn('key', ['invoice_template', 'invoice_qr_display', 'invoice_color', 'invoice_footer_title', 'invoice_footer_notes', 'invoice_logo', 'currencySymbolPosition', 'currencySymbolSpace', 'decimalSeparator', 'thousandsSeparator', 'decimalFormat', 'floatNumber', 'defaultCurrency'])
            ->pluck('value', 'key')
            ->toArray();

        // Resolve currency symbol from Currency model using defaultCurrency code
        $currencyCode = $invoiceSettings['defaultCurrency'] ?? 'USD';
        $currency = \App\Models\Currency::where('code', $currencyCode)->first();
        $invoiceSettings['currencySymbol'] = $currency ? $currency->symbol : '$';

        return Inertia::render('invoices/Preview', [
            'invoice' => $invoiceData,
            'invoiceSettings' => $invoiceSettings,
        ]);
    }

    public function processPayment(Request $request, $token)
    {
        $invoice = Invoice::where('payment_token', $token)->firstOrFail();
        $remainingAmount = $invoice->remaining_amount ?: $invoice->total_amount;
        $pendingTotal = $invoice->payments()->where('status', 'pending')->sum('amount');
        $maxAmount = max(0, $remainingAmount - $pendingTotal);

        $request->validate([
            'payment_method' => 'required|string',
            'amount' => 'required|numeric|min:0.01|max:' . $maxAmount
        ]);

        if ($maxAmount <= 0) {
            return back()->withErrors(['amount' => __('A pending payment already covers the full due amount. Please wait for approval.')]);
        }

        $request->merge([
            'invoice_id' => $invoice->id,
            'invoice_token' => $token,
            'type' => 'invoice'
        ]);

        $paymentMethod = $request->payment_method;
        $controllerMap = [
            'bank' => '\App\Http\Controllers\BankPaymentController',
            'stripe' => '\App\Http\Controllers\StripePaymentController',
            'paypal' => '\App\Http\Controllers\PayPalPaymentController',
            'razorpay' => '\App\Http\Controllers\RazorpayController',
            'mercadopago' => '\App\Http\Controllers\MercadoPagoController',
            'paystack' => '\App\Http\Controllers\PaystackPaymentController',
            'flutterwave' => '\App\Http\Controllers\FlutterwavePaymentController',
            'paytabs' => '\App\Http\Controllers\PayTabsPaymentController',
            'skrill' => '\App\Http\Controllers\SkrillPaymentController',
            'coingate' => '\App\Http\Controllers\CoinGatePaymentController',
            'payfast' => '\App\Http\Controllers\PayfastPaymentController',
            'tap' => '\App\Http\Controllers\TapPaymentController',
            'xendit' => '\App\Http\Controllers\XenditPaymentController',
            'paytr' => '\App\Http\Controllers\PayTRPaymentController',
            'mollie' => '\App\Http\Controllers\MolliePaymentController',
            'toyyibpay' => '\App\Http\Controllers\ToyyibPayPaymentController',
            'cashfree' => '\App\Http\Controllers\CashfreeController',
            'khalti' => '\App\Http\Controllers\KhaltiPaymentController',
            'iyzipay' => '\App\Http\Controllers\IyzipayPaymentController',
            'benefit' => '\App\Http\Controllers\BenefitPaymentController',
            'ozow' => '\App\Http\Controllers\OzowPaymentController',
            'easebuzz' => '\App\Http\Controllers\EasebuzzPaymentController',
            'authorizenet' => '\App\Http\Controllers\AuthorizeNetPaymentController',
            'fedapay' => '\App\Http\Controllers\FedaPayPaymentController',
            'payhere' => '\App\Http\Controllers\PayHerePaymentController',
            'cinetpay' => '\App\Http\Controllers\CinetPayPaymentController',
            'paiement' => '\App\Http\Controllers\PaiementPaymentController',
            'yookassa' => '\App\Http\Controllers\YooKassaPaymentController',
            'aamarpay' => '\App\Http\Controllers\AamarpayPaymentController',
            'midtrans' => '\App\Http\Controllers\MidtransPaymentController',
        ];

        if (!isset($controllerMap[$paymentMethod])) {
            return back()->withErrors(['error' => 'Payment method not supported']);
        }

        try {
            $controller = app($controllerMap[$paymentMethod]);
            return $controller->processInvoicePayment($request, $invoice);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return back()->withErrors(['error' => __('Invoice not found. Please check the link and try again.')]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => __('Payment processing failed. Please try again or contact support.')]);
        }
    }



    public function getEnabledPaymentGateways($userId = null, $workspaceId = null)
    {
        if (!$userId) {
            return [];
        }

        // Get company-specific payment settings only
        $settings = PaymentSetting::where('user_id', $userId)->pluck('value', 'key')->toArray();

        $gateways = [];
        $paymentGateways = [
            'bank' => ['name' => 'Bank Transfer', 'icon' => '🏦'],
            'stripe' => ['name' => 'Credit Card (Stripe)', 'icon' => '💳'],
            'paypal' => ['name' => 'PayPal', 'icon' => '🅿️'],
            'razorpay' => ['name' => 'Razorpay', 'icon' => '💰'],
            'mercadopago' => ['name' => 'Mercado Pago', 'icon' => '💳'],
            'paystack' => ['name' => 'Paystack', 'icon' => '🅿️'],
            'flutterwave' => ['name' => 'Flutterwave', 'icon' => '💳'],
            'paytabs' => ['name' => 'Paytabs', 'icon' => '🅿️'],
            'skrill' => ['name' => 'Skrill', 'icon' => '💳'],
            'coingate' => ['name' => 'Coin Gate', 'icon' => '💳'],
            'payfast' => ['name' => 'Pay Fast', 'icon' => '🅿️'],
            'tap' => ['name' => 'Tap', 'icon' => '💳'],
            'xendit' => ['name' => 'Xendit', 'icon' => '💳'],
            'paytr' => ['name' => 'PayTR', 'icon' => '🅿️'],
            'mollie' => ['name' => 'Mollie', 'icon' => '💳'],
            'toyyibpay' => ['name' => 'Toyyib Pay', 'icon' => '💳'],
            'cashfree' => ['name' => 'Cashfree', 'icon' => '💳'],
            'khalti' => ['name' => 'Khalti', 'icon' => '💳'],
            'iyzipay' => ['name' => 'Iyzipay', 'icon' => '💳'],
            'benefit' => ['name' => 'Benefit', 'icon' => '💳'],
            'ozow' => ['name' => 'Ozow', 'icon' => '💳'],
            'easebuzz' => ['name' => 'Easebuzz', 'icon' => '💳'],
            'authorizenet' => ['name' => 'Authorize.net', 'icon' => '💳'],
            'fedapay' => ['name' => 'Fedapay', 'icon' => '💳'],
            'payhere' => ['name' => 'Pay Here', 'icon' => '🅿️'],
            'cinetpay' => ['name' => 'Cinet Pay', 'icon' => '💳'],
            'paiement' => ['name' => 'Paiement Pro', 'icon' => '🅿️'],
            'yookassa' => ['name' => 'Yoo Kassa', 'icon' => '💳'],
            'aamarpay' => ['name' => 'Aamar Pay', 'icon' => '💳'],
            'midtrans' => ['name' => 'Midtrans', 'icon' => '💳'],
        ];

        foreach ($paymentGateways as $key => $config) {
            $enabledKey = "is_{$key}_enabled";
            if (($settings[$enabledKey] ?? '0') === '1') {
                $gateways[] = [
                    'id' => $key,
                    'name' => $config['name'],
                    'icon' => $config['icon']
                ];
            }
        }

        return $gateways;
    }

    public function getPaymentMethods(Invoice $invoice)
    {
        $gateways = $this->getEnabledPaymentGateways($invoice->created_by, $invoice->workspace_id);
        // Get payment settings for credentials
        $paymentSettings = PaymentSetting::where('user_id', $invoice->created_by)
            ->pluck('value', 'key')
            ->toArray();
            
        // Add credentials to response
        $response = [
            'gateways' => $gateways,
            'paypalClientId' => $paymentSettings['paypal_client_id'] ?? null,
            'stripeKey' => $paymentSettings['stripe_key'] ?? null,
            'razorpayKey' => $paymentSettings['razorpay_key'] ?? null,
            'mercadopagoAccessToken' => $paymentSettings['mercadopago_access_token'] ?? null,
            'paystackPublicKey' => $paymentSettings['paystack_public_key'] ?? null,
            'flutterwavePublicKey' => $paymentSettings['flutterwave_public_key'] ?? null,
            'tapPublicKey' => $paymentSettings['tap_secret_key'] ?? null,
            'xenditApiKey' => $paymentSettings['xendit_api_key'] ?? null,
            'paytrMerchantId' => $paymentSettings['paytr_merchant_id'] ?? null,
            'mollieApiKey' => $paymentSettings['mollie_api_key'] ?? null,
            'toyyibpayCategoryCode' => $paymentSettings['toyyibpay_category_code'] ?? null,
            'benefitPublicKey' => $paymentSettings['benefit_public_key'] ?? null,
            'iyzipayPublicKey' => $paymentSettings['iyzipay_public_key'] ?? null,
            'aamarpayStoreId' => $paymentSettings['aamarpay_store_id'] ?? null,
            'paytrMerchantKey' => $paymentSettings['paytr_merchant_key'] ?? null,
            'paytrMerchantSalt' => $paymentSettings['paytr_merchant_salt'] ?? null,
            'yookassaShopId' => $paymentSettings['yookassa_shop_id'] ?? null,
            'yookassaSecretKey' => $paymentSettings['yookassa_secret_key'] ?? null,
            'paiementMerchantId' => $paymentSettings['paiement_merchant_id'] ?? null,
            'cinetpaySiteId' => $paymentSettings['cinetpay_site_id'] ?? null,
            'cinetpayApiKey' => $paymentSettings['cinetpay_api_key'] ?? null,
            'payhereMerchantId' => $paymentSettings['payhere_merchant_id'] ?? null,
            'payhereMerchantSecret' => $paymentSettings['payhere_merchant_secret'] ?? null,
            'payhereMode' => $paymentSettings['payhere_mode'] ?? null,
            'fedapaySecretKey' => $paymentSettings['fedapay_secret_key'] ?? null,
            'fedapayMode' => $paymentSettings['fedapay_mode'] ?? null,
            'authorizenetMerchantId' => $paymentSettings['authorizenet_merchant_id'] ?? null,
            'authorizenetTransactionKey' => $paymentSettings['authorizenet_transaction_key'] ?? null,
            'authorizenetMode' => $paymentSettings['authorizenet_mode'] ?? null,
            'khaltiPublicKey' => $paymentSettings['khalti_public_key'] ?? null,
            'khaltiSecretKey' => $paymentSettings['khalti_secret_key'] ?? null,
            'easebuzzMerchantKey' => $paymentSettings['easebuzz_merchant_key'] ?? null,
            'easebuzzSaltKey' => $paymentSettings['easebuzz_salt_key'] ?? null,
            'easebuzzEnvironment' => $paymentSettings['easebuzz_environment'] ?? null,
            'ozowSiteKey' => $paymentSettings['ozow_site_key'] ?? null,
            'ozowPrivateKey' => $paymentSettings['ozow_private_key'] ?? null,
            'ozowApiKey' => $paymentSettings['ozow_api_key'] ?? null,
            'ozowMode' => $paymentSettings['ozow_mode'] ?? null,
            'cashfreePublicKey' => $paymentSettings['cashfree_public_key'] ?? null,
            'cashfreeSecretKey' => $paymentSettings['cashfree_secret_key'] ?? null,
            'cashfreeMode' => $paymentSettings['cashfree_mode'] ?? null,
            'paytabsProfileId' => $paymentSettings['paytabs_profile_id'] ?? null,
            'paytabsServerKey' => $paymentSettings['paytabs_server_key'] ?? null,
            'paytabsRegion' => $paymentSettings['paytabs_region'] ?? null,
            'skrillMerchantId' => $paymentSettings['skrill_merchant_id'] ?? null,
            'coingateApiToken' => $paymentSettings['coingate_api_token'] ?? null,
            'coingateMode' => $paymentSettings['coingate_mode'] ?? null,
            'payfastMerchantId' => $paymentSettings['payfast_merchant_id'] ?? null,
            'payfastMerchantKey' => $paymentSettings['payfast_merchant_key'] ?? null,
            'payfastMode' => $paymentSettings['payfast_mode'] ?? null,
            'iyzipayPublicKey' => $paymentSettings['iyzipay_public_key'] ?? null,
            'iyzipaySecretKey' => $paymentSettings['iyzipay_secret_key'] ?? null,
            'iyzipayMode' => $paymentSettings['iyzipay_mode'] ?? null
        ];
        return response()->json($response);
    }
}
