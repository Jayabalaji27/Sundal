<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Services\StorageConfigService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

class SystemSettingsController extends Controller
{
    /**
     * Update the system settings.
     *
     * Handles system-wide configuration including:
     * - Language and localization settings
     * - Date/time formats and timezone
     * - Email verification requirements
     * - Landing page enable/disable toggle
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
        try {
            $user = auth()->user();
            $isSaasCompany = isSaasMode() && $user->type === 'company';

            // SaaS company users can only update limited fields
            if ($isSaasCompany) {
                $validated = $request->validate([
                    'defaultLanguage' => 'required|string',
                    'dateFormat'      => 'required|string',
                    'timeFormat'      => 'required|string',
                ]);
            } else {
                $validated = $request->validate([
                    'defaultLanguage'   => 'required|string',
                    'dateFormat'        => 'required|string',
                    'timeFormat'        => 'required|string',
                    'calendarStartDay'  => 'required|string',
                    'defaultTimezone'   => 'required|string',
                    'emailVerification' => 'boolean',
                    'landingPageEnabled'=> 'boolean',
                    'registrationEnabled' => 'boolean',
                    'termsConditionsUrl'  => 'nullable|url',
                ]);
            }

            $workspaceId = ($user->type === 'company') ? $user->current_workspace_id : null;

            foreach ($validated as $key => $value) {
                $convertedValue = is_bool($value) ? ($value ? '1' : '0') : $value;
                updateSetting($key, $convertedValue, $user->id, $workspaceId);
            }

            return redirect()->back()->with('success', __('System settings updated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update system settings: :error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Update the brand settings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateBrand(Request $request)
    {
        try {
            $validated = $request->validate([
                'settings' => 'required|array',
                'settings.logoDark' => 'nullable|string',
                'settings.logoLight' => 'nullable|string',
                'settings.favicon' => 'nullable|string',
                'settings.titleText' => 'nullable|string|max:255',
                'settings.footerText' => 'nullable|string|max:500',
                'settings.themeColor' => 'nullable|string|in:blue,green,purple,orange,red,custom',
                'settings.customColor' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'settings.sidebarVariant' => 'nullable|string|in:inset,floating,minimal',
                'settings.sidebarStyle' => 'nullable|string|in:plain,colored,gradient',
                'settings.layoutDirection' => 'nullable|string|in:left,right',
                'settings.themeMode' => 'nullable|string|in:light,dark,system',
            ]);

            // Check if application is in demo mode
            if (config('app.is_demo')) {
                // Demo mode: store ONLY theme settings in cookies (exclude logos and text)
                $themeOnlySettings = [
                    'themeColor' => $validated['settings']['themeColor'] ?? 'green',
                    'customColor' => $validated['settings']['customColor'] ?? '#10B77F',
                    'sidebarVariant' => $validated['settings']['sidebarVariant'] ?? 'inset',
                    'sidebarStyle' => $validated['settings']['sidebarStyle'] ?? 'plain',
                    'layoutDirection' => $validated['settings']['layoutDirection'] ?? 'left',
                    'themeMode' => $validated['settings']['themeMode'] ?? 'light',
                ];
                // Store as single cookie that frontend expects
                cookie()->queue(cookie('brandSettings', json_encode($themeOnlySettings), 60 * 24 * 30));
                cookie()->queue(cookie('layoutDirection', $validated['settings']['layoutDirection'] ?? 'left', 60 * 24 * 30));

                return redirect()->back()->with('success', __('Theme settings saved successfully!'));
            } else {
                // Normal mode: store ONLY in database (no cookies)
                $user = auth()->user();
                $workspaceId = null;

                // For superadmin, workspace_id is always null
                // For company users, use current workspace
                if ($user->type === 'company') {
                    $workspaceId = $user->current_workspace_id;
                } else if ($user->type === 'superadmin') {
                    $workspaceId = null;
                }

                foreach ($validated['settings'] as $key => $value) {
                    updateSetting($key, $value, $user->id, $workspaceId);
                }

                // Silent mode (e.g., theme toggle from header) — no flash message
                if ($request->boolean('_silent')) {
                    return response()->json(['success' => true]);
                }

                return redirect()->back()->with('success', __('Brand settings updated successfully.'));
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update brand settings: :error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Update the acting user's own personal display preferences (theme mode,
     * accent color, sidebar style, layout direction). Unlike updateBrand()
     * above, this is open to every authenticated role, not just settings_brand
     * holders — these are per-user rows (updateSetting keys on $user->id), not
     * the company's shared logo/title/footer identity, so there's no reason a
     * Manager/Member/Client can't set their own dark mode or RTL preference.
     * Used by the header dark-mode toggle and the language switcher's RTL
     * layout flip, both of which are visible regardless of settings_brand.
     */
    public function updateAppearance(Request $request)
    {
        try {
            $validated = $request->validate([
                'settings' => 'required|array',
                'settings.themeMode' => 'nullable|string|in:light,dark,system',
                'settings.themeColor' => 'nullable|string|in:blue,green,purple,orange,red,custom',
                'settings.customColor' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'settings.sidebarVariant' => 'nullable|string|in:inset,floating,minimal',
                'settings.sidebarStyle' => 'nullable|string|in:plain,colored,gradient',
                'settings.layoutDirection' => 'nullable|string|in:left,right',
            ]);

            if (config('app.is_demo')) {
                $themeOnlySettings = [
                    'themeColor' => $validated['settings']['themeColor'] ?? 'green',
                    'customColor' => $validated['settings']['customColor'] ?? '#10B77F',
                    'sidebarVariant' => $validated['settings']['sidebarVariant'] ?? 'inset',
                    'sidebarStyle' => $validated['settings']['sidebarStyle'] ?? 'plain',
                    'layoutDirection' => $validated['settings']['layoutDirection'] ?? 'left',
                    'themeMode' => $validated['settings']['themeMode'] ?? 'light',
                ];
                cookie()->queue(cookie('brandSettings', json_encode($themeOnlySettings), 60 * 24 * 30));
                cookie()->queue(cookie('layoutDirection', $validated['settings']['layoutDirection'] ?? 'left', 60 * 24 * 30));
            } else {
                $user = auth()->user();
                $workspaceId = $user->type === 'company' ? $user->current_workspace_id : null;

                foreach ($validated['settings'] as $key => $value) {
                    updateSetting($key, $value, $user->id, $workspaceId);
                }
            }

            if ($request->boolean('_silent')) {
                return response()->json(['success' => true]);
            }

            return redirect()->back()->with('success', __('Preferences updated.'));
        } catch (\Exception $e) {
            if ($request->boolean('_silent')) {
                return response()->json(['success' => false], 422);
            }
            return redirect()->back()->with('error', __('Failed to update preferences: :error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Update the recaptcha settings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateRecaptcha(Request $request)
    {
        try {
            $validated = $request->validate([
                'recaptchaEnabled' => 'boolean',
                'recaptchaVersion' => 'required|in:v2,v3',
                'recaptchaSiteKey' => 'required|string',
                'recaptchaSecretKey' => 'required|string',
            ]);

            $user = auth()->user();
            $workspaceId = null;

            if ($user->type === 'company') {
                $workspaceId = $user->current_workspace_id;
            }

            foreach ($validated as $key => $value) {
                $convertedValue = is_bool($value) ? ($value ? '1' : '0') : $value;
                updateSetting($key, $convertedValue, $user->id, $workspaceId, isSaasMode() ? false: true);
            }

            return redirect()->back()->with('success', __('ReCaptcha settings updated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update ReCaptcha settings: :error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Update the chatgpt settings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateChatgpt(Request $request)
    {
        try {
            $validated = $request->validate([
                'chatgptKey' => 'required|string',
                'chatgptModel' => 'required|string',
            ]);

            $user = auth()->user();
            $workspaceId = null;

            if ($user->type === 'company') {
                $workspaceId = $user->current_workspace_id;
            }

            foreach ($validated as $key => $value) {
                updateSetting($key, $value, $user->id, $workspaceId);
            }

            return redirect()->back()->with('success', __('Chat GPT settings updated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update Chat GPT settings: :error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Update the storage settings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateStorage(Request $request)
    {
        try {
            $validated = $request->validate([
                'storage_type' => 'required|in:local,aws_s3,wasabi',
                'allowedFileTypes' => 'required|string',
                'maxUploadSize' => 'required|numeric|min:1',
                'awsAccessKeyId' => 'required_if:storage_type,aws_s3|string',
                'awsSecretAccessKey' => 'required_if:storage_type,aws_s3|string',
                'awsDefaultRegion' => 'required_if:storage_type,aws_s3|string',
                'awsBucket' => 'required_if:storage_type,aws_s3|string',
                'awsUrl' => 'required_if:storage_type,aws_s3|string',
                'awsEndpoint' => 'required_if:storage_type,aws_s3|string',
                'wasabiAccessKey' => 'required_if:storage_type,wasabi|string',
                'wasabiSecretKey' => 'required_if:storage_type,wasabi|string',
                'wasabiRegion' => 'required_if:storage_type,wasabi|string',
                'wasabiBucket' => 'required_if:storage_type,wasabi|string',
                'wasabiUrl' => 'required_if:storage_type,wasabi|string',
                'wasabiRoot' => 'required_if:storage_type,wasabi|string',
            ]);

            $settings = [
                'storage_type' => $validated['storage_type'],
                'storage_file_types' => $validated['allowedFileTypes'],
                'storage_max_upload_size' => $validated['maxUploadSize'],
            ];

            if ($validated['storage_type'] === 'aws_s3') {
                $settings['aws_access_key_id'] = $validated['awsAccessKeyId'];
                $settings['aws_secret_access_key'] = $validated['awsSecretAccessKey'];
                $settings['aws_default_region'] = $validated['awsDefaultRegion'];
                $settings['aws_bucket'] = $validated['awsBucket'];
                $settings['aws_url'] = $validated['awsUrl'];
                $settings['aws_endpoint'] = $validated['awsEndpoint'];
            }

            if ($validated['storage_type'] === 'wasabi') {
                $settings['wasabi_access_key'] = $validated['wasabiAccessKey'];
                $settings['wasabi_secret_key'] = $validated['wasabiSecretKey'];
                $settings['wasabi_region'] = $validated['wasabiRegion'];
                $settings['wasabi_bucket'] = $validated['wasabiBucket'];
                $settings['wasabi_url'] = $validated['wasabiUrl'];
                $settings['wasabi_root'] = $validated['wasabiRoot'];
            }

            $user = auth()->user();
            $workspaceId = null;

            if ($user->type === 'company') {
                $workspaceId = $user->current_workspace_id;
            }

            foreach ($settings as $key => $value) {
                updateSetting($key, $value, $user->id, $workspaceId);
            }

            StorageConfigService::clearCache();

            return redirect()->back()->with('success', __('Storage settings updated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update storage settings: :error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Update the cookie settings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateCookie(Request $request)
    {
        try {
            $validated = $request->validate([
                'enableLogging' => 'required|boolean',
                'strictlyNecessaryCookies' => 'required|boolean',
                'cookieTitle' => 'required|string|max:255',
                'strictlyCookieTitle' => 'required|string|max:255',
                'cookieDescription' => 'required|string',
                'strictlyCookieDescription' => 'required|string',
                'contactUsDescription' => 'required|string',
                'contactUsUrl' => 'required|url',
            ]);

            $user = auth()->user();
            $workspaceId = null;

            if ($user->type === 'company') {
                $workspaceId = $user->current_workspace_id;
            }

            foreach ($validated as $key => $value) {
                updateSetting($key, is_bool($value) ? ($value ? '1' : '0') : $value, $user->id, $workspaceId, isSaasMode() ? false: true);
            }

            return redirect()->back()->with('success', __('Cookie settings updated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update cookie settings: :error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Store cookie consent data as CSV.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeCookieConsent(Request $request)
    {
        try {
            $validated = $request->validate([
                'ip' => 'required|string',
                'date' => 'required|string',
                'time' => 'required|string',
                'acceptedCookies' => 'required|string',
                'deviceType' => 'required|string',
                'browserLanguage' => 'required|string',
                'browserName' => 'required|string',
                'osName' => 'required|string',
                'country' => 'required|string',
                'region' => 'required|string',
                'regionName' => 'required|string',
                'city' => 'required|string',
                'zipcode' => 'required|string',
                'lat' => 'required|string',
                'lon' => 'required|string',
            ]);

            $csvData = [
                $validated['ip'],
                $validated['date'],
                $validated['time'],
                $validated['acceptedCookies'],
                $validated['deviceType'],
                $validated['browserLanguage'],
                $validated['browserName'],
                $validated['osName'],
                $validated['country'],
                $validated['region'],
                $validated['regionName'],
                $validated['city'],
                $validated['zipcode'],
                $validated['lat'],
                $validated['lon']
            ];

            $filename = 'cookie-consents-' . date('Y-m-d') . '.csv';
            $filepath = storage_path('app/' . $filename);

            $fileExists = file_exists($filepath);
            $file = fopen($filepath, 'a');

            if (!$fileExists) {
                fputcsv($file, ['IP', 'Date', 'Time', 'Accepted-cookies', 'Device type', 'Browser language', 'Browser name', 'OS Name', 'Country', 'Region', 'RegionName', 'City', 'Zipcode', 'Lat', 'Lon']);
            }

            fputcsv($file, $csvData);
            fclose($file);

            return response()->json(['success' => true, 'message' => 'Cookie consent stored successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to store cookie consent: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update the SEO settings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateSeo(Request $request)
    {
        try {
            $user = auth()->user();
            $workspaceId = null;

            if ($user->type === 'company') {
                $workspaceId = $user->current_workspace_id;
            }

            // Check if image already exists
            $existingImage = getSetting('metaImage', '', $user->id, $workspaceId);

            $validated = $request->validate([
                'metaKeywords' => 'required|string|max:255',
                'metaDescription' => 'required|string|max:160',
                'metaImage' => 'nullable',
            ]);

            updateSetting('metaKeywords', $validated['metaKeywords'], $user->id, $workspaceId, isSaasMode() ? false: true);
            updateSetting('metaDescription', $validated['metaDescription'], $user->id, $workspaceId, isSaasMode() ? false: true);

            if ($request->hasFile('metaImage')) {
                if ($existingImage && $existingImage !== 'seo/seo-banner.jpg') {
                    delete_file($existingImage);
                }

                $fileName = 'meta_image_' . time() . '.' . $request->file('metaImage')->getClientOriginalExtension();
                $uploadResult = upload_file($request, 'metaImage', $fileName, 'seo');

                if (!$uploadResult['status']) {
                    return redirect()->back()->with('error', $uploadResult['msg']);
                }

                updateSetting('metaImage', $uploadResult['url'], $user->id, $workspaceId, isSaasMode() ? false: true);
            }

            return redirect()->back()->with('success', __('SEO settings updated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update SEO settings: :error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Clear application cache.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function clearCache()
    {
        try {
            \Artisan::call('cache:clear');
            \Artisan::call('route:clear');
            \Artisan::call('view:clear');
            \Artisan::call('optimize:clear');

            return redirect()->back()->with('success', __('Cache cleared successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to clear cache: :error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Get email notification settings.
     */
    public function getEmailNotifications()
    {
        $userId = auth()->id();
        $workspaceId = auth()->user()->type === 'company' ? auth()->user()->current_workspace_id : null;
        $templates = \App\Models\EmailTemplate::select('id', 'name')->get();
        $settings = [];

        foreach ($templates as $template) {
            $userTemplate = \App\Models\UserEmailTemplate::where('user_id', $userId)
                ->where('template_id', $template->id)
                ->where('workspace_id', $workspaceId)
                ->first();

            $settings[$template->name] = $userTemplate ? $userTemplate->is_active : false;
        }

        return response()->json($settings);
    }

    /**
     * Get available email notifications.
     */
    public function getAvailableEmailNotifications()
    {
        $templates = \App\Models\EmailTemplate::select('id', 'name')->get();
        $notifications = [];

        foreach ($templates as $template) {
            $notifications[] = [
                'name' => $template->name,
                'label' => str_replace(' ', ' ', $template->name)
            ];
        }

        return response()->json($notifications);
    }

    /**
     * Update email notification settings.
     */
    public function updateEmailNotifications(Request $request)
    {
        try {
            $userId = auth()->id();
            $workspaceId = auth()->user()->type === 'company' ? auth()->user()->current_workspace_id : null;
            $availableTemplates = \App\Models\EmailTemplate::pluck('name', 'id')->toArray();

            $rules = [];
            foreach ($availableTemplates as $templateId => $templateName) {
                $rules[$templateName] = 'boolean';
            }

            $validated = $request->validate($rules);

            foreach ($availableTemplates as $templateId => $templateName) {
                if (isset($validated[$templateName])) {
                    \App\Models\UserEmailTemplate::updateOrCreate(
                        ['user_id' => $userId, 'template_id' => $templateId, 'workspace_id' => $workspaceId],
                        ['is_active' => $validated[$templateName]]
                    );
                }
            }

            return redirect()->back()->with('success', __('Email notification settings updated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update email notification settings: :error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Get available Slack notifications.
     */
    public function getAvailableSlackNotifications()
    {
        $templates = \App\Models\NotificationTemplate::where('type', 'slack')
            ->select(['id', 'name'])->get();
        $notifications = [];

        foreach ($templates as $template) {
            $notifications[] = [
                'name' => $template->name,
                'label' => $template->name
            ];
        }

        return response()->json($notifications);
    }

    /**
     * Get Slack configuration.
     */
    public function getSlackConfig()
    {
        $userId = createdBy();
        return response()->json([
            'slack_enabled' => getSetting('slack_enabled', '0', $userId) === '1',
            'slack_webhook_url' => getSetting('slack_webhook_url', '', $userId)
        ]);
    }

    /**
     * Update Slack notification settings.
     */
    public function updateSlackNotifications(Request $request)
    {
        try {
            $userId = createdBy();
            $workspaceId = auth()->user()->type === 'company' ? auth()->user()->current_workspace_id : null;
            $availableTemplates = \App\Models\NotificationTemplate::where('type', 'slack')
                ->pluck('name', 'id')->toArray();

            $rules = [
                'slack_enabled' => 'boolean',
                'slack_webhook_url' => 'nullable|url'
            ];

            foreach ($availableTemplates as $templateId => $templateName) {
                $rules[$templateName] = 'boolean';
            }

            $validated = $request->validate($rules);

            // Update Slack configuration
            updateSetting('slack_enabled', $validated['slack_enabled'] ? '1' : '0', $userId);
            updateSetting('slack_webhook_url', $validated['slack_webhook_url'] ?? '', $userId);

            // Update notification settings
            foreach ($availableTemplates as $templateId => $templateName) {
                if (isset($validated[$templateName])) {
                    \App\Models\UserNotificationTemplate::updateOrCreate(
                        [
                            'user_id' => $userId,
                            'template_id' => $templateId,
                            'type' => 'slack'
                        ],
                        [
                            'workspace_id' => $workspaceId,
                            'is_active' => $validated[$templateName]
                        ]
                    );
                }
            }

            return redirect()->back()->with('success', __('Slack settings updated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update Slack settings: :error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Get Slack notifications.
     */
    public function getSlackNotifications()
    {
        $userId = createdBy();
        $workspaceId = auth()->user()->type === 'company' ? auth()->user()->current_workspace_id : null;
        $templates = \App\Models\NotificationTemplate::where('type', 'slack')
            ->select(['id', 'name'])->get();
        $settings = [];

        foreach ($templates as $template) {
            $userTemplate = \App\Models\UserNotificationTemplate::where('user_id', $userId)
                ->where('template_id', $template->id)
                ->where('type', 'slack')
                ->where('workspace_id', $workspaceId)
                ->first();

            $settings[$template->name] = $userTemplate ? $userTemplate->is_active : false;
        }

        return response()->json($settings);
    }

    /**
     * Get available Telegram notifications.
     */
    public function getAvailableTelegramNotifications()
    {
        $templates = \App\Models\NotificationTemplate::where('type', 'telegram')
            ->select(['id', 'name'])->get();
        $notifications = [];

        foreach ($templates as $template) {
            $notifications[] = [
                'name' => $template->name,
                'label' => $template->name
            ];
        }

        return response()->json($notifications);
    }

    /**
     * Get Telegram notifications.
     */
    public function getTelegramNotifications()
    {
        $userId = createdBy();
        $workspaceId = auth()->user()->type === 'company' ? auth()->user()->current_workspace_id : null;
        $templates = \App\Models\NotificationTemplate::where('type', 'telegram')
            ->select(['id', 'name'])->get();
        $settings = [];

        foreach ($templates as $template) {
            $userTemplate = \App\Models\UserNotificationTemplate::where('user_id', $userId)
                ->where('template_id', $template->id)
                ->where('type', 'telegram')
                ->where('workspace_id', $workspaceId)
                ->first();

            $settings[$template->name] = $userTemplate ? $userTemplate->is_active : false;
        }

        return response()->json($settings);
    }

    /**
     * Get Telegram configuration.
     */
    public function getTelegramConfig()
    {
        $userId = createdBy();
        return response()->json([
            'telegram_enabled' => getSetting('telegram_enabled', '0', $userId) === '1',
            'telegram_bot_token' => getSetting('telegram_bot_token', '', $userId),
            'telegram_chat_id' => getSetting('telegram_chat_id', '', $userId)
        ]);
    }

    /**
     * Update Telegram notification settings.
     */
    public function updateTelegramNotifications(Request $request)
    {
        try {
            $userId = createdBy();
            $workspaceId = auth()->user()->type === 'company' ? auth()->user()->current_workspace_id : null;
            $availableTemplates = \App\Models\NotificationTemplate::where('type', 'telegram')
                ->pluck('name', 'id')->toArray();

            $rules = [
                'telegram_enabled' => 'boolean',
                'telegram_bot_token' => 'nullable|string',
                'telegram_chat_id' => 'nullable|string'
            ];

            foreach ($availableTemplates as $templateId => $templateName) {
                $rules[$templateName] = 'boolean';
            }

            $validated = $request->validate($rules);

            // Update Telegram configuration
            updateSetting('telegram_enabled', $validated['telegram_enabled'] ? '1' : '0', $userId);
            updateSetting('telegram_bot_token', $validated['telegram_bot_token'] ?? '', $userId);
            updateSetting('telegram_chat_id', $validated['telegram_chat_id'] ?? '', $userId);

            // Update notification settings
            foreach ($availableTemplates as $templateId => $templateName) {
                if (isset($validated[$templateName])) {
                    \App\Models\UserNotificationTemplate::updateOrCreate(
                        [
                            'user_id' => $userId,
                            'template_id' => $templateId,
                            'type' => 'telegram'
                        ],
                        [
                            'workspace_id' => $workspaceId,
                            'is_active' => $validated[$templateName]
                        ]
                    );
                }
            }

            return redirect()->back()->with('success', __('Telegram settings updated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update Telegram settings: :error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Test Slack webhook.
     */
    public function testSlackWebhook(Request $request)
    {
        $request->validate([
            'webhook_url' => 'required|url',
        ]);

        $message = [
            'text' => 'Test message from Taskly SaaS',
            'username' => 'Taskly Bot',
            'icon_emoji' => ':robot_face:',
        ];

        try {
            $response = Http::post($request->webhook_url, $message);

            if ($response->successful()) {
                return back();
            }

            return back()->withErrors(['webhook' => 'Failed to send test message']);
        } catch (\Exception $e) {
            return back()->withErrors(['webhook' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Test Telegram bot.
     */
    public function testTelegramBot(Request $request)
    {
        $request->validate([
            'bot_token' => 'required|string',
            'chat_id' => 'required|string'
        ]);

        try {
            $botToken = $request->bot_token;
            $chatId = $request->chat_id;

            // Send test message to Telegram
            $message = 'Test message from Taskly - Telegram integration is working!';
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

            $data = [
                'chat_id' => $chatId,
                'text' => $message
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $response = json_decode($result, true);
                if (isset($response['ok']) && $response['ok']) {
                    return redirect()->back()->with('success', __('Test message sent successfully to Telegram!'));
                }
            }

            return redirect()->back()->with('error', __('Failed to send test message. Please check your bot token and chat ID.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to send test message: :message', ['message' => $e->getMessage()]));
        }
    }
}
