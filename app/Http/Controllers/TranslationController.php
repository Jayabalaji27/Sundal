<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use App\Models\Setting;
use App\Models\User;


class TranslationController extends BaseController
{
    public function getTranslations($locale)
    {
        $path = resource_path("lang/{$locale}.json");

        if (!File::exists($path)) {
            $path = resource_path("lang/en.json");
            $locale = 'en';
        }

        // Determine if this is an RTL language
        $isRtlLanguage = in_array($locale, ['ar', 'he']);
        
        // Get current layout direction from settings
        $currentLayoutDirection = 'left'; // default
        
        // Check if demo mode
        $isDemo = config('app.is_demo') || (request()->cookie('is_demo') === 'true');
        
        if ($isDemo) {
            // Demo mode: use cookies for layout direction
            $currentLayoutDirection = request()->cookie('layoutDirection', 'left');
            
            // Auto-update layout direction when switching to/from Arabic in demo mode
            if ($isRtlLanguage && $currentLayoutDirection === 'left') {
                $currentLayoutDirection = 'right';
            } elseif (!$isRtlLanguage && $currentLayoutDirection === 'right') {
                // Only switch to LTR if no other RTL language is being used
                $currentLayoutDirection = 'left';
            }
        } else {
            // Normal mode: use database settings
            if (auth()->check()) {
                // auth()->user()->update(['lang' => $locale]);

                // $userSettings = settings();
                // $currentLayoutDirection = $userSettings['layoutDirection'] ?? 'left';
                
                
                // // Auto-update layout direction when switching to/from Arabic
                // if ($isRtlLanguage && $currentLayoutDirection === 'left') {
                //     // Switching to Arabic - update to RTL
                //     $currentLayoutDirection = 'right';
                // } elseif (!$isRtlLanguage && $currentLayoutDirection === 'right') {
                //     // Switching from Arabic to non-RTL language - update to LTR
                //     $currentLayoutDirection = 'left';
                // }
            } else {
                // For unauthenticated users, get from superadmin settings
                $superAdmin = User::where('type', 'superadmin')->first();
                if ($superAdmin) {
                    $superAdminSettings = settings($superAdmin->id);
                    $currentLayoutDirection = $superAdminSettings['layoutDirection'] ?? 'left';
                }
            }
        }
        
        // Convert to CSS direction value
        $layoutDirection = $currentLayoutDirection === 'right' ? 'rtl' : 'ltr';

        // Store in cookies if in demo mode
        if ($isDemo) {
            $cookieOptions = 60 * 24 * 365; // 1 year for demo mode
            Cookie::queue('app_language', $locale, $cookieOptions);
            Cookie::queue('app_direction', $layoutDirection, $cookieOptions);
            Cookie::queue('layoutDirection', $currentLayoutDirection, $cookieOptions);
            Cookie::queue('taskly_demo_language', $locale, $cookieOptions);
            Cookie::queue('selected_language', $locale, $cookieOptions); // Add this for consistency
        }

        $translations = json_decode(File::get($path), true);

        // Add layout direction to the response
        $response = [
            'translations' => $translations,
            'layoutDirection' => $layoutDirection,
            'locale' => $locale,
            'isDemo' => $isDemo
        ];

        return response()->json($response);
    }

    // Add a method to get the initial locale
    public function getInitialLocale()
    {     
        // Check if demo mode
        $isDemo = config('app.is_demo') || (request()->cookie('is_demo') === 'true');

        if ($isDemo) {
            // In demo mode, check cookie first
            $cookieLang = Cookie::get('taskly_demo_language') ?: Cookie::get('app_language');
            if ($cookieLang) {
                return response($cookieLang);
            }
        }

        if (auth()->check()) {
            // For authenticated users, get from user preferences
            return response(auth()->user()->lang ?? 'en');
        } else if (request()->is('login', 'register', 'password/*', 'email/*')) {
            // For auth pages, get from superadmin
            $superAdmin = User::where('type', 'superadmin')->first();
            return response($superAdmin->lang ?? 'en');
        }

        // Default fallback
        return response('en');
    }
} 