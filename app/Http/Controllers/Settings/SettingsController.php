<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Traits\HasPermissionChecks;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Currency;
use App\Models\PaymentSetting;
use App\Models\Webhook;
use App\Models\Workspace;
use App\Models\Tax;
use App\Services\GoogleCalendarService;
use App\Models\Task;
use App\Models\Role;


class SettingsController extends Controller
{
    use HasPermissionChecks;
    /**
     * Display the main settings page.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $this->authorizePermission('settings_view');
        
        $user = auth()->user();
        $workspaceId = null;
        
        // For company users, get the current workspace
        if ($user->type === 'company') {
            $workspaceId = $user->current_workspace_id;
        }
        
        // Get system settings using helper function
        $systemSettings = settings($user->id, $workspaceId);
        
        // Add Google Meet settings
        $systemSettings['google_meet_json_file'] = getSetting('google_meet_json_file', '', $user->id, $workspaceId);
        $systemSettings['google_meet_token'] = getSetting('google_meet_token', '', $user->id, $workspaceId);
        $systemSettings['google_meet_refresh_token'] = getSetting('google_meet_refresh_token', '', $user->id, $workspaceId);
        $systemSettings['is_google_meeting_test'] = getSetting('is_google_meeting_test', '0', $user->id, $workspaceId);
        
        // Add Zoom meeting test status
        $systemSettings['is_zoom_meeting_test'] = getSetting('is_zoom_meeting_test', '0', $user->id, $workspaceId);
        
        // Ensure Google Calendar sync status is included
        $systemSettings['is_googlecalendar_sync'] = getSetting('is_googlecalendar_sync', '0', $user->id, $workspaceId);
        
        // Get ReCaptcha settings separately (always without workspace for company users in non-SaaS mode)
        if ($user->type === 'company' && !isSaasMode()) {
            $recaptchaSettings = Setting::where('user_id', $user->id)
                ->whereIn('key', [
                    'recaptchaEnabled',
                    'recaptchaVersion',
                    'recaptchaSiteKey',
                    'recaptchaSecretKey',
                    'enableLogging',
                    'strictlyNecessaryCookies',
                    'cookieTitle',
                    'strictlyCookieTitle',
                    'cookieDescription',
                    'strictlyCookieDescription',
                    'contactUsDescription',
                    'contactUsUrl',
                    'metaKeywords',
                    'metaDescription',
                    'metaImage',
                ])
                ->pluck('value', 'key')->toArray();
            $systemSettings = array_merge($systemSettings, $recaptchaSettings);
        }

        $currencies = Currency::all();
        $paymentSettings = PaymentSetting::getUserSettings($user->id, $workspaceId);
        $slackSettings = [
            'slack_enabled' => getSetting('slack_enabled', false, $user->id),
            'slack_webhook_url' => getSetting('slack_webhook_url', '', $user->id),
        ];
        $telegramSettings = [
            'telegram_enabled' => getSetting('telegram_enabled', false, $user->id),
            'telegram_bot_token' => getSetting('telegram_bot_token', '', $user->id),
            'telegram_chat_id' => getSetting('telegram_chat_id', '', $user->id),
        ];
        
        // Mask sensitive data for display in demo mode
        if (config('app.is_demo', false)) {
            $paymentSettings = $this->maskSensitiveDataForDemo($paymentSettings);
        }
        $webhooks = Webhook::where('user_id', $user->id)
            ->where('workspace_id', $workspaceId)
            ->get();
            
        // Get taxes for current workspace
        $taxes = [];
        if ($workspaceId) {
            $taxes = Tax::where('workspace_id', $workspaceId)
                ->orderBy('name')
                ->get();
        }
            
        // Get current workspace for company users
        $currentWorkspace = null;
        if ($user->type === 'company' && $workspaceId) {
            $currentWorkspace = Workspace::find($workspaceId);
        }
        
        $invoiceSettings = Setting::where('user_id', $user->id)
            ->where('workspace_id', $workspaceId)
            ->whereIn('key', ['invoice_template', 'invoice_qr_display', 'invoice_color', 'invoice_footer_title', 'invoice_footer_notes', 'invoice_logo'])
            ->pluck('value', 'key')->toArray();

        // Read-only role/permission matrix for the Settings → Roles tab.
        // Custom role management (create/clone/edit) is Phase 5 — deliberately not exposed here yet.
        // Only superadmin/company reach this controller (settings_view), so no extra scoping is needed —
        // Role::withPermissionCheck() is skipped because it assumes a `created_by` column the roles table doesn't have.
        // Super Admin is a platform role - only superadmins see it. Workspace owners see
        // every workspace role; everyone else only sees their own.
        $isSuperAdmin = $user->isSuperAdmin();
        $workspaceRole = $user->getCurrentWorkspaceRole();
        $currentRoleName = $isSuperAdmin ? 'superadmin' : ($workspaceRole === 'owner' ? 'company' : $workspaceRole);

        $roles = Role::with('permissions:id,name,module')
            ->when(!$isSuperAdmin, fn ($q) => $q->where('name', '!=', 'superadmin'))
            ->when(!$isSuperAdmin && $workspaceRole !== 'owner', fn ($q) => $q->where('name', $currentRoleName))
            ->get(['id', 'name', 'label'])
            ->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->label ?? $role->name,
                'permission_count' => $role->permissions->count(),
                'modules' => $role->permissions->pluck('module')->unique()->values(),
                'is_current' => $role->name === $currentRoleName,
            ])
            ->sortByDesc('is_current')
            ->values();

        return Inertia::render('settings/index', [
            'systemSettings' => $systemSettings,
            'settings' => $systemSettings, // For helper functions
            'cacheSize' => getCacheSize(),
            'currencies' => $currencies,
            'timezones' => config('timezones'),
            'dateFormats' => config('dateformat'),
            'timeFormats' => config('timeformat'),
            'paymentSettings' => $paymentSettings,
            'slackSettings' => $slackSettings,
            'telegramSettings' => $telegramSettings,
            'webhooks' => $webhooks,
            'taxes' => $taxes,
            'invoiceSettings' => $invoiceSettings,
            'currentWorkspace' => $currentWorkspace,
            'isDemoMode' => config('app.is_demo', false),
            'isSaasMode' => isSaasMode(),
            'isSuperAdmin' => $user->type === 'superadmin' || $user->type === 'super admin',
            'appUrl' => config('app.url'),
            'roles' => $roles,
        ]);
    }
    
    /**
     * Mask sensitive payment data for demo mode display
     */
    private function maskSensitiveDataForDemo(array $settings): array
    {
        $sensitiveKeys = [
            'stripe_key',
            'stripe_secret',
            'paypal_client_id',
            'paypal_secret_key',
            'razorpay_key',
            'razorpay_secret',
            'mercadopago_access_token',
            'paystack_public_key',
            'paystack_secret_key',
            'flutterwave_public_key',
            'flutterwave_secret_key',
            'paytabs_profile_id',
            'paytabs_server_key',
            'skrill_merchant_id',
            'skrill_secret_word',
            'coingate_api_token',
            'payfast_merchant_id',
            'payfast_merchant_key',
            'payfast_passphrase',
            'tap_secret_key',
            'xendit_api_key',
            'paytr_merchant_key',
            'paytr_merchant_salt',
            'mollie_api_key',
            'toyyibpay_secret_key',
            'benefit_secret_key',
            'benefit_public_key',
            'iyzipay_secret_key',
            'iyzipay_public_key',
            'aamarpay_signature',
            'midtrans_secret_key',
            'yookassa_secret_key',
            'nepalste_secret_key',
            'nepalste_public_key',
            'cinetpay_api_key',
            'cinetpay_secret_key',
            'payhere_merchant_secret',
            'payhere_app_secret',
            'fedapay_secret_key',
            'fedapay_public_key',
            'authorizenet_transaction_key',
            'khalti_secret_key',
            'khalti_public_key',
            'easebuzz_merchant_key',
            'easebuzz_salt_key',
            'ozow_private_key',
            'ozow_api_key',
            'cashfree_secret_key',
            'cashfree_public_key'
        ];
        
        foreach ($sensitiveKeys as $key) {
            if (isset($settings[$key]) && !empty($settings[$key])) {
                $settings[$key] = str_repeat('*', strlen($settings[$key]));
            }
        }
        
        if (isset($settings['bank_detail']) && !empty($settings['bank_detail'])) {
            $settings['bank_detail'] = 'Bank: ****\nAccount: ****\nRouting: ****';
        }
        
        return $settings;
    }

    public function storeInvoiceSettings()
    {
        $this->authorizePermission('settings_invoice');
        
        $user = auth()->user();
        $workspaceId = null;
        
        if ($user->type === 'company') {
            $workspaceId = $user->current_workspace_id;
        }
        
        $validated = request()->validate([
            'invoice_template' => 'required|string|in:new_york,toronto,rio,london,istanbul,mumbai,hong_kong,tokyo,sydney,paris',
            'invoice_qr_display' => 'boolean',
            'invoice_color' => 'required|string|regex:/^#[0-9A-F]{6}$/i',
            'invoice_footer_title' => 'nullable|string|max:255',
            'invoice_footer_notes' => 'nullable|string',
            'invoice_logo' => 'nullable|string',
        ]);
        
        foreach ($validated as $key => $value) {
            Setting::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'workspace_id' => $workspaceId,
                    'key' => $key,
                ],
                ['value' => is_bool($value) ? ($value ? 'true' : 'false') : $value]
            );
        }
        
        return back()->with('success', 'Invoice settings updated successfully');
    }

    /**
     * Update Slack settings
     */
    public function updateSlackSettings()
    {
        $user = auth()->user();
        $workspaceId = $user->type === 'company' ? $user->current_workspace_id : null;
        
        $validated = request()->validate([
            'slack_enabled' => 'boolean',
            'slack_webhook_url' => 'nullable|url',
        ]);
        
        updateSetting('slack_enabled', $validated['slack_enabled'] ? '1' : '0', $user->id, $workspaceId);
        updateSetting('slack_webhook_url', $validated['slack_webhook_url'] ?? '', $user->id, $workspaceId);
        
        return back()->with('success', 'Slack settings updated successfully');
    }
    
    /**
     * Update Telegram settings
     */
    public function updateTelegramSettings()
    {
        $user = auth()->user();
        $workspaceId = $user->type === 'company' ? $user->current_workspace_id : null;
        
        $validated = request()->validate([
            'telegram_enabled' => 'boolean',
            'telegram_bot_token' => 'nullable|string',
            'telegram_chat_id' => 'nullable|string',
        ]);
        
        updateSetting('telegram_enabled', $validated['telegram_enabled'] ? '1' : '0', $user->id, $workspaceId);
        updateSetting('telegram_bot_token', $validated['telegram_bot_token'] ?? '', $user->id, $workspaceId);
        updateSetting('telegram_chat_id', $validated['telegram_chat_id'] ?? '', $user->id, $workspaceId);
        
        return back()->with('success', 'Telegram settings updated successfully');
    }

    /**
     * Update Google Calendar settings
     */
    public function updateGoogleCalendar()
    {
        $this->authorizePermission('settings_google_calendar');
        
        $user = auth()->user();
        $workspaceId = $user->type === 'company' ? $user->current_workspace_id : null;
        
        $validated = request()->validate([
            'googleCalendarEnabled' => 'boolean',
            'googleCalendarId' => 'nullable|string|max:255',
            'googleCalendarJson' => 'nullable|file|mimes:json|max:2048',
        ]);
        
        // Check if Google Calendar is being disabled
        $currentlyEnabled = getSetting('googleCalendarEnabled', '0', $user->id, $workspaceId) === '1';
        $newEnabled = $validated['googleCalendarEnabled'] ?? false;
        
        $settings = [
            'googleCalendarEnabled' => $newEnabled,
            'googleCalendarId' => $validated['googleCalendarId'] ?? '',
        ];
        
        // Check if credentials are being changed
        $credentialsChanged = false;
        if (isset($settings['googleCalendarId']) && $settings['googleCalendarId'] !== getSetting('googleCalendarId', '', $user->id, $workspaceId)) {
            $credentialsChanged = true;
        }
        
        // Handle JSON file upload
        if (request()->hasFile('googleCalendarJson')) {
            $credentialsChanged = true;
            
            // Delete existing JSON file if it exists
            $existingPath = getSetting('googleCalendarJsonPath', null, $user->id, $workspaceId);
            if ($existingPath && \Storage::disk('public')->exists($existingPath)) {
                \Storage::disk('public')->delete($existingPath);
            }
            
            $file = request()->file('googleCalendarJson');
            $fileName = 'google-calendar-' . $user->id . '-' . time() . '.json';
            $path = $file->storeAs('google-calendar', $fileName, 'public');
            $settings['googleCalendarJsonPath'] = $path;
        }
        
        // If Google Calendar is being disabled, delete all synced events
        if ($currentlyEnabled && !$newEnabled) {
            $this->deleteAllGoogleCalendarEvents($user->id, $workspaceId);
        }
        
        // Reset sync test status when credentials change
        if ($credentialsChanged) {
            $settings['is_googlecalendar_sync'] = '0';
        }
        
        foreach ($settings as $key => $value) {
            updateSetting($key, is_bool($value) ? ($value ? '1' : '0') : $value, $user->id, $workspaceId);
        }
        
        $message = $newEnabled 
            ? 'Google Calendar integration enabled successfully' 
            : 'Google Calendar integration disabled. All synced events have been removed from Google Calendar.';
        
        return back()->with('success', $message);
    }
    
    /**
     * Sync Google Calendar to test credentials
     */
    public function syncGoogleCalendar()
    {
        $this->authorizePermission('settings_google_calendar');
        
        try {
            $user = auth()->user();
            $workspaceId = $user->type === 'company' ? $user->current_workspace_id : null;
            $userId = $user->id;
            
            $googleCalendarEnabled = getSetting('googleCalendarEnabled', '0', $userId, $workspaceId);
            $googleCalendarId = getSetting('googleCalendarId', '', $userId, $workspaceId);
            $googleCalendarJsonPath = getSetting('googleCalendarJsonPath', '', $userId, $workspaceId);
            
            if ($googleCalendarEnabled !== '1') {
                return back()->withErrors(['error' => 'Google Calendar integration is not enabled.']);
            }
            
            if (empty($googleCalendarId) || trim($googleCalendarId) === '') {
                $googleCalendarId = 'primary';
            }
            
            if (empty($googleCalendarJsonPath) || trim($googleCalendarJsonPath) === '') {
                return back()->withErrors(['error' => 'Google Calendar service account JSON is not uploaded.']);
            }
            
            // Get the JSON file path
            $jsonPath = storage_path('app/public/' . $googleCalendarJsonPath);
            
            if (!file_exists($jsonPath)) {
                throw new \Exception('Service account JSON file not found at: ' . $jsonPath);
            }
            
            // Validate JSON file
            $jsonContent = file_get_contents($jsonPath);
            $credentials = json_decode($jsonContent, true);
            
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON file format: ' . json_last_error_msg());
            }
            
            if (!isset($credentials['type']) || $credentials['type'] !== 'service_account') {
                throw new \Exception('Invalid service account credentials. Expected type: service_account, got: ' . ($credentials['type'] ?? 'none'));
            }
            
            // Check if Google Client library is installed
            if (!class_exists('\Google_Client')) {
                throw new \Exception('Google Client library is not installed. Please run: composer require google/apiclient');
            }
            
            // Test Google Calendar API connection
            $client = new \Google_Client();
            $client->setAuthConfig($jsonPath);
            $client->addScope(\Google_Service_Calendar::CALENDAR_READONLY);
            
            $service = new \Google_Service_Calendar($client);
            
            // Test by fetching calendar info
            try {
                $calendar = $service->calendars->get($googleCalendarId);
                
                if (!$calendar) {
                    throw new \Exception('Unable to access the specified calendar.');
                }
                
                // Store sync test success status
                $settingResult = updateSetting('is_googlecalendar_sync', '1', $userId, $workspaceId);
                
                // Debug log to confirm the setting was stored
                \Log::info('Google Calendar sync test successful', [
                    'user_id' => $userId,
                    'workspace_id' => $workspaceId,
                    'calendar_summary' => $calendar->getSummary(),
                    'setting_stored' => $settingResult ? 'yes' : 'no'
                ]);
            } catch (\Google_Service_Exception $calendarException) {
                // Handle specific calendar access errors
                $errorCode = $calendarException->getCode();
                if ($errorCode === 404) {
                    throw new \Exception('Calendar not found. Please check your Google Calendar ID.');
                } elseif ($errorCode === 403) {
                    throw new \Exception('Access denied. Please ensure the service account has access to this calendar.');
                } else {
                    throw new \Exception('Calendar access error: ' . $calendarException->getMessage());
                }
            }
            
            return back()->with('success', 'Google Calendar sync test completed successfully. Connected to: ' . $calendar->getSummary());
        } catch (\Google_Service_Exception $e) {
            // Clear sync test status on failure
            updateSetting('is_googlecalendar_sync', '0', $userId, $workspaceId);
            
            $errorCode = $e->getCode();
            $errorMessage = 'Google API Error: ' . $e->getMessage();
            
            // Provide more specific error messages based on error codes
            if ($errorCode === 404) {
                $errorMessage = 'Calendar not found. Please verify your Google Calendar ID is correct.';
            } elseif ($errorCode === 403) {
                $errorMessage = 'Access denied. Please ensure the service account has proper permissions for this calendar.';
            } elseif ($errorCode === 401) {
                $errorMessage = 'Authentication failed. Please check your service account credentials.';
            }
            
            \Log::error('Google Calendar API error', [
                'error' => $errorMessage,
                'error_code' => $errorCode,
                'calendar_id' => $googleCalendarId ?? 'not_set'
            ]);
            
            return back()->withErrors(['error' => 'Google Calendar sync failed: ' . $errorMessage]);
        } catch (\Exception $e) {
            // Clear sync test status on failure
            updateSetting('is_googlecalendar_sync', '0', $userId, $workspaceId);
            
            \Log::error('Google Calendar sync failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            
            return back()->withErrors(['error' => 'Google Calendar sync failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete all Google Calendar events for tasks when integration is disabled
     */
    private function deleteAllGoogleCalendarEvents($userId, $workspaceId = null)
    {
        try {
            $googleCalendarService = app(GoogleCalendarService::class);
            
            // Get all tasks that have Google Calendar event IDs
            $tasksQuery = Task::whereNotNull('google_calendar_event_id')
                ->whereHas('project', function ($q) use ($workspaceId) {
                    if ($workspaceId) {
                        $q->where('workspace_id', $workspaceId);
                    }
                });
            
            $tasks = $tasksQuery->get();
            
            $deletedCount = 0;
            $failedCount = 0;
            
            foreach ($tasks as $task) {
                try {
                    // Delete the event from Google Calendar
                    $deleted = $googleCalendarService->deleteEvent(
                        $task->google_calendar_event_id, 
                        $userId, 
                        $workspaceId
                    );
                    
                    if ($deleted) {
                        // Clear the Google Calendar event ID from the task
                        $task->update(['google_calendar_event_id' => null]);
                        $deletedCount++;
                    } else {
                        $failedCount++;
                    }
                } catch (\Exception $e) {
                    \Log::warning('Failed to delete Google Calendar event for task', [
                        'task_id' => $task->id,
                        'event_id' => $task->google_calendar_event_id,
                        'error' => $e->getMessage()
                    ]);
                    
                    // Still clear the event ID from the task even if deletion failed
                    $task->update(['google_calendar_event_id' => null]);
                    $failedCount++;
                }
            }
            
            \Log::info('Google Calendar events cleanup completed', [
                'user_id' => $userId,
                'workspace_id' => $workspaceId,
                'deleted_count' => $deletedCount,
                'failed_count' => $failedCount,
                'total_tasks' => $tasks->count()
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Failed to delete Google Calendar events', [
                'user_id' => $userId,
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage()
            ]);
        }
    }

}
