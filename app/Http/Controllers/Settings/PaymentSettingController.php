<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentSettingController extends Controller
{
    use HasPermissionChecks;
    public function index()
    {
        $this->authorizePermission('settings_payment');
        
        $user = auth()->user();
        $workspaceId = $user->type === 'company' ? $user->current_workspace_id : null;
        $paymentSettings = getPaymentSettings($user->id, $workspaceId);
        
        return Inertia::render('settings/index', [
            'paymentSettings' => $paymentSettings,
        ]);
    }

    public function getPaymentMethods()
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([]);
        }
        
        if ($user->type === 'company') {
            // For company users, get workspace-specific settings
            $paymentSettings = getPaymentSettings($user->id, $user->current_workspace_id);
            $settings = settings($user->id, $user->current_workspace_id);
        } elseif ($user->type === 'superadmin') {
            // For superadmin, get global settings
            $paymentSettings = getPaymentSettings($user->id, null);
            $settings = settings($user->id, null);
        } else {
            return response()->json([]);
        }
        
        // Add default currency to payment settings
        $paymentSettings['defaultCurrency'] = $settings['defaultCurrency'] ?? 'usd';
        
        return response()->json($paymentSettings);
    }
    public function store(Request $request)
    {
        $this->authorizePermission('settings_payment');
        
        try {
            $validatedData = $request->validate([
                'stripe_key' => 'nullable|string',
                'stripe_secret' => 'nullable|string',
                'paypal_client_id' => 'nullable|string',
                'paypal_secret_key' => 'nullable|string',
                'paypal_mode' => 'in:sandbox,live',
                'bank_detail' => 'nullable|string',
                'razorpay_key' => 'nullable|string',
                'razorpay_secret' => 'nullable|string',
                'mercadopago_mode' => 'in:sandbox,live',
                'mercadopago_access_token' => 'nullable|string',
                'paystack_public_key' => 'nullable|string',
                'paystack_secret_key' => 'nullable|string',
                'flutterwave_public_key' => 'nullable|string',
                'flutterwave_secret_key' => 'nullable|string',
                'paytabs_profile_id' => 'nullable|string',
                'paytabs_server_key' => 'nullable|string',
                'paytabs_region' => 'nullable|string',
                'paytabs_mode' => 'in:sandbox,live',
                'skrill_merchant_id' => 'nullable|string',
                'skrill_secret_word' => 'nullable|string',
                'coingate_api_token' => 'nullable|string',
                'coingate_mode' => 'in:sandbox,live',
                'payfast_merchant_id' => 'nullable|string',
                'payfast_merchant_key' => 'nullable|string',
                'payfast_passphrase' => 'nullable|string',
                'payfast_mode' => 'in:sandbox,live',
                'tap_secret_key' => 'nullable|string',
                'xendit_api_key' => 'nullable|string',
                'paytr_merchant_id' => 'nullable|string',
                'paytr_merchant_key' => 'nullable|string',
                'paytr_merchant_salt' => 'nullable|string',
                'mollie_api_key' => 'nullable|string',
                'toyyibpay_category_code' => 'nullable|string',
                'toyyibpay_secret_key' => 'nullable|string',
                'paymentwall_public_key' => 'nullable|string',
                'paymentwall_private_key' => 'nullable|string',
                'sspay_secret_key' => 'nullable|string',
                'sspay_category_code' => 'nullable|string',
                'benefit_mode' => 'in:sandbox,live',
                'benefit_secret_key' => 'nullable|string',
                'benefit_public_key' => 'nullable|string',
                'iyzipay_mode' => 'in:sandbox,live',
                'iyzipay_secret_key' => 'nullable|string',
                'iyzipay_public_key' => 'nullable|string',
                'aamarpay_store_id' => 'nullable|string',
                'aamarpay_signature' => 'nullable|string',
                'midtrans_mode' => 'in:sandbox,live',
                'midtrans_secret_key' => 'nullable|string',
                'yookassa_shop_id' => 'nullable|string',
                'yookassa_secret_key' => 'nullable|string',
                'nepalste_mode' => 'in:sandbox,live',
                'nepalste_secret_key' => 'nullable|string',
                'nepalste_public_key' => 'nullable|string',
                'paiement_merchant_id' => 'nullable|string',
                'cinetpay_site_id' => 'nullable|string',
                'cinetpay_api_key' => 'nullable|string',
                'cinetpay_secret_key' => 'nullable|string',
                'payhere_mode' => 'in:sandbox,live',
                'payhere_merchant_id' => 'nullable|string',
                'payhere_merchant_secret' => 'nullable|string',
                'payhere_app_id' => 'nullable|string',
                'payhere_app_secret' => 'nullable|string',
                'fedapay_mode' => 'in:sandbox,live',
                'fedapay_secret_key' => 'nullable|string',
                'fedapay_public_key' => 'nullable|string',
                'authorizenet_mode' => 'in:sandbox,live',
                'authorizenet_merchant_id' => 'nullable|string',
                'authorizenet_transaction_key' => 'nullable|string',
                'khalti_secret_key' => 'nullable|string',
                'khalti_public_key' => 'nullable|string',
                'easebuzz_merchant_key' => 'nullable|string',
                'easebuzz_salt_key' => 'nullable|string',
                'easebuzz_environment' => 'nullable|string',
                'ozow_mode' => 'in:sandbox,live',
                'ozow_site_key' => 'nullable|string',
                'ozow_private_key' => 'nullable|string',
                'ozow_api_key' => 'nullable|string',
                'cashfree_mode' => 'in:sandbox,live',
                'cashfree_secret_key' => 'nullable|string',
                'cashfree_public_key' => 'nullable|string',
            ]);

            $settings = $this->preparePaymentSettings($request, $validatedData);
            $this->validateEnabledPaymentMethods($request, $validatedData);
            $this->savePaymentSettings($settings);

            return back()->with('success', 'Payment settings saved successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            \Log::error('Payment settings save error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to save payment settings: ' . $e->getMessage()]);
        }
    }

    private function preparePaymentSettings(Request $request, array $validatedData): array
    {
        return [
            'is_manually_enabled' => $request->boolean('is_manually_enabled'),
            'is_bank_enabled' => $request->boolean('is_bank_enabled'),
            'is_stripe_enabled' => $request->boolean('is_stripe_enabled'),
            'is_paypal_enabled' => $request->boolean('is_paypal_enabled'),
            'is_razorpay_enabled' => $request->boolean('is_razorpay_enabled'),
            'is_mercadopago_enabled' => $request->boolean('is_mercadopago_enabled'),
            'is_paystack_enabled' => $request->boolean('is_paystack_enabled'),
            'is_flutterwave_enabled' => $request->boolean('is_flutterwave_enabled'),
            'is_paytabs_enabled' => $request->boolean('is_paytabs_enabled'),
            'is_skrill_enabled' => $request->boolean('is_skrill_enabled'),
            'is_coingate_enabled' => $request->boolean('is_coingate_enabled'),
            'is_payfast_enabled' => $request->boolean('is_payfast_enabled'),
            'is_tap_enabled' => $request->boolean('is_tap_enabled'),
            'is_xendit_enabled' => $request->boolean('is_xendit_enabled'),
            'is_paytr_enabled' => $request->boolean('is_paytr_enabled'),
            'is_mollie_enabled' => $request->boolean('is_mollie_enabled'),
            'is_toyyibpay_enabled' => $request->boolean('is_toyyibpay_enabled'),
            'is_paymentwall_enabled' => $request->boolean('is_paymentwall_enabled'),
            'is_sspay_enabled' => $request->boolean('is_sspay_enabled'),
            'is_benefit_enabled' => $request->boolean('is_benefit_enabled'),
            'is_iyzipay_enabled' => $request->boolean('is_iyzipay_enabled'),
            'is_aamarpay_enabled' => $request->boolean('is_aamarpay_enabled'),
            'is_midtrans_enabled' => $request->boolean('is_midtrans_enabled'),
            'is_yookassa_enabled' => $request->boolean('is_yookassa_enabled'),
            'is_nepalste_enabled' => $request->boolean('is_nepalste_enabled'),
            'is_paiement_enabled' => $request->boolean('is_paiement_enabled'),
            'is_cinetpay_enabled' => $request->boolean('is_cinetpay_enabled'),
            'is_payhere_enabled' => $request->boolean('is_payhere_enabled'),
            'is_fedapay_enabled' => $request->boolean('is_fedapay_enabled'),
            'is_authorizenet_enabled' => $request->boolean('is_authorizenet_enabled'),
            'is_khalti_enabled' => $request->boolean('is_khalti_enabled'),
            'is_easebuzz_enabled' => $request->boolean('is_easebuzz_enabled'),
            'is_ozow_enabled' => $request->boolean('is_ozow_enabled'),
            'is_cashfree_enabled' => $request->boolean('is_cashfree_enabled'),
            'paypal_mode' => $validatedData['paypal_mode'] ?? 'sandbox',
            'mercadopago_mode' => $validatedData['mercadopago_mode'] ?? 'sandbox',
            'bank_detail' => $validatedData['bank_detail'] ?? null,
            'stripe_key' => $validatedData['stripe_key'] ?? null,
            'stripe_secret' => $validatedData['stripe_secret'] ?? null,
            'paypal_client_id' => $validatedData['paypal_client_id'] ?? null,
            'paypal_secret_key' => $validatedData['paypal_secret_key'] ?? null,
            'razorpay_key' => $validatedData['razorpay_key'] ?? null,
            'razorpay_secret' => $validatedData['razorpay_secret'] ?? null,
            'mercadopago_access_token' => $validatedData['mercadopago_access_token'] ?? null,
            'paystack_public_key' => $validatedData['paystack_public_key'] ?? null,
            'paystack_secret_key' => $validatedData['paystack_secret_key'] ?? null,
            'flutterwave_public_key' => $validatedData['flutterwave_public_key'] ?? null,
            'flutterwave_secret_key' => $validatedData['flutterwave_secret_key'] ?? null,
            'paytabs_profile_id' => $validatedData['paytabs_profile_id'] ?? null,
            'paytabs_server_key' => $validatedData['paytabs_server_key'] ?? null,
            'paytabs_region' => $validatedData['paytabs_region'] ?? null,
            'paytabs_mode' => $validatedData['paytabs_mode'] ?? 'sandbox',
            'skrill_merchant_id' => $validatedData['skrill_merchant_id'] ?? null,
            'skrill_secret_word' => $validatedData['skrill_secret_word'] ?? null,
            'coingate_api_token' => $validatedData['coingate_api_token'] ?? null,
            'coingate_mode' => $validatedData['coingate_mode'] ?? 'sandbox',
            'payfast_merchant_id' => $validatedData['payfast_merchant_id'] ?? null,
            'payfast_merchant_key' => $validatedData['payfast_merchant_key'] ?? null,
            'payfast_passphrase' => $validatedData['payfast_passphrase'] ?? null,
            'payfast_mode' => $validatedData['payfast_mode'] ?? 'sandbox',
            'tap_secret_key' => $validatedData['tap_secret_key'] ?? null,
            'xendit_api_key' => $validatedData['xendit_api_key'] ?? null,
            'paytr_merchant_id' => $validatedData['paytr_merchant_id'] ?? null,
            'paytr_merchant_key' => $validatedData['paytr_merchant_key'] ?? null,
            'paytr_merchant_salt' => $validatedData['paytr_merchant_salt'] ?? null,
            'mollie_api_key' => $validatedData['mollie_api_key'] ?? null,
            'toyyibpay_category_code' => $validatedData['toyyibpay_category_code'] ?? null,
            'toyyibpay_secret_key' => $validatedData['toyyibpay_secret_key'] ?? null,
            'paymentwall_public_key' => $validatedData['paymentwall_public_key'] ?? null,
            'paymentwall_private_key' => $validatedData['paymentwall_private_key'] ?? null,
            'sspay_secret_key' => $validatedData['sspay_secret_key'] ?? null,
            'sspay_category_code' => $validatedData['sspay_category_code'] ?? null,
            'benefit_mode' => $validatedData['benefit_mode'] ?? 'sandbox',
            'benefit_secret_key' => $validatedData['benefit_secret_key'] ?? null,
            'benefit_public_key' => $validatedData['benefit_public_key'] ?? null,
            'iyzipay_mode' => $validatedData['iyzipay_mode'] ?? 'sandbox',
            'iyzipay_secret_key' => $validatedData['iyzipay_secret_key'] ?? null,
            'iyzipay_public_key' => $validatedData['iyzipay_public_key'] ?? null,
            'aamarpay_store_id' => $validatedData['aamarpay_store_id'] ?? null,
            'aamarpay_signature' => $validatedData['aamarpay_signature'] ?? null,
            'midtrans_mode' => $validatedData['midtrans_mode'] ?? 'sandbox',
            'midtrans_secret_key' => $validatedData['midtrans_secret_key'] ?? null,
            'yookassa_shop_id' => $validatedData['yookassa_shop_id'] ?? null,
            'yookassa_secret_key' => $validatedData['yookassa_secret_key'] ?? null,
            'nepalste_mode' => $validatedData['nepalste_mode'] ?? 'sandbox',
            'nepalste_secret_key' => $validatedData['nepalste_secret_key'] ?? null,
            'nepalste_public_key' => $validatedData['nepalste_public_key'] ?? null,
            'paiement_merchant_id' => $validatedData['paiement_merchant_id'] ?? null,
            'cinetpay_site_id' => $validatedData['cinetpay_site_id'] ?? null,
            'cinetpay_api_key' => $validatedData['cinetpay_api_key'] ?? null,
            'cinetpay_secret_key' => $validatedData['cinetpay_secret_key'] ?? null,
            'payhere_mode' => $validatedData['payhere_mode'] ?? 'sandbox',
            'payhere_merchant_id' => $validatedData['payhere_merchant_id'] ?? null,
            'payhere_merchant_secret' => $validatedData['payhere_merchant_secret'] ?? null,
            'payhere_app_id' => $validatedData['payhere_app_id'] ?? null,
            'payhere_app_secret' => $validatedData['payhere_app_secret'] ?? null,
            'fedapay_mode' => $validatedData['fedapay_mode'] ?? 'sandbox',
            'fedapay_secret_key' => $validatedData['fedapay_secret_key'] ?? null,
            'fedapay_public_key' => $validatedData['fedapay_public_key'] ?? null,
            'authorizenet_mode' => $validatedData['authorizenet_mode'] ?? 'sandbox',
            'authorizenet_merchant_id' => $validatedData['authorizenet_merchant_id'] ?? null,
            'authorizenet_transaction_key' => $validatedData['authorizenet_transaction_key'] ?? null,
            'khalti_secret_key' => $validatedData['khalti_secret_key'] ?? null,
            'khalti_public_key' => $validatedData['khalti_public_key'] ?? null,
            'easebuzz_merchant_key' => $validatedData['easebuzz_merchant_key'] ?? null,
            'easebuzz_salt_key' => $validatedData['easebuzz_salt_key'] ?? null,
            'easebuzz_environment' => $validatedData['easebuzz_environment'] ?? null,
            'ozow_mode' => $validatedData['ozow_mode'] ?? 'sandbox',
            'ozow_site_key' => $validatedData['ozow_site_key'] ?? null,
            'ozow_private_key' => $validatedData['ozow_private_key'] ?? null,
            'ozow_api_key' => $validatedData['ozow_api_key'] ?? null,
            'cashfree_mode' => $validatedData['cashfree_mode'] ?? 'sandbox',
            'cashfree_secret_key' => $validatedData['cashfree_secret_key'] ?? null,
            'cashfree_public_key' => $validatedData['cashfree_public_key'] ?? null,
        ];
    }

    private function validateEnabledPaymentMethods(Request $request, array $validatedData): void
    {
        $errors = [];

        if ($request->boolean('is_stripe_enabled')) {
            $config = ['key' => $validatedData['stripe_key'] ?? null, 'secret' => $validatedData['stripe_secret'] ?? null];
            $validation = validatePaymentMethodConfig('stripe', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_paypal_enabled')) {
            $config = ['client_id' => $validatedData['paypal_client_id'] ?? null, 'secret' => $validatedData['paypal_secret_key'] ?? null];
            $validation = validatePaymentMethodConfig('paypal', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_razorpay_enabled')) {
            $config = ['key' => $validatedData['razorpay_key'] ?? null, 'secret' => $validatedData['razorpay_secret'] ?? null];
            $validation = validatePaymentMethodConfig('razorpay', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_mercadopago_enabled')) {
            $config = ['access_token' => $validatedData['mercadopago_access_token'] ?? null];
            $validation = validatePaymentMethodConfig('mercadopago', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_paystack_enabled')) {
            $config = ['public_key' => $validatedData['paystack_public_key'] ?? null, 'secret_key' => $validatedData['paystack_secret_key'] ?? null];
            $validation = validatePaymentMethodConfig('paystack', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_flutterwave_enabled')) {
            $config = ['public_key' => $validatedData['flutterwave_public_key'] ?? null, 'secret_key' => $validatedData['flutterwave_secret_key'] ?? null];
            $validation = validatePaymentMethodConfig('flutterwave', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_bank_enabled')) {
            $config = ['details' => $validatedData['bank_detail'] ?? null];
            $validation = validatePaymentMethodConfig('bank', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_paytabs_enabled')) {
            $config = ['server_key' => $validatedData['paytabs_server_key'] ?? null, 'profile_id' => $validatedData['paytabs_profile_id'] ?? null, 'region' => $validatedData['paytabs_region'] ?? null];
            $validation = validatePaymentMethodConfig('paytabs', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_skrill_enabled')) {
            $config = ['merchant_id' => $validatedData['skrill_merchant_id'] ?? null, 'secret_word' => $validatedData['skrill_secret_word'] ?? null];
            $validation = validatePaymentMethodConfig('skrill', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_coingate_enabled')) {
            $config = ['api_token' => $validatedData['coingate_api_token'] ?? null];
            $validation = validatePaymentMethodConfig('coingate', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_payfast_enabled')) {
            $config = ['merchant_id' => $validatedData['payfast_merchant_id'] ?? null, 'merchant_key' => $validatedData['payfast_merchant_key'] ?? null];
            $validation = validatePaymentMethodConfig('payfast', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_tap_enabled')) {
            $config = ['secret_key' => $validatedData['tap_secret_key'] ?? null];
            $validation = validatePaymentMethodConfig('tap', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_xendit_enabled')) {
            $config = ['api_key' => $validatedData['xendit_api_key'] ?? null];
            $validation = validatePaymentMethodConfig('xendit', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_paytr_enabled')) {
            $config = ['merchant_id' => $validatedData['paytr_merchant_id'] ?? null, 'merchant_key' => $validatedData['paytr_merchant_key'] ?? null, 'merchant_salt' => $validatedData['paytr_merchant_salt'] ?? null];
            $validation = validatePaymentMethodConfig('paytr', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_mollie_enabled')) {
            $config = ['api_key' => $validatedData['mollie_api_key'] ?? null];
            $validation = validatePaymentMethodConfig('mollie', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_toyyibpay_enabled')) {
            $config = ['category_code' => $validatedData['toyyibpay_category_code'] ?? null, 'secret_key' => $validatedData['toyyibpay_secret_key'] ?? null];
            $validation = validatePaymentMethodConfig('toyyibpay', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_cashfree_enabled')) {
            $config = ['public_key' => $validatedData['cashfree_public_key'] ?? null, 'secret_key' => $validatedData['cashfree_secret_key'] ?? null];
            $validation = validatePaymentMethodConfig('cashfree', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_ozow_enabled')) {
            $config = ['site_key' => $validatedData['ozow_site_key'] ?? null, 'private_key' => $validatedData['ozow_private_key'] ?? null];
            $validation = validatePaymentMethodConfig('ozow', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_easebuzz_enabled')) {
            $config = ['merchant_key' => $validatedData['easebuzz_merchant_key'] ?? null, 'salt_key' => $validatedData['easebuzz_salt_key'] ?? null];
            $validation = validatePaymentMethodConfig('easebuzz', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_khalti_enabled')) {
            $config = ['public_key' => $validatedData['khalti_public_key'] ?? null, 'secret_key' => $validatedData['khalti_secret_key'] ?? null];
            $validation = validatePaymentMethodConfig('khalti', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_authorizenet_enabled')) {
            $config = ['merchant_id' => $validatedData['authorizenet_merchant_id'] ?? null, 'transaction_key' => $validatedData['authorizenet_transaction_key'] ?? null];
            $validation = validatePaymentMethodConfig('authorizenet', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_fedapay_enabled')) {
            $config = ['public_key' => $validatedData['fedapay_public_key'] ?? null, 'secret_key' => $validatedData['fedapay_secret_key'] ?? null];
            $validation = validatePaymentMethodConfig('fedapay', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_payhere_enabled')) {
            $config = ['merchant_id' => $validatedData['payhere_merchant_id'] ?? null, 'merchant_secret' => $validatedData['payhere_merchant_secret'] ?? null];
            $validation = validatePaymentMethodConfig('payhere', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_cinetpay_enabled')) {
            $config = ['site_id' => $validatedData['cinetpay_site_id'] ?? null, 'api_key' => $validatedData['cinetpay_api_key'] ?? null];
            $validation = validatePaymentMethodConfig('cinetpay', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_paiement_enabled')) {
            $config = ['merchant_id' => $validatedData['paiement_merchant_id'] ?? null];
            $validation = validatePaymentMethodConfig('paiement', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_nepalste_enabled')) {
            $config = ['public_key' => $validatedData['nepalste_public_key'] ?? null, 'secret_key' => $validatedData['nepalste_secret_key'] ?? null];
            $validation = validatePaymentMethodConfig('nepalste', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_yookassa_enabled')) {
            $config = ['shop_id' => $validatedData['yookassa_shop_id'] ?? null, 'secret_key' => $validatedData['yookassa_secret_key'] ?? null];
            $validation = validatePaymentMethodConfig('yookassa', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_midtrans_enabled')) {
            $config = ['secret_key' => $validatedData['midtrans_secret_key'] ?? null];
            $validation = validatePaymentMethodConfig('midtrans', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_aamarpay_enabled')) {
            $config = ['store_id' => $validatedData['aamarpay_store_id'] ?? null, 'signature' => $validatedData['aamarpay_signature'] ?? null];
            $validation = validatePaymentMethodConfig('aamarpay', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_iyzipay_enabled')) {
            $config = ['public_key' => $validatedData['iyzipay_public_key'] ?? null, 'secret_key' => $validatedData['iyzipay_secret_key'] ?? null];
            $validation = validatePaymentMethodConfig('iyzipay', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_paymentwall_enabled')) {
            $config = ['public_key' => $validatedData['paymentwall_public_key'] ?? null, 'private_key' => $validatedData['paymentwall_private_key'] ?? null];
            $validation = validatePaymentMethodConfig('paymentwall', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_sspay_enabled')) {
            $config = ['secret_key' => $validatedData['sspay_secret_key'] ?? null];
            $validation = validatePaymentMethodConfig('sspay', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if ($request->boolean('is_benefit_enabled')) {
            $config = ['public_key' => $validatedData['benefit_public_key'] ?? null, 'secret_key' => $validatedData['benefit_secret_key'] ?? null];
            $validation = validatePaymentMethodConfig('benefit', $config);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if (!empty($errors)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'payment_methods' => $errors
            ]);
        }
    }

    private function savePaymentSettings(array $settings): void
    {
        $user = auth()->user();
        
        // Determine workspace ID based on user type
        $workspaceId = null;
        if ($user->type === 'company') {
            $workspaceId = $user->current_workspace_id;
        }
        
        foreach ($settings as $key => $value) {
            updatePaymentSetting($key, $value, $user->id, $workspaceId);
        }
    }

    public function getEnabledMethods()
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([]);
        }
        
        $workspaceId = null;
        if ($user->type === 'company') {
            $workspaceId = $user->current_workspace_id;
        }
        
        $enabledMethods = getEnabledPaymentMethods($user->id, $workspaceId);
        
        return response()->json($enabledMethods);
    }
}