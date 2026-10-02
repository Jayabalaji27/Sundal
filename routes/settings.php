<?php

use App\Http\Controllers\Settings\ZoomSettingsController;
use App\Http\Controllers\Settings\GoogleMeetSettingsController;
use App\Http\Controllers\Settings\GoogleCalendarSettingsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\EmailSettingController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Settings\SystemSettingsController;
use App\Http\Controllers\Settings\CurrencySettingController;
use App\Http\Controllers\PlanOrderController;
use App\Http\Controllers\Settings\PaymentSettingController;
use App\Http\Controllers\Settings\WebhookController;
use App\Http\Controllers\StripePaymentController;
use App\Http\Controllers\PayPalPaymentController;
use App\Http\Controllers\BankPaymentController;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Settings Routes
|--------------------------------------------------------------------------
|
| Here are the routes for settings management
|
*/

// Payment routes accessible without plan check
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/payment-methods', [PaymentSettingController::class, 'getPaymentMethods'])->name('payment.methods');
    Route::get('/enabled-payment-methods', [PaymentSettingController::class, 'getEnabledMethods'])->name('payment.enabled-methods');
    Route::post('/plan-orders', [PlanOrderController::class, 'create'])->name('plan-orders.create');
    Route::post('/stripe-payment', [StripePaymentController::class, 'processPayment'])->name('settings.stripe.payment');
    Route::post('/paypal-payment', [PayPalPaymentController::class, 'processPayment'])->name('settings.paypal.payment');
    Route::post('/bank-payment', [BankPaymentController::class, 'processPayment'])->name('settings.bank.payment');

});

Route::middleware(['auth', 'verified', 'plan.access'])->group(function () {
    // Payment Settings (admin only)
    Route::post('/payment-settings', [PaymentSettingController::class, 'store'])->middleware('permission:settings_payment')->name('payment.settings');
    
    // Profile settings page with profile and password sections.
    // Client role has no self-service profile page (see ProfileController) — block direct
    // navigation to these routes too, not just the nav link.
    Route::middleware('block.client.profile')->group(function () {
        Route::get('profile', function () {
            return Inertia::render('settings/profile-settings');
        })->name('profile');

        // Routes for form submissions
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('profile', [ProfileController::class, 'update']); // For file uploads with method spoofing
        Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
        Route::put('profile/password', [PasswordController::class, 'update'])->name('password.update');
    });
    
    // Language update route
    Route::post('user/language', function(\Illuminate\Http\Request $request) {
        $request->validate(['lang' => 'required|string|max:10']);
        auth()->user()->update(['lang' => $request->lang]);
        
        if ($request->expectsJson()) {
            return response()->json(['success' => true]);   
        }
        return back();
    })->name('user.update-language');

    // Email settings page
    Route::get('settings/email', function () {
        return Inertia::render('settings/components/email-settings');
    })->middleware('permission:settings_email')->name('settings.email');
    
    // Email settings routes
    Route::get('settings/email/get', [EmailSettingController::class, 'getEmailSettings'])->middleware('permission:settings_email')->name('settings.email.get');
    Route::post('settings/email/update', [EmailSettingController::class, 'updateEmailSettings'])->middleware('permission:settings_email')->name('settings.email.update');
    Route::post('settings/email/test', [EmailSettingController::class, 'sendTestEmail'])->middleware('permission:settings_email')->name('settings.email.test');
  
    // General settings page with system and company settings
    Route::get('settings', [SettingsController::class, 'index'])->middleware('permission:settings_view')->name('settings');
    
    // System Settings routes
    Route::post('settings/system', [SystemSettingsController::class, 'update'])->middleware('permission:settings_system')->name('settings.system.update');
    Route::post('settings/brand', [SystemSettingsController::class, 'updateBrand'])->middleware('permission:settings_brand')->name('settings.brand.update');
    // Personal display preferences (theme mode, RTL layout, sidebar style) — open to
    // every authenticated role, not gated behind settings_brand. See updateAppearance() docblock.
    Route::post('settings/appearance', [SystemSettingsController::class, 'updateAppearance'])->name('settings.appearance.update');
    Route::post('settings/storage', [SystemSettingsController::class, 'updateStorage'])->middleware('permission:settings_storage')->name('settings.storage.update');
    Route::post('settings/recaptcha', [SystemSettingsController::class, 'updateRecaptcha'])->middleware('permission:settings_recaptcha')->name('settings.recaptcha.update');
    Route::post('settings/chatgpt', [SystemSettingsController::class, 'updateChatgpt'])->middleware('permission:settings_chatgpt')->name('settings.chatgpt.update');
    Route::post('settings/cookie', [SystemSettingsController::class, 'updateCookie'])->middleware('permission:settings_cookie')->name('settings.cookie.update');
    Route::post('settings/cookie/consent', [SystemSettingsController::class, 'storeCookieConsent'])->name('settings.cookie.consent');
    Route::post('settings/seo', [SystemSettingsController::class, 'updateSeo'])->middleware('permission:settings_seo')->name('settings.seo.update');
    Route::post('settings/cache/clear', [SystemSettingsController::class, 'clearCache'])->middleware('permission:settings_cache')->name('settings.cache.clear');
    
    // Currency Settings routes
    Route::post('settings/currency', [CurrencySettingController::class, 'update'])->middleware('permission:settings_currency')->name('settings.currency.update');
    
    // Webhook Settings routes (commented out)
    // Route::get('settings/webhooks', [WebhookController::class, 'index'])->name('settings.webhooks.index');
    // Route::post('settings/webhooks', [WebhookController::class, 'store'])->name('settings.webhooks.store');
    // Route::put('settings/webhooks/{webhook}', [WebhookController::class, 'update'])->name('settings.webhooks.update');
    // Route::delete('settings/webhooks/{webhook}', [WebhookController::class, 'destroy'])->name('settings.webhooks.destroy');
    
    // Webhook Settings routes
    Route::get('settings/webhooks', [WebhookController::class, 'index'])->middleware(['permission:settings_webhook', 'block.superadmin.workspace'])->name('settings.webhooks.index');
    Route::post('settings/webhooks', [WebhookController::class, 'store'])->name('settings.webhooks.store');
    Route::put('settings/webhooks/{webhook}', [WebhookController::class, 'update'])->name('settings.webhooks.update');
    Route::delete('settings/webhooks/{webhook}', [WebhookController::class, 'destroy'])->name('settings.webhooks.destroy');
    
    // Email notification settings routes
    Route::get('settings/email-notifications', [SystemSettingsController::class, 'getEmailNotifications'])->name('settings.email-notifications.get');
    Route::get('settings/email-notifications/available', [SystemSettingsController::class, 'getAvailableEmailNotifications'])->name('settings.email-notifications.available');
    Route::post('settings/email-notifications', [SystemSettingsController::class, 'updateEmailNotifications'])->name('settings.email-notifications.update');
    
    // Zoom Settings routes
    Route::post('settings/zoom', [ZoomSettingsController::class, 'update'])->middleware('permission:settings_zoom')->name('settings.zoom.update');
    Route::post('settings/zoom/test', [ZoomSettingsController::class, 'test'])->middleware('permission:settings_zoom')->name('settings.zoom.test');

    // Google Meet Settings routes
    Route::post('settings/google-meet', [GoogleMeetSettingsController::class, 'update'])->middleware('permission:settings_google_meet')->name('settings.google-meet.update');
    Route::get('/auth/googlemeet', [GoogleMeetSettingsController::class, 'redirectToGoogle'])->name('settings.google-meet.redirectToGoogle');
    Route::get('/oauth', [GoogleMeetSettingsController::class, 'handleGoogleCallback'])->name('settings.google-meet.handleGoogleCallback');

    Route::post('settings/google-calendar', [GoogleCalendarSettingsController::class, 'update'])->middleware('permission:settings_google_calendar')->name('settings.google-calendar.update');
    Route::post('settings/google-calendar/sync', [GoogleCalendarSettingsController::class, 'sync'])->middleware('permission:settings_google_calendar')->name('settings.google-calendar.sync');

    // Invoice settings
    Route::post('settings/invoice', [SettingsController::class, 'storeInvoiceSettings'])->middleware('permission:settings_invoice')->name('settings.invoice.store');

});