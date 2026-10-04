<?php

use App\Models\Setting;
use App\Models\User;
use App\Models\Coupon;
use Carbon\Carbon;
use App\Models\Plan;
use App\Models\PlanOrder;
use App\Models\Role;
use App\Models\PaymentSetting;

if (!function_exists('getCacheSize')) {
    /**
     * Get the total cache size in MB
     *
     * @return string
     */
    function getCacheSize()
    {
        $file_size = 0;
        $framework_path = storage_path('framework');
        
        if (is_dir($framework_path)) {
            foreach (\File::allFiles($framework_path) as $file) {
                $file_size += $file->getSize();
            }
        }
        
        return number_format($file_size / 1000000, 2);
    }
}

if (! function_exists('platformSettingsUserId')) {
    /**
     * Platform (SaaS-level) settings - landing page, registration, email, branding -
     * belong to the platform, not to whichever superadmin account saved them. All
     * superadmins read and write them under one canonical account (the oldest
     * superadmin), which is also the account guests' settings are read from.
     * Before this, a second superadmin's saves were never seen by guests.
     */
    function platformSettingsUserId(): ?int
    {
        return \Illuminate\Support\Facades\Cache::remember('settings_platform_user_id', 60, function () {
            return User::where('type', 'superadmin')->orderBy('id')->value('id');
        });
    }
}

if (! function_exists('settings')) {
    function settings($user_id = null, $workspace_id = null)
    {
        // Skip database queries during installation
        if (request()->is('install/*') || request()->is('update/*') || !file_exists(storage_path('installed'))) {
            return [];
        }

        if (is_null($user_id)) {
            if (auth()->user()) {
                if (!in_array(auth()->user()->type, ['superadmin', 'company'])) {
                    $user_id = auth()->user()->created_by;
                } else {
                    $user_id = auth()->id();
                }
            } else {
                $user = \Illuminate\Support\Facades\Cache::remember('settings_default_user', 60, function () {
                    return User::where('type', isSaasMode() ? 'superadmin' : 'company')->first();
                });
                $user_id = $user ? $user->id : null;
            }
        }
        
        // In non-SaaS mode, always use company user settings
        if (!isSaasMode()) {
            $companyUser = \Illuminate\Support\Facades\Cache::remember('settings_company_user', 60, function () {
                return User::where('type', 'company')->first();
            });
            if ($companyUser) {
                $user_id = $companyUser->id;
                $workspace_id = $companyUser->current_workspace_id;
            }
        }

        if (!$user_id) {
            return [];
        }

        // Check if this is a superadmin user
        $user = \Illuminate\Support\Facades\Cache::remember("settings_user_{$user_id}", 60, function () use ($user_id) {
            return User::find($user_id);
        });
        if ($user && $user->type === 'superadmin') {
            $workspace_id = null;
            $user_id = platformSettingsUserId() ?? $user_id;
        } elseif (auth()->user() && auth()->user()->type === 'company' && is_null($workspace_id)) {
            $workspace_id = auth()->user()->current_workspace_id;
        }

        $cacheKey = "settings_{$user_id}_{$workspace_id}";
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($user_id, $workspace_id) {
            return Setting::where('user_id', $user_id)
                ->where('workspace_id', $workspace_id)
                ->pluck('value', 'key')->toArray();
        });
    }
}

if (! function_exists('formatDateTime')) {
    function formatDateTime($date, $includeTime = true)
    {
        if (!$date) {
            return null;
        }

        $settings = settings();

        $dateFormat = $settings['dateFormat'] ?? 'Y-m-d';
        $timeFormat = $settings['timeFormat'] ?? 'H:i';
        $timezone = $settings['defaultTimezone'] ?? config('app.timezone', 'UTC');

        $format = $includeTime ? "$dateFormat $timeFormat" : $dateFormat;

        return Carbon::parse($date)->timezone($timezone)->format($format);
    }
}

if (! function_exists('getSetting')) {
    function getSetting($key, $default = null, $user_id = null, $workspace_id = null)
    {
        // In non-SaaS mode, get settings from company user if no user specified
        if (!isSaasMode() && is_null($user_id)) {
            $companyUser = User::where('type', 'company')->first();
            if ($companyUser) {
                $user_id = $companyUser->id;
                $workspace_id = $companyUser->current_workspace_id;
            }
        }
        
        $settings = settings($user_id, $workspace_id);
        // If no value found and no default provided, try to get from defaultSettings
        if (!isset($settings[$key]) && $default === null) {
            $defaultSettings = defaultSettings();
            $default = $defaultSettings[$key] ?? null;
        }
        
        return $settings[$key] ?? $default;
    }
}

if (! function_exists('updateSetting')) {
    function updateSetting($key, $value, $user_id = null, $workspace_id = null, $ignore_workspace = false) 
    {
        if (is_null($user_id)) {
            if (auth()->user()) {
                if (!in_array(auth()->user()->type, ['superadmin', 'company'])) {
                    $user_id = auth()->user()->created_by;
                } else {
                    $user_id = auth()->id();
                }
            } else {
                $user = User::where('type', isSaasMode() ? 'superadmin' : 'company')->first();
                $user_id = $user ? $user->id : null;
            }
        }

        if (!$user_id) {
            return false;
        }

        // Superadmin saves go to the shared platform settings account (see platformSettingsUserId)
        if (User::whereKey($user_id)->value('type') === 'superadmin') {
            $user_id = platformSettingsUserId() ?? $user_id;
            $workspace_id = null;
        }

        // For superadmin, workspace_id is always null
        // For company users, use their current_workspace_id if ignore_workspace is false
        if (auth()->user() && !$ignore_workspace) {
            if (auth()->user()->type === 'superadmin') {
                $workspace_id = null;
            } elseif (auth()->user()->type === 'company' && is_null($workspace_id)) {
                $workspace_id = auth()->user()->current_workspace_id;
            }
        }

        // If ignore_workspace is true, set workspace_id to null
        if ($ignore_workspace) {
            $workspace_id = null;
        }

        return Setting::updateOrCreate(
            ['user_id' => $user_id, 'workspace_id' => $workspace_id, 'key' => $key],
            ['value' => $value]
        );
    }
}

if (! function_exists('isLandingPageEnabled')) {
    function isLandingPageEnabled()
    {
        return getSetting('landingPageEnabled', true) === true || getSetting('landingPageEnabled', true) === '1';
    }
}

if (! function_exists('createDefaultWorkspace')) {
    /**
     * Create default workspace for company user
     *
     * @param User $user
     * @return \App\Models\Workspace
     */
    function createDefaultWorkspace($user)
    {
        $workspace = \App\Models\Workspace::create([
            'name' => $user->name . "'s Workspace",
            'slug' => \Illuminate\Support\Str::slug($user->name . '-workspace-' . $user->id),
            'owner_id' => $user->id,
            'is_active' => true,
        ]);
        
        // Create workspace member record
        \App\Models\WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()
        ]);
        
        // Set current workspace
        $user->current_workspace_id = $workspace->id;
        $user->save();
        
        return $workspace;
    }
}

if (! function_exists('defaultRoleAndSetting')) {
    function defaultRoleAndSetting($user)
    {
        $companyRole = Role::where('name', 'company')->first();
            
        if ($companyRole) {
            $user->assignRole($companyRole);
        }
        
        // Create default settings for the user
        if ($user->type === 'superadmin') {
            createDefaultSettings($user->id);
        } elseif ($user->type === 'company') {
            $workspace = createDefaultWorkspace($user);
            copySettingsFromSuperAdmin($user->id, $workspace->id);
        }

        return true;
    }
}

if (! function_exists('getPaymentSettings')) {
    /**
     * Get payment settings for a user and workspace
     *
     * @param int|null $userId
     * @param int|null $workspaceId
     * @return array
     */
    function getPaymentSettings($userId = null, $workspaceId = null)
    {
        // In non-SaaS mode, only superadmin cannot access payment settings
        // Company users still need payment settings for invoice payments
        if (!isSaasMode() && auth()->check() && auth()->user()->type === 'superadmin') {
            return [];
        }
        
        if (is_null($userId)) {
            if (auth()->check()) {
                $userId = auth()->id();
                // For company users, use their current workspace
                if (auth()->user()->type === 'company' && is_null($workspaceId)) {
                    $workspaceId = auth()->user()->current_workspace_id;
                }
                // For superadmin, workspace is always null
                elseif (auth()->user()->type === 'superadmin') {
                    $workspaceId = null;
                }
            } else {
                return [];
            }
        }
        
        // If user is authenticated and no workspace specified, use current workspace for company users
        if (auth()->check() && is_null($workspaceId) && auth()->user()->type === 'company') {
            $workspaceId = auth()->user()->current_workspace_id;
        }

        return PaymentSetting::getUserSettings($userId, $workspaceId);
    }
}

if (! function_exists('updatePaymentSetting')) {
    /**
     * Update or create a payment setting
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $userId
     * @param int|null $workspaceId
     * @return \App\Models\PaymentSetting
     */
    function updatePaymentSetting($key, $value, $userId = null, $workspaceId = null)
    {
        if (is_null($userId)) {
            $userId = auth()->id();
        }
        
        // If user is authenticated and no workspace specified, use current workspace
        if (auth()->check() && is_null($workspaceId) && auth()->user()->type === 'company') {
            $workspaceId = auth()->user()->current_workspace_id;
        }
        
        // For superadmin, workspace_id is always null
        if (auth()->check() && auth()->user()->type === 'superadmin') {
            $workspaceId = null;
        }

        return PaymentSetting::updateOrCreateSetting($userId, $key, $value, $workspaceId);
    }
}

if (! function_exists('isPaymentMethodEnabled')) {
    /**
     * Check if a payment method is enabled
     *
     * @param string $method (stripe, paypal, razorpay, mercadopago, bank)
     * @param int|null $userId
     * @param int|null $workspaceId
     * @return bool
     */
    function isPaymentMethodEnabled($method, $userId = null, $workspaceId = null)
    {
        $settings = getPaymentSettings($userId, $workspaceId);
        $key = "is_{$method}_enabled";
        
        return isset($settings[$key]) && ($settings[$key] === true || $settings[$key] === '1');
    }
}

if (! function_exists('getPaymentMethodConfig')) {
    /**
     * Get configuration for a specific payment method
     *
     * @param string $method (stripe, paypal, razorpay, mercadopago)
     * @param int|null $userId
     * @param int|null $workspaceId
     * @return array
     */
    function getPaymentMethodConfig($method, $userId = null, $workspaceId = null)
    {
        $settings = getPaymentSettings($userId, $workspaceId);
        
        switch ($method) {
            case 'stripe':
                return [
                    'enabled' => isPaymentMethodEnabled('stripe', $userId, $workspaceId),
                    'key' => $settings['stripe_key'] ?? null,
                    'secret' => $settings['stripe_secret'] ?? null,
                ];
                
            case 'paypal':
                return [
                    'enabled' => isPaymentMethodEnabled('paypal', $userId, $workspaceId),
                    'mode' => $settings['paypal_mode'] ?? 'sandbox',
                    'client_id' => $settings['paypal_client_id'] ?? null,
                    'secret' => $settings['paypal_secret_key'] ?? null,
                ];
                
            case 'razorpay':
                return [
                    'enabled' => isPaymentMethodEnabled('razorpay', $userId, $workspaceId),
                    'key' => $settings['razorpay_key'] ?? null,
                    'secret' => $settings['razorpay_secret'] ?? null,
                ];
                
            case 'mercadopago':
                return [
                    'enabled' => isPaymentMethodEnabled('mercadopago', $userId, $workspaceId),
                    'mode' => $settings['mercadopago_mode'] ?? 'sandbox',
                    'access_token' => $settings['mercadopago_access_token'] ?? null,
                ];
                
            case 'paystack':
                return [
                    'enabled' => isPaymentMethodEnabled('paystack', $userId, $workspaceId),
                    'public_key' => $settings['paystack_public_key'] ?? null,
                    'secret_key' => $settings['paystack_secret_key'] ?? null,
                ];
                
            case 'flutterwave':
                return [
                    'enabled' => isPaymentMethodEnabled('flutterwave', $userId, $workspaceId),
                    'public_key' => $settings['flutterwave_public_key'] ?? null,
                    'secret_key' => $settings['flutterwave_secret_key'] ?? null,
                ];
                
            case 'bank':
                return [
                    'enabled' => isPaymentMethodEnabled('bank', $userId, $workspaceId),
                    'details' => $settings['bank_detail'] ?? null,
                ];
                
            case 'paytabs':
                return [
                    'enabled' => isPaymentMethodEnabled('paytabs', $userId, $workspaceId),
                    'mode' => $settings['paytabs_mode'] ?? 'sandbox',
                    'profile_id' => $settings['paytabs_profile_id'] ?? null,
                    'server_key' => $settings['paytabs_server_key'] ?? null,
                    'region' => $settings['paytabs_region'] ?? 'ARE',
                ];
                
            case 'skrill':
                return [
                    'enabled' => isPaymentMethodEnabled('skrill', $userId, $workspaceId),
                    'merchant_id' => $settings['skrill_merchant_id'] ?? null,
                    'secret_word' => $settings['skrill_secret_word'] ?? null,
                ];
                
            case 'coingate':
                return [
                    'enabled' => isPaymentMethodEnabled('coingate', $userId, $workspaceId),
                    'mode' => $settings['coingate_mode'] ?? 'sandbox',
                    'api_token' => $settings['coingate_api_token'] ?? null,
                ];
                
            case 'payfast':
                return [
                    'enabled' => isPaymentMethodEnabled('payfast', $userId, $workspaceId),
                    'mode' => $settings['payfast_mode'] ?? 'sandbox',
                    'merchant_id' => $settings['payfast_merchant_id'] ?? null,
                    'merchant_key' => $settings['payfast_merchant_key'] ?? null,
                    'passphrase' => $settings['payfast_passphrase'] ?? null,
                ];
                
            case 'tap':
                return [
                    'enabled' => isPaymentMethodEnabled('tap', $userId, $workspaceId),
                    'secret_key' => $settings['tap_secret_key'] ?? null,
                ];
                
            case 'xendit':
                return [
                    'enabled' => isPaymentMethodEnabled('xendit', $userId, $workspaceId),
                    'api_key' => $settings['xendit_api_key'] ?? null,
                ];
                
            case 'paytr':
                return [
                    'enabled' => isPaymentMethodEnabled('paytr', $userId, $workspaceId),
                    'merchant_id' => $settings['paytr_merchant_id'] ?? null,
                    'merchant_key' => $settings['paytr_merchant_key'] ?? null,
                    'merchant_salt' => $settings['paytr_merchant_salt'] ?? null,
                ];
                
            case 'mollie':
                return [
                    'enabled' => isPaymentMethodEnabled('mollie', $userId, $workspaceId),
                    'api_key' => $settings['mollie_api_key'] ?? null,
                ];
                
            case 'toyyibpay':
                return [
                    'enabled' => isPaymentMethodEnabled('toyyibpay', $userId, $workspaceId),
                    'category_code' => $settings['toyyibpay_category_code'] ?? null,
                    'secret_key' => $settings['toyyibpay_secret_key'] ?? null,
                    'mode' => $settings['toyyibpay_mode'] ?? 'sandbox',
                ];
                
            case 'cashfree':
                return [
                    'enabled' => isPaymentMethodEnabled('cashfree', $userId, $workspaceId),
                    'mode' => $settings['cashfree_mode'] ?? 'sandbox',
                    'public_key' => $settings['cashfree_public_key'] ?? null,
                    'secret_key' => $settings['cashfree_secret_key'] ?? null,
                ];
                
            case 'iyzipay':
                return [
                    'enabled' => isPaymentMethodEnabled('iyzipay', $userId, $workspaceId),
                    'mode' => $settings['iyzipay_mode'] ?? 'sandbox',
                    'public_key' => $settings['iyzipay_public_key'] ?? null,
                    'secret_key' => $settings['iyzipay_secret_key'] ?? null,
                ];
                
            case 'benefit':
                return [
                    'enabled' => isPaymentMethodEnabled('benefit', $userId, $workspaceId),
                    'mode' => $settings['benefit_mode'] ?? 'sandbox',
                    'public_key' => $settings['benefit_public_key'] ?? null,
                    'secret_key' => $settings['benefit_secret_key'] ?? null,
                ];
                
            case 'ozow':
                return [
                    'enabled' => isPaymentMethodEnabled('ozow', $userId, $workspaceId),
                    'mode' => $settings['ozow_mode'] ?? 'sandbox',
                    'site_key' => $settings['ozow_site_key'] ?? null,
                    'private_key' => $settings['ozow_private_key'] ?? null,
                    'api_key' => $settings['ozow_api_key'] ?? null,
                ];
                
            case 'easebuzz':
                return [
                    'enabled' => isPaymentMethodEnabled('easebuzz', $userId, $workspaceId),
                    'merchant_key' => $settings['easebuzz_merchant_key'] ?? null,
                    'salt_key' => $settings['easebuzz_salt_key'] ?? null,
                    'environment' => $settings['easebuzz_environment'] ?? 'test',
                ];
                
            case 'khalti':
                return [
                    'enabled' => isPaymentMethodEnabled('khalti', $userId, $workspaceId),
                    'public_key' => $settings['khalti_public_key'] ?? null,
                    'secret_key' => $settings['khalti_secret_key'] ?? null,
                ];
                
            case 'authorizenet':
                return [
                    'enabled' => isPaymentMethodEnabled('authorizenet', $userId, $workspaceId),
                    'mode' => $settings['authorizenet_mode'] ?? 'sandbox',
                    'merchant_id' => $settings['authorizenet_merchant_id'] ?? null,
                    'transaction_key' => $settings['authorizenet_transaction_key'] ?? null,
                    'supported_countries' => ['US', 'CA', 'GB', 'AU'],
                    'supported_currencies' => ['USD', 'CAD', 'CHF', 'DKK', 'EUR', 'GBP', 'NOK', 'PLN', 'SEK', 'AUD', 'NZD'],
                ];
                
            case 'fedapay':
                return [
                    'enabled' => isPaymentMethodEnabled('fedapay', $userId, $workspaceId),
                    'mode' => $settings['fedapay_mode'] ?? 'sandbox',
                    'public_key' => $settings['fedapay_public_key'] ?? null,
                    'secret_key' => $settings['fedapay_secret_key'] ?? null,
                ];
                
            case 'payhere':
                return [
                    'enabled' => isPaymentMethodEnabled('payhere', $userId, $workspaceId),
                    'mode' => $settings['payhere_mode'] ?? 'sandbox',
                    'merchant_id' => $settings['payhere_merchant_id'] ?? null,
                    'merchant_secret' => $settings['payhere_merchant_secret'] ?? null,
                    'app_id' => $settings['payhere_app_id'] ?? null,
                    'app_secret' => $settings['payhere_app_secret'] ?? null,
                ];
                
            case 'cinetpay':
                return [
                    'enabled' => isPaymentMethodEnabled('cinetpay', $userId, $workspaceId),
                    'site_id' => $settings['cinetpay_site_id'] ?? null,
                    'api_key' => $settings['cinetpay_api_key'] ?? null,
                    'secret_key' => $settings['cinetpay_secret_key'] ?? null,
                ];
                
            case 'paymentwall':
                return [
                    'enabled' => isPaymentMethodEnabled('paymentwall', $userId, $workspaceId),
                    'mode' => $settings['paymentwall_mode'] ?? 'sandbox',
                    'public_key' => $settings['paymentwall_public_key'] ?? null,
                    'private_key' => $settings['paymentwall_private_key'] ?? null,
                ];
                
            default:
                return [];
        }
    }
}

if (! function_exists('getEnabledPaymentMethods')) {
    /**
     * Get all enabled payment methods
     *
     * @param int|null $userId
     * @param int|null $workspaceId
     * @return array
     */
    function getEnabledPaymentMethods($userId = null, $workspaceId = null)
    {
        $methods = ['stripe', 'paypal', 'razorpay', 'mercadopago', 'paystack', 'flutterwave', 'bank', 'paytabs', 'skrill', 'coingate', 'payfast', 'tap', 'xendit', 'paytr', 'mollie', 'toyyibpay', 'cashfree', 'iyzipay', 'benefit', 'ozow', 'easebuzz', 'khalti', 'authorizenet', 'fedapay', 'payhere', 'cinetpay', 'paymentwall'];
        $enabled = [];
        
        foreach ($methods as $method) {
            if (isPaymentMethodEnabled($method, $userId, $workspaceId)) {
                $enabled[$method] = getPaymentMethodConfig($method, $userId, $workspaceId);
            }
        }
        
        return $enabled;
    }
}

if (! function_exists('validatePaymentMethodConfig')) {
    /**
     * Validate payment method configuration
     *
     * @param string $method
     * @param array $config
     * @return array [valid => bool, errors => array]
     */
    function validatePaymentMethodConfig($method, $config)
    {
        $errors = [];
        
        switch ($method) {
            case 'stripe':
                if (empty($config['key'])) {
                    $errors[] = __('Stripe publishable key is required');
                }
                if (empty($config['secret'])) {
                    $errors[] = __('Stripe secret key is required');
                }
                break;
                
            case 'paypal':
                if (empty($config['client_id'])) {
                    $errors[] = __('PayPal client ID is required');
                }
                if (empty($config['secret'])) {
                    $errors[] = __('PayPal secret key is required');
                }
                break;
                
            case 'razorpay':
                if (empty($config['key'])) {
                    $errors[] = __('Razorpay key ID is required');
                }
                if (empty($config['secret'])) {
                    $errors[] = __('Razorpay secret key is required');
                }
                break;
                
            case 'mercadopago':
                if (empty($config['access_token'])) {
                    $errors[] = __('MercadoPago access token is required');
                }
                break;
                
            case 'bank':
                if (empty($config['details'])) {
                    $errors[] = __('Bank details are required');
                }
                break;
                
            case 'paytabs':
                if (empty($config['server_key'])) {
                    $errors[] = 'PayTabs server key is required';
                }
                if (empty($config['profile_id'])) {
                    $errors[] = 'PayTabs profile id is required';
                }
                if (empty($config['region'])) {
                    $errors[] = 'PayTabs region is required';
                }
                break;
                
            case 'skrill':
                if (empty($config['merchant_id'])) {
                    $errors[] = 'Skrill merchant ID is required';
                }
                if (empty($config['secret_word'])) {
                    $errors[] = 'Skrill secret word is required';
                }
                break;
                
            case 'coingate':
                if (empty($config['api_token'])) {
                    $errors[] = 'CoinGate API token is required';
                }
                break;
                
            case 'payfast':
                if (empty($config['merchant_id'])) {
                    $errors[] = 'Payfast merchant ID is required';
                }
                if (empty($config['merchant_key'])) {
                    $errors[] = 'Payfast merchant key is required';
                }
                break;
                
            case 'tap':
                if (empty($config['secret_key'])) {
                    $errors[] = 'Tap secret key is required';
                }
                break;
                
            case 'xendit':
                if (empty($config['api_key'])) {
                    $errors[] = 'Xendit api key is required';
                }
                break;
                
            case 'paytr':
                if (empty($config['merchant_id'])) {
                    $errors[] = 'PayTR merchant ID is required';
                }
                if (empty($config['merchant_key'])) {
                    $errors[] = 'PayTR merchant key is required';
                }
                if (empty($config['merchant_salt'])) {
                    $errors[] = 'PayTR merchant salt is required';
                }
                break;
                
            case 'mollie':
                if (empty($config['api_key'])) {
                    $errors[] = 'Mollie API key is required';
                }
                break;
                
            case 'toyyibpay':
                if (empty($config['category_code'])) {
                    $errors[] = 'toyyibPay category code is required';
                }
                if (empty($config['secret_key'])) {
                    $errors[] = 'toyyibPay secret key is required';
                }
                break;
                
            case 'cashfree':
                if (empty($config['public_key'])) {
                    $errors[] = 'Cashfree App ID is required';
                }
                if (empty($config['secret_key'])) {
                    $errors[] = 'Cashfree Secret Key is required';
                }
                break;
                
            case 'iyzipay':
                if (empty($config['public_key'])) {
                    $errors[] = 'Iyzipay API key is required';
                }
                if (empty($config['secret_key'])) {
                    $errors[] = 'Iyzipay secret key is required';
                }
                break;
                
            case 'benefit':
                if (empty($config['public_key'])) {
                    $errors[] = 'Benefit API key is required';
                }
                if (empty($config['secret_key'])) {
                    $errors[] = 'Benefit secret key is required';
                }
                break;
                
            case 'ozow':
                if (empty($config['site_key'])) {
                    $errors[] = 'Ozow site key is required';
                }
                if (empty($config['private_key'])) {
                    $errors[] = 'Ozow private key is required';
                }
                break;
                
            case 'easebuzz':
                if (empty($config['merchant_key'])) {
                    $errors[] = 'Easebuzz merchant key is required';
                }
                if (empty($config['salt_key'])) {
                    $errors[] = 'Easebuzz salt key is required';
                }
                break;
                
            case 'khalti':
                if (empty($config['public_key'])) {
                    $errors[] = 'Khalti public key is required';
                }
                if (empty($config['secret_key'])) {
                    $errors[] = 'Khalti secret key is required';
                }
                break;
                
            case 'authorizenet':
                if (empty($config['merchant_id'])) {
                    $errors[] = 'AuthorizeNet merchant ID is required';
                }
                if (empty($config['transaction_key'])) {
                    $errors[] = 'AuthorizeNet transaction key is required';
                }
                break;
                
            case 'fedapay':
                if (empty($config['public_key'])) {
                    $errors[] = 'FedaPay public key is required';
                }
                if (empty($config['secret_key'])) {
                    $errors[] = 'FedaPay secret key is required';
                }
                break;
                
            case 'payhere':
                if (empty($config['merchant_id'])) {
                    $errors[] = 'PayHere merchant ID is required';
                }
                if (empty($config['merchant_secret'])) {
                    $errors[] = 'PayHere merchant secret is required';
                }
                break;
                
            case 'cinetpay':
                if (empty($config['site_id'])) {
                    $errors[] = 'CinetPay site ID is required';
                }
                if (empty($config['api_key'])) {
                    $errors[] = 'CinetPay API key is required';
                }
                break;
                
            case 'paiement':
                if (empty($config['merchant_id'])) {
                    $errors[] = 'Paiement Pro merchant ID is required';
                }
                break;
                
            case 'nepalste':
                if (empty($config['public_key'])) {
                    $errors[] = 'Nepalste public key is required';
                }
                if (empty($config['secret_key'])) {
                    $errors[] = 'Nepalste secret key is required';
                }
                break;
                
            case 'yookassa':
                if (empty($config['shop_id'])) {
                    $errors[] = 'YooKassa shop ID is required';
                }
                if (empty($config['secret_key'])) {
                    $errors[] = 'YooKassa secret key is required';
                }
                break;
                
            case 'midtrans':
                if (empty($config['secret_key'])) {
                    $errors[] = 'Midtrans secret key is required';
                }
                break;
                
            case 'aamarpay':
                if (empty($config['store_id'])) {
                    $errors[] = 'Aamarpay store ID is required';
                }
                if (empty($config['signature'])) {
                    $errors[] = 'Aamarpay signature is required';
                }
                break;
                            
            case 'paymentwall':
                if (empty($config['public_key'])) {
                    $errors[] = 'PaymentWall public key is required';
                }
                if (empty($config['private_key'])) {
                    $errors[] = 'PaymentWall private key is required';
                }
                break;
                
            case 'sspay':
                if (empty($config['secret_key'])) {
                    $errors[] = 'SSPay secret key is required';
                }
                break;
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

if (! function_exists('calculatePlanPricing')) {
    function calculatePlanPricing($plan, $couponCode = null, $billingCycle = 'monthly')
    {
        $originalPrice = $billingCycle === 'yearly' ? $plan->yearly_price : $plan->price;
        $discountAmount = 0;
        $finalPrice = $originalPrice;
        $couponId = null;
        
        if ($couponCode) {
            // usable(): enabled AND not past its expiry date - an expired coupon must
            // never discount a real payment, even if the UI let it through.
            $coupon = Coupon::where('code', $couponCode)
                ->usable()
                ->first();

            if ($coupon) {
                if ($coupon->type === 'percentage') {
                    $discountAmount = ($originalPrice * $coupon->discount_amount) / 100;
                } else {
                    $discountAmount = min($coupon->discount_amount, $originalPrice);
                }
                $finalPrice = max(0, $originalPrice - $discountAmount);
                $couponId = $coupon->id;
            }
        }
        
        return [
            'original_price' => $originalPrice,
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice,
            'coupon_id' => $couponId
        ];
    }
}

if (! function_exists('createPlanOrder')) {
    function createPlanOrder($data)
    {
        $plan = Plan::findOrFail($data['plan_id']);
        $pricing = calculatePlanPricing($plan, $data['coupon_code'] ?? null, $data['billing_cycle'] ?? 'monthly');
        
        return PlanOrder::create([
            'user_id' => $data['user_id'],
            'plan_id' => $plan->id,
            'coupon_id' => $pricing['coupon_id'],
            'billing_cycle' => $data['billing_cycle'] ?? 'monthly',
            'payment_method' => $data['payment_method'],
            'coupon_code' => $data['coupon_code'] ?? null,
            'original_price' => $pricing['original_price'],
            'discount_amount' => $pricing['discount_amount'],
            'final_price' => $pricing['final_price'],
            'payment_id' => $data['payment_id'],
            'status' => $data['status'] ?? 'pending',
            'ordered_at' => now(),
        ]);
    }
}

if (! function_exists('assignPlanToUser')) {
    function assignPlanToUser($user, $plan, $billingCycle)
    {
        $expiresAt = $billingCycle === 'yearly' ? now()->addYear() : now()->addMonth();

        if ($plan->plan_type === 'addon') {
            $user->update([
                'addon_plan_id' => $plan->id,
                'addon_expire_date' => $expiresAt,
                'addon_is_active' => 1,
            ]);
            return;
        }

        $user->update([
            'plan_id' => $plan->id,
            'plan_expire_date' => $expiresAt,
            'plan_is_active' => 1,
        ]);
    }
}

if (! function_exists('processPaymentSuccess')) {
    function processPaymentSuccess($data)
    {
        $plan = Plan::findOrFail($data['plan_id']);
        $user = User::findOrFail($data['user_id']);
        
        $planOrder = createPlanOrder(array_merge($data, ['status' => 'approved']));
        assignPlanToUser($user, $plan, $data['billing_cycle']);
        
        // Verify the plan was assigned
        $user->refresh();
        return $planOrder;
    }
}

if (! function_exists('platformPaymentSettings')) {
    /**
     * Payment gateway credentials that plan purchases are paid with - the
     * superadmin's (same account the plans page reads the public keys from).
     */
    function platformPaymentSettings(): array
    {
        $superAdminId = User::where('type', 'superadmin')->orderBy('id')->value('id');

        return $superAdminId ? PaymentSetting::getUserSettings($superAdminId, null) : [];
    }
}

if (! function_exists('createPendingPlanOrder')) {
    /**
     * Record a plan purchase that has been started but not yet confirmed by the
     * payment provider. The plan is only assigned once completePlanOrderPayment()
     * confirms it (or a superadmin approves the order by hand).
     */
    function createPendingPlanOrder(array $data): PlanOrder
    {
        return createPlanOrder(array_merge($data, ['status' => 'pending']));
    }
}

if (! function_exists('completePlanOrderPayment')) {
    /**
     * Approve a pending plan order once the provider has confirmed $paidAmount was
     * paid for it. Idempotent: an already-approved order is left as is. Returns
     * false when the order isn't pending or the amount doesn't cover the price.
     */
    function completePlanOrderPayment(PlanOrder $order, float $paidAmount, ?string $providerPaymentId = null): bool
    {
        if ($order->status === 'approved') {
            return true;
        }
        if ($order->status !== 'pending') {
            return false;
        }
        if (round($paidAmount, 2) + 0.009 < round((float) $order->final_price, 2)) {
            \Log::warning('Plan order payment amount mismatch', [
                'order' => $order->order_number, 'paid' => $paidAmount, 'expected' => $order->final_price,
            ]);
            return false;
        }

        if ($providerPaymentId && $providerPaymentId !== $order->payment_id) {
            $order->update(['notes' => trim(($order->notes ?? '') . ' provider_payment_id:' . $providerPaymentId)]);
        }

        $order->approve();

        return true;
    }
}

if (! function_exists('recordUnverifiedPlanPayment')) {
    /**
     * For gateways whose result we can't confirm with the provider on the server
     * yet: record the purchase as a pending plan order instead of activating the
     * plan. A superadmin approves it from Plan Orders once the money has arrived.
     * Idempotent per provider payment ID.
     */
    function recordUnverifiedPlanPayment(array $data): PlanOrder
    {
        if (!empty($data['payment_id'])) {
            $existing = PlanOrder::where('payment_id', $data['payment_id'])
                ->where('payment_method', $data['payment_method'] ?? null)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        \Log::info('Plan payment recorded as pending (not verified with provider)', [
            'method' => $data['payment_method'] ?? null, 'user_id' => $data['user_id'] ?? null, 'plan_id' => $data['plan_id'] ?? null,
        ]);

        return createPendingPlanOrder($data);
    }
}

if (! function_exists('planPaymentAlreadyUsed')) {
    /** A provider payment ID can only ever pay for one plan order. */
    function planPaymentAlreadyUsed(string $paymentId): bool
    {
        return PlanOrder::where('payment_id', $paymentId)->where('status', 'approved')->exists();
    }
}

if (! function_exists('isSaasMode')) {
    /**
     * Check if the application is running in SaaS mode
     *
     * @return bool
     */
    function isSaasMode()
    {
        return config('app.is_saas');
    }
}

if (! function_exists('getPaymentGatewaySettings')) {
    function getPaymentGatewaySettings($userId = null, $workspaceId = null)
    {
        // Use current authenticated user if no user specified
        if (is_null($userId) && auth()->check()) {
            $userId = auth()->id();
            $user = auth()->user();
            
            if ($user->type === 'company') {
                $workspaceId = $workspaceId ?? $user->current_workspace_id;
            } elseif ($user->type === 'superadmin') {
                $workspaceId = null;
            }
        }
        if (!$userId) {
            return [
                'payment_settings' => [],
                'general_settings' => [],
                'user_id' => null
            ];
        }
        
        $paymentSettings = PaymentSetting::getUserSettings($userId, $workspaceId);
        
        // If no payment settings found for company user, get from superadmin
        if (empty($paymentSettings) && auth()->check() && auth()->user()->type === 'company') {
            $superAdmin = User::where('type', 'superadmin')->first();
            if ($superAdmin) {
                $paymentSettings = PaymentSetting::getUserSettings($superAdmin->id, null);
            }
        }
        
        return [
            'payment_settings' => $paymentSettings,
            'general_settings' => settings($userId, $workspaceId),
            'user_id' => $userId
        ];
    }
}

if (! function_exists('validatePaymentRequest')) {
    function validatePaymentRequest($request, $additionalRules = [])
    {
        $baseRules = [
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
            'coupon_code' => 'nullable|string',
        ];
        
        return $request->validate(array_merge($baseRules, $additionalRules));
    }
}

if (! function_exists('handlePaymentError')) {
    function handlePaymentError($e, $method = 'payment')
    {
        return back()->withErrors(['error' => __('Payment processing failed: :message', ['message' => $e->getMessage()])]);
    }
}

if (! function_exists('defaultSettings')) {
    /**
     * Get default settings for System, Brand, Storage, and Currency configurations
     *
     * @return array
     */
    function defaultSettings()
    {
        $settings = [
            // System Settings
            'defaultLanguage' => 'en',
            'dateFormat' => 'Y-m-d',
            'timeFormat' => 'H:i',
            'calendarStartDay' => 'sunday',
            'defaultTimezone' => 'UTC',
            'emailVerification' => false,
            'landingPageEnabled' => true,
            
            // Brand Settings
            'logoDark' => '/images/logos/logo-dark.png',
            'logoLight' => '/images/logos/logo-light.png',
            'favicon' => '/images/logos/favicon.png',
            'titleText' => 'Taskly',
            'footerText' => '© ' . date('Y') . ' Taskly. All rights reserved.',
            'themeColor' => 'green',
            'customColor' => '#10b981',
            'sidebarVariant' => 'inset',
            'sidebarStyle' => 'plain',
            'layoutDirection' => 'left',
            'themeMode' => 'light',
            
            // Storage Settings
            'storage_type' => 'local',
            'storage_file_types' => 'jpg,png,webp,gif,pdf,doc,docx,txt,csv',
            'storage_max_upload_size' => '2048',
            'aws_access_key_id' => '',
            'aws_secret_access_key' => '',
            'aws_default_region' => 'us-east-1',
            'aws_bucket' => '',
            'aws_url' => '',
            'aws_endpoint' => '',
            'wasabi_access_key' => '',
            'wasabi_secret_key' => '',
            'wasabi_region' => 'us-east-1',
            'wasabi_bucket' => '',
            'wasabi_url' => '',
            'wasabi_root' => '',
            
            // Currency Settings
            'decimalFormat' => '2',
            'defaultCurrency' => 'USD',
            'decimalSeparator' => '.',
            'thousandsSeparator' => ',',
            'floatNumber' => true,
            'currencySymbolSpace' => false,
            'currencySymbolPosition' => 'before',
            
        ];

        if (isDemo()) {
            $cookieSettingArray = [
                'enableLogging' => true,
                'strictlyNecessaryCookies' => true,
                'cookieTitle' => 'Cookie Consent',
                'strictlyCookieTitle' => 'Strictly Necessary Cookies',
                'cookieDescription' => 'We use cookies to enhance your browsing experience and provide personalized content.',
                'strictlyCookieDescription' => 'These cookies are essential for the website to function properly.',
                'contactUsDescription' => 'If you have any questions about our cookie policy, please contact us.',
                'contactUsUrl' => 'https://example.com/contact',
            ];
            $settings = array_merge($settings, $cookieSettingArray);
        }
        return $settings;
    }
}

if (! function_exists('createDefaultSettings')) {
    /**
     * Create default settings for a user
     *
     * @param int $userId
     * @param int|null $workspaceId
     * @return void
     */
    function createDefaultSettings($userId, $workspaceId = null)
    {
        $defaults = defaultSettings();
        $settingsData = [];
        
        foreach ($defaults as $key => $value) {
            $settingsData[] = [
                'user_id' => $userId,
                'workspace_id' => $workspaceId,
                'key' => $key,
                'value' => is_bool($value) ? ($value ? '1' : '0') : (string)$value,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        Setting::insert($settingsData);
    }
}

if (! function_exists('getSidebarLogo')) {
    function getSidebarLogo()
    {
        $appearance = getSetting('themeMode', 'light');
        $logoLight = getSetting('logoLight', '/images/logos/logo-light.png');
        $logoDark = getSetting('logoDark', '/images/logos/logo-dark.png');
        
        $logoPath = $appearance === 'dark' ? $logoLight : $logoDark;
        return $logoPath;
    }
}

if (! function_exists('getFile')) {
    function getFile($path)
    {
        $storageSettings = getSetting('storage_setting', 'local');
        
        if ($storageSettings == 's3') {
            $s3Key = getSetting('s3_key');
            $s3Secret = getSetting('s3_secret');
            $s3Region = getSetting('s3_region');
            $s3Bucket = getSetting('s3_bucket');
            $s3Url = getSetting('s3_url');
            $s3Endpoint = getSetting('s3_endpoint');
            
            if ($s3Key && $s3Secret && $s3Region && $s3Bucket) {
                config([
                    'filesystems.disks.s3.key' => $s3Key,
                    'filesystems.disks.s3.secret' => $s3Secret,
                    'filesystems.disks.s3.region' => $s3Region,
                    'filesystems.disks.s3.bucket' => $s3Bucket,
                    'filesystems.disks.s3.url' => $s3Url,
                    'filesystems.disks.s3.endpoint' => $s3Endpoint,
                ]);
                return \Storage::disk('s3')->url($path);
            }
        } else if ($storageSettings == 'wasabi') {
            $wasabiKey = getSetting('wasabi_key');
            $wasabiSecret = getSetting('wasabi_secret');
            $wasabiRegion = getSetting('wasabi_region');
            $wasabiBucket = getSetting('wasabi_bucket');
            $wasabiRoot = getSetting('wasabi_root');
            $wasabiUrl = getSetting('wasabi_url');
            
            if ($wasabiKey && $wasabiSecret && $wasabiRegion && $wasabiBucket) {
                config([
                    'filesystems.disks.wasabi.key' => $wasabiKey,
                    'filesystems.disks.wasabi.secret' => $wasabiSecret,
                    'filesystems.disks.wasabi.region' => $wasabiRegion,
                    'filesystems.disks.wasabi.bucket' => $wasabiBucket,
                    'filesystems.disks.wasabi.root' => $wasabiRoot,
                    'filesystems.disks.wasabi.endpoint' => $wasabiUrl
                ]);
                return \Storage::disk('wasabi')->url($path);
            }
        }

        // Some settings (e.g. branding logos) are stored as full URLs already;
        // prefixing those produced "https://hosthttps://host/..." (QA L2).
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return config('app.url') . $path;
    }
}

if (! function_exists('copySettingsFromSuperAdmin')) {
    /**
     * Copy system and brand settings from superadmin to company user workspace
     *
     * @param int $companyUserId
     * @param int $workspaceId
     * @return void
     */
    function copySettingsFromSuperAdmin($companyUserId, $workspaceId)
    {
        $superAdmin = User::where('type', 'superadmin')->first();
        if (!$superAdmin) {
            createDefaultSettings($companyUserId, $workspaceId);
            return;
        }
        
        // Settings to copy from superadmin (system and brand settings only)
        $settingsToCopy = [
            'defaultLanguage', 'dateFormat', 'timeFormat', 'calendarStartDay', 
            'defaultTimezone', 'emailVerification', 'landingPageEnabled',
            'logoDark', 'logoLight', 'favicon', 'titleText', 'footerText',
            'themeColor', 'customColor', 'sidebarVariant', 'sidebarStyle',
            'layoutDirection', 'themeMode'
        ];
        
        $superAdminSettings = Setting::where('user_id', $superAdmin->id)
            ->where('workspace_id', null)
            ->whereIn('key', $settingsToCopy)
            ->get();
        
        $settingsData = [];
        
        // Only copy existing superadmin settings
        foreach ($superAdminSettings as $setting) {
            $settingsData[] = [
                'user_id' => $companyUserId,
                'workspace_id' => $workspaceId,
                'key' => $setting->key,
                'value' => $setting->value,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        Setting::insert($settingsData);
    }
if (!function_exists('parseBrowserData')) {
    function parseBrowserData(string $userAgent): array
    {
        $browser = 'Unknown';
        $os = 'Unknown';
        $deviceType = 'desktop';

        // Browser detection
        if (preg_match('/Chrome\/([0-9.]+)/', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox\/([0-9.]+)/', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari\/([0-9.]+)/', $userAgent) && !preg_match('/Chrome/', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edge\/([0-9.]+)/', $userAgent)) {
            $browser = 'Edge';
        }

        // OS detection
        if (preg_match('/Windows NT/', $userAgent)) {
            $os = 'Windows';
        } elseif (preg_match('/Mac OS X/', $userAgent)) {
            $os = 'macOS';
        } elseif (preg_match('/Linux/', $userAgent)) {
            $os = 'Linux';
        } elseif (preg_match('/Android/', $userAgent)) {
            $os = 'Android';
            $deviceType = 'mobile';
        } elseif (preg_match('/iPhone|iPad/', $userAgent)) {
            $os = 'iOS';
            $deviceType = preg_match('/iPad/', $userAgent) ? 'tablet' : 'mobile';
        }

        return [
            'browser_name' => $browser,
            'os_name' => $os,
            'browser_language' => 'en',
            'device_type' => $deviceType,
        ];
    }
}

if (! function_exists('isEmailTemplateEnabled')) {
    /**
     * Check if an email template is enabled for a user
     *
     * @param string $templateName
     * @param int|null $userId
     * @return bool
     */
    function isEmailTemplateEnabled($templateName, $userId = null)
    {
        if (is_null($userId)) {
            $userId = auth()->id();
        }

        $template = \App\Models\EmailTemplate::where('name', $templateName)->first();
        if (!$template) {
            return false;
        }

        $userTemplate = \App\Models\UserEmailTemplate::where('user_id', $userId)
            ->where('template_id', $template->id)
            ->first();

        return $userTemplate ? $userTemplate->is_active : false;
    }
}

if (! function_exists('isNotificationTemplateEnabled')) {
    /**
     * Check if a notification template is enabled for a user
     *
     * @param string $templateName
     * @param int|null $userId
     * @return bool
     */
    function isNotificationTemplateEnabled($templateName, $userId = null)
    {
        if (is_null($userId)) {
            $userId = createdBy();
        }

        $template = \App\Models\NotificationTemplate::where('name', $templateName)->first();
        if (!$template) {
            return false;
        }

        return $template->is_active ?? false;
    }
}
}
if (!function_exists('isDemo')) {
    function isDemo()
    {
        $isDemo = config('app.is_demo');
        return $isDemo;
    }
}

if (! function_exists('createdBy')) {
    function createdBy()
    {
        if (auth()->check()) {
            if (auth()->user()->type == 'superadmin') {
                return auth()->user()->id;
            } else if (auth()->user()->type == 'company') {
                return auth()->user()->id;
            } else {
                return auth()->user()->created_by;
            }
        }
        return null;
    }
}

if (! function_exists('getCompanyName')) {
    function getCompanyName()
    {
        $company = User::find(createdBy());
        if ($company) {
            return $company->name;
        } else {
            return 'Taskly';
        }
    }
}

if (! function_exists('getTwilioConfig')) {
    function getTwilioConfig()
    {
        return [
            'twilio_sid' => getSetting('twilio_sid', ''),
            'twilio_token' => getSetting('twilio_token', ''),
            'twilio_from' => getSetting('twilio_from', '')
        ];
    }
}

if (! function_exists('isSlackNotificationEnabled')) {
    function isSlackNotificationEnabled($notificationType, $userId = null, $workspaceId = null)
    {
        try {
            if (is_null($userId)) {
                $userId = createdBy();
            }

            $slackNotifications = \App\Models\SlackSetting::where('user_id', $userId)
                ->where('workspace_id', $workspaceId)
                ->where('key', 'slack_notifications')
                ->first();

            if (!$slackNotifications) {
                return false;
            }

            $notifications = json_decode($slackNotifications->value, true);
            return $notifications[$notificationType] ?? false;
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (! function_exists('isTelegramNotificationEnabled')) {
    function isTelegramNotificationEnabled($notificationType, $userId = null, $workspaceId = null)
    {
        try {
            if (is_null($userId)) {
                $userId = createdBy();
            }

            $telegramNotifications = \App\Models\TelegramSetting::where('user_id', $userId)
                ->where('workspace_id', $workspaceId)
                ->where('key', 'telegram_notifications')
                ->first();

            if (!$telegramNotifications) {
                return false;
            }

            $notifications = json_decode($telegramNotifications->value, true);
            return $notifications[$notificationType] ?? false;
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (! function_exists('upload_file')) {
    function upload_file($request, $key_name, $name, $path, $custom_validation = [])
    {
        try {
            $storage_settings = settings();
            if (!isset($storage_settings['storage_type'])) {
                $storage_settings['storage_type'] = 'local';
            }
            $max_size  = !empty($storage_settings['storage_max_upload_size']) ? $storage_settings['storage_max_upload_size'] : '2048';
            $mimes     = !empty($storage_settings['storage_file_types'])      ? $storage_settings['storage_file_types']      : defaultSettings()['storage_file_types'];
            if ($storage_settings['storage_type'] == 'wasabi') {
                config(['filesystems.disks.wasabi.driver'=>'s3','filesystems.disks.wasabi.key'=>$storage_settings['wasabi_access_key'],'filesystems.disks.wasabi.secret'=>$storage_settings['wasabi_secret_key'],'filesystems.disks.wasabi.region'=>$storage_settings['wasabi_region']??'us-east-1','filesystems.disks.wasabi.bucket'=>$storage_settings['wasabi_bucket'],'filesystems.disks.wasabi.endpoint'=>$storage_settings['wasabi_url'],'filesystems.disks.wasabi.root'=>$storage_settings['wasabi_root'],'filesystems.disks.wasabi.visibility'=>'public']);
            } elseif ($storage_settings['storage_type'] == 'aws_s3') {
                config(['filesystems.disks.s3.driver'=>'s3','filesystems.disks.s3.key'=>$storage_settings['aws_access_key_id'],'filesystems.disks.s3.secret'=>$storage_settings['aws_secret_access_key'],'filesystems.disks.s3.region'=>$storage_settings['aws_default_region']??'us-east-1','filesystems.disks.s3.bucket'=>$storage_settings['aws_bucket'],'filesystems.disks.s3.url'=>$storage_settings['aws_url']??null,'filesystems.disks.s3.endpoint'=>$storage_settings['aws_endpoint']??null,'filesystems.disks.s3.visibility'=>'public']);
            }
            $file = $request->$key_name;
            $extension = strtolower($file->getClientOriginalExtension());
            $allowed = explode(',', $mimes);
            if (empty($extension) || !in_array($extension, $allowed)) {
                return ['status' => false, 'msg' => 'The '.$key_name.' must be a file of type: '.implode(', ', $allowed).'.'];
            }
            $validation = count($custom_validation) > 0 ? $custom_validation : ['mimes:'.$mimes, 'max:'.$max_size];
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [$key_name => $validation]);
            if ($validator->fails()) {
                return ['status' => false, 'msg' => $validator->messages()->first()];
            }
            $storageType = $storage_settings['storage_type'] ?? 'local';
            $diskName = match ($storageType) { 'aws_s3' => 's3', 'wasabi' => 'wasabi', default => 'public' };
            $file->storeAs('media/'.$path, $name, $diskName);
            return ['status' => true, 'msg' => 'success', 'url' => $path.'/'.$name];
        } catch (\Exception $e) {
            return ['status' => false, 'msg' => $e->getMessage()];
        }
    }
}

if (! function_exists('delete_file')) {
    function delete_file($filePath)
    {
        try {
            $storage_settings = settings();
            $storage_type = $storage_settings['storage_type'] ?? 'local';
            if ($storage_type == 'wasabi') {
                config(['filesystems.disks.wasabi.key'=>$storage_settings['wasabi_access_key'],'filesystems.disks.wasabi.secret'=>$storage_settings['wasabi_secret_key'],'filesystems.disks.wasabi.region'=>$storage_settings['wasabi_region']??'us-east-1','filesystems.disks.wasabi.bucket'=>$storage_settings['wasabi_bucket'],'filesystems.disks.wasabi.endpoint'=>$storage_settings['wasabi_url'],'filesystems.disks.wasabi.root'=>$storage_settings['wasabi_root']]);
                $diskName = 'wasabi';
            } elseif ($storage_type == 'aws_s3') {
                config(['filesystems.disks.s3.key'=>$storage_settings['aws_access_key_id'],'filesystems.disks.s3.secret'=>$storage_settings['aws_secret_access_key'],'filesystems.disks.s3.region'=>$storage_settings['aws_default_region']??'us-east-1','filesystems.disks.s3.bucket'=>$storage_settings['aws_bucket']]);
                $diskName = 's3';
            } else {
                $diskName = 'public';
            }
            if (\Illuminate\Support\Facades\Storage::disk($diskName)->exists('media/'.$filePath)) {
                \Illuminate\Support\Facades\Storage::disk($diskName)->delete('media/'.$filePath);
                return true;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (! function_exists('download_file')) {
    function download_file($filePath, $fileName = null)
    {
        try {
            $storage_settings = settings();
            $storage_type = $storage_settings['storage_type'] ?? 'local';
            if ($storage_type == 'wasabi') {
                config(['filesystems.disks.wasabi.key'=>$storage_settings['wasabi_access_key'],'filesystems.disks.wasabi.secret'=>$storage_settings['wasabi_secret_key'],'filesystems.disks.wasabi.region'=>$storage_settings['wasabi_region']??'us-east-1','filesystems.disks.wasabi.bucket'=>$storage_settings['wasabi_bucket'],'filesystems.disks.wasabi.endpoint'=>$storage_settings['wasabi_url'],'filesystems.disks.wasabi.root'=>$storage_settings['wasabi_root']]);
                $diskName = 'wasabi';
            } elseif ($storage_type == 'aws_s3') {
                config(['filesystems.disks.s3.key'=>$storage_settings['aws_access_key_id'],'filesystems.disks.s3.secret'=>$storage_settings['aws_secret_access_key'],'filesystems.disks.s3.region'=>$storage_settings['aws_default_region']??'us-east-1','filesystems.disks.s3.bucket'=>$storage_settings['aws_bucket']]);
                $diskName = 's3';
            } else {
                $diskName = 'public';
            }
            if (!\Illuminate\Support\Facades\Storage::disk($diskName)->exists('media/'.$filePath)) {
                abort(404, 'File not found.');
            }
            return \Illuminate\Support\Facades\Storage::disk($diskName)->download('media/'.$filePath, $fileName ?? basename($filePath));
        } catch (\Exception $e) {
            abort(404, 'File not found.');
        }
    }
}

if (! function_exists('enableWorkspaceInvitationForUser')) {
    function enableWorkspaceInvitationForUser($userId, $workspaceId = null)
    {
        $template = \App\Models\EmailTemplate::where('name', 'Workspace Invitation')->first();
        if ($template) {
            \App\Models\UserEmailTemplate::updateOrCreate(
                ['user_id' => $userId, 'template_id' => $template->id, 'workspace_id' => $workspaceId],
                ['is_active' => true]
            );
        }
    }
}

if (! function_exists('getAdminAllSetting')) {
    function getAdminAllSetting()
    {
        return \Illuminate\Support\Facades\Cache::rememberForever('admin_settings', function () {
            if (isSaasMode()) {
                $superAdmin = \App\Models\User::where('type', 'superadmin')->first();
            } else {
                $superAdmin = \App\Models\User::where('type', 'company')->first();
            }
            $settings = [];
            if ($superAdmin) {
                $settings = \App\Models\Setting::where('user_id', $superAdmin->id)->pluck('value', 'key')->toArray();
            }
            return $settings;
        });
    }
}

if (! function_exists('check_file')) {
    function check_file($path)
    {
        try {
            if (empty($path)) {
                return false;
            }
            if (str_starts_with($path, 'http') || str_starts_with($path, 'https')) {
                return true;
            }
            $storage_settings = getAdminAllSetting();
            if (! isset($storage_settings['storage_type'])) {
                return false;
            }
            $storageType = $storage_settings['storage_type'];
            if ($storageType === 'local' || $storageType === null) {
                $publicPath = storage_path('app/public/media/'.ltrim($path, '/'));
                if (file_exists($publicPath)) {
                    return true;
                }
                return file_exists(base_path($path));
            }
            if ($storageType === 'aws_s3') {
                if (empty($storage_settings['aws_access_key_id']) || empty($storage_settings['aws_secret_access_key']) ||
                    empty($storage_settings['aws_default_region']) || empty($storage_settings['aws_bucket'])) {
                    return false;
                }
                config([
                    'filesystems.disks.s3.key'    => $storage_settings['aws_access_key_id'],
                    'filesystems.disks.s3.secret' => $storage_settings['aws_secret_access_key'],
                    'filesystems.disks.s3.region' => $storage_settings['aws_default_region'] ?? 'us-east-1',
                    'filesystems.disks.s3.bucket' => $storage_settings['aws_bucket'],
                ]);
                return \Illuminate\Support\Facades\Storage::disk('s3')->exists('media/'.ltrim($path, '/'));
            }
            if ($storageType === 'wasabi') {
                if (empty($storage_settings['wasabi_access_key']) || empty($storage_settings['wasabi_secret_key']) ||
                    empty($storage_settings['wasabi_region']) || empty($storage_settings['wasabi_bucket'])) {
                    return false;
                }
                config([
                    'filesystems.disks.wasabi.key'      => $storage_settings['wasabi_access_key'],
                    'filesystems.disks.wasabi.secret'   => $storage_settings['wasabi_secret_key'],
                    'filesystems.disks.wasabi.region'   => $storage_settings['wasabi_region'] ?? 'us-east-1',
                    'filesystems.disks.wasabi.bucket'   => $storage_settings['wasabi_bucket'],
                    'filesystems.disks.wasabi.endpoint' => $storage_settings['wasabi_url'] ?? null,
                    'filesystems.disks.wasabi.root'     => $storage_settings['wasabi_root'] ?? '',
                ]);
                return \Illuminate\Support\Facades\Storage::disk('wasabi')->exists('media/'.ltrim($path, '/'));
            }
            return false;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('check_file error: '.$e->getMessage(), ['path' => $path]);
            return false;
        }
    }
}

if (! function_exists('get_file')) {
    function get_file($path)
    {
        try {
            if (empty($path)) {
                return asset('images/avatar/avatar.png');
            }
            if (str_starts_with($path, 'http') || str_starts_with($path, 'https')) {
                return $path;
            }
            $storage_settings = getAdminAllSetting();
            if (! isset($storage_settings['storage_type'])) {
                // Check if local file exists; fall back to public default
                $localPath = storage_path('app/public/media/'.ltrim($path, '/'));
                if (file_exists($localPath)) {
                    return url('storage/media/'.ltrim($path, '/'));
                }
                return asset('images/avatar/avatar.png');
            }
            $storageType = $storage_settings['storage_type'];
            if ($storageType === 'aws_s3' || $storageType === 's3') {
                if (empty($storage_settings['s3_key']) || empty($storage_settings['s3_secret']) ||
                    empty($storage_settings['s3_region']) || empty($storage_settings['s3_bucket'])) {
                    return url('storage/media/'.ltrim($path, '/'));
                }
                config([
                    'filesystems.disks.s3.key'    => $storage_settings['s3_key'],
                    'filesystems.disks.s3.secret' => $storage_settings['s3_secret'],
                    'filesystems.disks.s3.region' => $storage_settings['s3_region'],
                    'filesystems.disks.s3.bucket' => $storage_settings['s3_bucket'],
                ]);
                return \Illuminate\Support\Facades\Storage::disk('s3')->url('media/'.ltrim($path, '/'));
            }
            if ($storageType === 'wasabi') {
                if (empty($storage_settings['wasabi_key']) || empty($storage_settings['wasabi_secret']) ||
                    empty($storage_settings['wasabi_region']) || empty($storage_settings['wasabi_bucket'])) {
                    return url('storage/media/'.ltrim($path, '/'));
                }
                config([
                    'filesystems.disks.wasabi.key'      => $storage_settings['wasabi_key'],
                    'filesystems.disks.wasabi.secret'   => $storage_settings['wasabi_secret'],
                    'filesystems.disks.wasabi.region'   => $storage_settings['wasabi_region'],
                    'filesystems.disks.wasabi.bucket'   => $storage_settings['wasabi_bucket'],
                    'filesystems.disks.wasabi.root'     => $storage_settings['wasabi_root'],
                    'filesystems.disks.wasabi.endpoint' => $storage_settings['wasabi_url'],
                ]);
                return \Illuminate\Support\Facades\Storage::disk('wasabi')->url('media/'.ltrim($path, '/'));
            }
            $localPath = storage_path('app/public/media/'.ltrim($path, '/'));
            if (file_exists($localPath)) {
                return url('storage/media/'.ltrim($path, '/'));
            }
            return asset('images/avatar/avatar.png');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('get_file error: '.$e->getMessage(), ['path' => $path]);
            return asset('images/avatar/avatar.png');
        }
    }
}

if (! function_exists('isRegistrationEnabled')) {
    function isRegistrationEnabled()
    {
        return getSetting('registrationEnabled', true) === true || getSetting('registrationEnabled', true) === '1';
    }
}

if (! function_exists('defultnotificationAndsetting')) {
    function defultnotificationAndsetting($user)
    {
        $templates = \App\Models\NotificationTemplate::all();
        foreach ($templates as $template) {
            $defaultLangs = \App\Models\NotificationTemplateLang::where('parent_id', $template->id)->where('created_by', 1)->get();
            foreach ($defaultLangs as $defaultLang) {
                \App\Models\NotificationTemplateLang::updateOrCreate(
                    ['parent_id' => $template->id, 'lang' => $defaultLang->lang, 'created_by' => $user],
                    ['title' => $defaultLang->title, 'content' => $defaultLang->content]
                );
            }
        }
    }
}

if (! function_exists('getPaymentMethodConfig2')) {
    function getPaymentMethodConfig2($method, $userId = null, $workspaceId = null)
    {
        $settings = getPaymentSettings($userId, $workspaceId);
        $enabled = isPaymentMethodEnabled($method, $userId, $workspaceId);
        switch ($method) {
            case 'paytr':      return ['enabled'=>$enabled,'merchant_id'=>$settings['paytr_merchant_id']??null,'merchant_key'=>$settings['paytr_merchant_key']??null,'merchant_salt'=>$settings['paytr_merchant_salt']??null];
            case 'mollie':     return ['enabled'=>$enabled,'api_key'=>$settings['mollie_api_key']??null];
            case 'toyyibpay':  return ['enabled'=>$enabled,'category_code'=>$settings['toyyibpay_category_code']??null,'secret_key'=>$settings['toyyibpay_secret_key']??null,'mode'=>$settings['toyyibpay_mode']??'sandbox'];
            case 'cashfree':   return ['enabled'=>$enabled,'mode'=>$settings['cashfree_mode']??'sandbox','public_key'=>$settings['cashfree_public_key']??null,'secret_key'=>$settings['cashfree_secret_key']??null];
            case 'iyzipay':    return ['enabled'=>$enabled,'mode'=>$settings['iyzipay_mode']??'sandbox','public_key'=>$settings['iyzipay_public_key']??null,'secret_key'=>$settings['iyzipay_secret_key']??null];
            case 'benefit':    return ['enabled'=>$enabled,'mode'=>$settings['benefit_mode']??'sandbox','public_key'=>$settings['benefit_public_key']??null,'secret_key'=>$settings['benefit_secret_key']??null];
            case 'ozow':       return ['enabled'=>$enabled,'mode'=>$settings['ozow_mode']??'sandbox','site_key'=>$settings['ozow_site_key']??null,'private_key'=>$settings['ozow_private_key']??null,'api_key'=>$settings['ozow_api_key']??null];
            case 'easebuzz':   return ['enabled'=>$enabled,'merchant_key'=>$settings['easebuzz_merchant_key']??null,'salt_key'=>$settings['easebuzz_salt_key']??null,'environment'=>$settings['easebuzz_environment']??'test'];
            case 'khalti':     return ['enabled'=>$enabled,'public_key'=>$settings['khalti_public_key']??null,'secret_key'=>$settings['khalti_secret_key']??null];
            case 'authorizenet':return ['enabled'=>$enabled,'mode'=>$settings['authorizenet_mode']??'sandbox','merchant_id'=>$settings['authorizenet_merchant_id']??null,'transaction_key'=>$settings['authorizenet_transaction_key']??null];
            case 'fedapay':    return ['enabled'=>$enabled,'mode'=>$settings['fedapay_mode']??'sandbox','public_key'=>$settings['fedapay_public_key']??null,'secret_key'=>$settings['fedapay_secret_key']??null];
            case 'payhere':    return ['enabled'=>$enabled,'mode'=>$settings['payhere_mode']??'sandbox','merchant_id'=>$settings['payhere_merchant_id']??null,'merchant_secret'=>$settings['payhere_merchant_secret']??null];
            case 'cinetpay':   return ['enabled'=>$enabled,'site_id'=>$settings['cinetpay_site_id']??null,'api_key'=>$settings['cinetpay_api_key']??null,'secret_key'=>$settings['cinetpay_secret_key']??null];
            case 'midtrans':   return ['enabled'=>$enabled,'mode'=>$settings['midtrans_mode']??'sandbox','secret_key'=>$settings['midtrans_secret_key']??null,'client_key'=>$settings['midtrans_client_key']??null];
            default:           return [];
        }
    }
}

if (! function_exists('emailNotificationEnabled')) {
    function emailNotificationEnabled($userId = null, $workspaceId = null)
    {
        if (is_null($userId)) $userId = createdBy();
        if (is_null($workspaceId) && auth()->check() && auth()->user()->type === 'company') {
            $workspaceId = auth()->user()->current_workspace_id;
        }
        $template = \App\Models\EmailTemplate::where('name', 'Invoice Notification')->first();
        if (!$template) return false;
        $userTemplate = \App\Models\UserEmailTemplate::where('user_id', $userId)->where('template_id', $template->id)->where('workspace_id', $workspaceId)->first();
        return $userTemplate ? $userTemplate->is_active : false;
    }
}

if (! function_exists('isTelegramEnabled')) {
    function isTelegramEnabled($userId = null)
    {
        if (is_null($userId)) $userId = createdBy();
        return getSetting('telegram_enabled', false, $userId) === '1';
    }
}

if (! function_exists('isSlackEnabled')) {
    function isSlackEnabled($userId = null)
    {
        if (is_null($userId)) $userId = createdBy();
        return getSetting('slack_enabled', false, $userId) === '1';
    }
}

if (! function_exists('getSlackWebhookUrl')) {
    function getSlackWebhookUrl($userId = null)
    {
        if (is_null($userId)) $userId = createdBy();
        return getSetting('slack_webhook_url', '', $userId);
    }
}

if (! function_exists('getTelegramConfig')) {
    function getTelegramConfig()
    {
        return ['telegram_bot_token' => getSetting('telegram_bot_token', ''), 'telegram_chat_id' => getSetting('telegram_chat_id', '')];
    }
}

if (! function_exists('isNotificationTypeEnabled')) {
    function isNotificationTypeEnabled($templateName, $type, $userId = null)
    {
        if (is_null($userId)) $userId = createdBy();
        return \App\Models\UserNotificationTemplate::isNotificationActive($templateName, $userId, $type);
    }
}

if (! function_exists('setNotificationTypeStatus')) {
    function setNotificationTypeStatus($templateName, $type, $isActive, $userId = null)
    {
        if (is_null($userId)) $userId = createdBy();
        return \App\Models\UserNotificationTemplate::setNotificationStatus($templateName, $userId, $type, $isActive);
    }
}

if (! function_exists('createDefaultNotificationTemplates')) {
    function createDefaultNotificationTemplates($companyId)
    {
        $languages = json_decode(file_get_contents(resource_path('lang/language.json')), true);
        $langCodes = collect($languages)->pluck('code')->toArray();
        $templates = \App\Models\NotificationTemplate::all();
        foreach ($templates as $template) {
            foreach ($langCodes as $langCode) {
                if (\App\Models\NotificationTemplateLang::where('parent_id', $template->id)->where('lang', $langCode)->where('created_by', $companyId)->exists()) continue;
                $global = \App\Models\NotificationTemplateLang::where('parent_id', $template->id)->where('lang', $langCode)->where('created_by', 1)->first();
                if ($global) {
                    \App\Models\NotificationTemplateLang::create(['parent_id'=>$template->id,'lang'=>$langCode,'title'=>$global->title,'content'=>$global->content,'created_by'=>$companyId]);
                }
            }
        }
    }
}

if (! function_exists('createDefaultNotificationTemplateSettings')) {
    function createDefaultNotificationTemplateSettings($userId)
    {
        $templates = \App\Models\NotificationTemplate::all();
        foreach ($templates as $template) {
            \App\Models\UserNotificationTemplate::updateOrCreate(
                ['user_id' => $userId, 'template_id' => $template->id],
                ['is_active' => false]
            );
        }
    }
}