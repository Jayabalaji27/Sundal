<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;
use App\Models\Currency;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        // Skip database queries during installation
        if ($request->is('install/*') || $request->is('update/*') || !file_exists(storage_path('installed'))) {
            // Get available languages even during installation
            $languagesFile = resource_path('lang/language.json');
            $availableLanguages = [];
            if (file_exists($languagesFile)) {
                $availableLanguages = json_decode(file_get_contents($languagesFile), true) ?? [];
            }
            
            $globalSettings = [
                'currencySymbol' => '$',
                'currencyNname' => 'US Dollar',
                'base_url' => config('app.url'),
                'availableLanguages' => $availableLanguages,
            ];
        } else {
            $user = $request->user();
            $isDemo = config('app.is_demo');
            $isSaas = isSaasMode();

            if ($isDemo) {
                // DEMO MODE: Use base settings with cookie overrides
                $settings = settings();
                $settings = $this->applyCookieOverrides($request, $settings);
            } else {
                // NORMAL MODE: Use database with cookie overrides for user preferences
                if ($isSaas) {
                    // SaaS Mode
                    if ($user && $user->current_workspace_id) {
                        // Inside company: use company settings
                        $workspace = $user->currentWorkspace;
                        if ($workspace && $workspace->owner_id) {
                            $settings = settings($workspace->owner_id, $user->current_workspace_id);
                        } else {
                            $settings = settings($user->id, $user->current_workspace_id);
                        }
                    } else {
                        // Login/register pages: use superadmin settings
                        $settings = settings();
                    }
                    // Apply user preference cookies for sidebar and layout (SaaS)
                    $settings = $this->applyUserPreferenceCookies($request, $settings);
                } else {
                    // Non-SaaS Mode - use database settings, still honoring the
                    // user's theme/sidebar/layout cookie preference (matches SaaS branch)
                    $settings = settings();
                    $settings = $this->applyUserPreferenceCookies($request, $settings);
                }
            }

            // Get currency symbol with error handling
            $currencyCode = $settings['defaultCurrency'] ?? 'USD';
            $currencySettings = [
                'currencySymbol' => '$',
                'currencyNname' => 'US Dollar'
            ];

            try {
                $currency = Currency::where('code', $currencyCode)->first();
                if ($currency) {
                    $currencySettings = [
                        'currencySymbol' => $currency->symbol,
                        'currencyNname' => $currency->name
                    ];
                }
            } catch (\Exception $e) {
                // Log the error but continue with default currency
                \Log::warning('Failed to fetch currency: ' . $e->getMessage());
            }

            // Get available languages
            $languagesFile = resource_path('lang/language.json');
            $availableLanguages = [];
            if (file_exists($languagesFile)) {
                $availableLanguages = json_decode(file_get_contents($languagesFile), true) ?? [];
            }
            
            // Merge currency settings with other settings
            $globalSettings = array_merge($settings, $currencySettings);
            $globalSettings['base_url'] = config('app.url');
            $globalSettings['is_saas'] = isSaasMode();
            $globalSettings['availableLanguages'] = $availableLanguages;

        }
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'base_url' => config('app.url'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'csrf_token' => csrf_token(),
            'auth' => [
                'user' => $request->user() ? array_merge(
                    $request->user()->loadMissing(['currentWorkspace', 'ownedWorkspaces', 'workspaces'])->toArray(),
                    [
                        'avatar' => check_file($request->user()->avatar) 
                            ? get_file($request->user()->avatar) 
                            : asset('images/avatar/avatar.png'),
                        'workspace_role' => $request->user()->currentWorkspace
                            ? $request->user()->currentWorkspace->getMemberRole($request->user())
                            : null,
                    ]
                ) : null,
                'roles' => fn() => $this->getUserRoles($request),
                'permissions' => fn() => $this->getUserPermissions($request),
            ],
            'workspaceSettings' => fn() => $this->getWorkspaceSettings($request),
            'unreadNotificationsCount' => fn() => $this->getUnreadNotificationsCount($request),
            'recentNotifications' => fn() => $this->getRecentNotifications($request),
            'isImpersonating' => session('impersonated_by') ? true : false,
            'ziggy' => fn(): array => [
                ...(new Ziggy)->toArray(),
                'url'      => rtrim($request->root(), '/'),
                'location' => $request->url(),
            ],
            'flash' => [
                'success'         => $request->session()->get('success'),
                'error'           => $request->session()->get('error'),
                'email_error'     => $request->session()->get('email_error'),
                'warning'         => $request->session()->get('warning'),
                'invitation_link' => $request->session()->get('invitation_link'),
                'status'          => $request->session()->get('status'),
                'statusType'      => $request->session()->get('statusType'),
                'new_api_key'     => $request->session()->get('new_api_key'),
            ],
            'globalSettings' => $globalSettings,
            'userLanguage' => $request->user()?->lang ?? config('app.locale', 'en'),
            'storageSettings' => $this->getStorageSettings($request),
            'allowed_file_types' => getSetting('storage_file_types', 'jpg,jpeg,png,svg,gif,zip,txt,pdf,docx', null, null),
            'max_file_size' => (int) getSetting('storage_max_file_size', 2048, null, null),
            'is_demo' => config('app.is_demo', false),
            'isDemoMode' => config('app.is_demo', false),
            'isSaasMode' => isSaasMode(),
            'is_saas' => isSaasMode(),
            'features' => config('features', []),

        ];
    }

    private function getStorageSettings(Request $request): array
    {
        try {
            $user = $request->user();
            if (!$user) return ['storage_type' => 'local'];
            $adminSettings = getAdminAllSetting();
            return [
                'storage_type' => $adminSettings['storage_type'] ?? 'local',
            ];
        } catch (\Exception $e) {
            return ['storage_type' => 'local'];
        }
    }

    /**
     * Get workspace-specific settings for the current user
     */
    private function getWorkspaceSettings(Request $request): array
    {
        $user = $request->user();

        if (!$user || !$user->current_workspace_id) {
            return [];
        }

        // Check session first for immediate access after workspace switch
        $sessionSettings = session('workspace_settings');
        $sessionPaymentSettings = session('workspace_payment_settings');

        if ($sessionSettings) {
            $settings = $sessionSettings;
            $settings['payment_settings'] = $sessionPaymentSettings ?? [];
            return $settings;
        }

        // Fallback to database query
        $workspace = $user->currentWorkspace;
        if (!$workspace) {
            return [];
        }

        $paymentSettings = \App\Models\PaymentSetting::getUserSettings($user->id, $workspace->id);

        return [
            'timesheet_enabled' => $workspace->timesheet_enabled,
            'timesheet_approval_required' => $workspace->timesheet_approval_required,
            'timesheet_auto_submit' => $workspace->timesheet_auto_submit,
            'timesheet_reminder_days' => $workspace->timesheet_reminder_days,
            'default_work_start' => $workspace->default_work_start?->format('H:i'),
            'default_work_end' => $workspace->default_work_end?->format('H:i'),
            'settings' => $workspace->settings ?? [],
            'payment_settings' => $paymentSettings
        ];
    }

    /**
     * Scope the authenticated user's notifications to their current workspace.
     */
    private function workspaceNotifications(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->current_workspace_id) {
            return null;
        }

        return $user->notifications()->where('data->workspace_id', $user->current_workspace_id);
    }

    private function getUnreadNotificationsCount(Request $request): int
    {
        $query = $this->workspaceNotifications($request);

        return $query ? $query->whereNull('read_at')->count() : 0;
    }

    private function getRecentNotifications(Request $request): array
    {
        $query = $this->workspaceNotifications($request);

        if (!$query) {
            return [];
        }

        return $query->latest()
            ->limit(10)
            ->get()
            ->map(fn ($notification) => \App\Http\Controllers\NotificationController::format($notification))
            ->all();
    }

    /**
     * Get user roles based on SaaS mode
     */
    private function getUserRoles(Request $request): array
    {
        $user = $request->user();
        if (!$user) {
            return [];
        }

        if (isSaasMode()) {
            return $user->roles->pluck('name')->toArray();
        }

        // Non-SaaS mode: get workspace role
        if ($user->current_workspace_id) {
            $workspaceMember = \App\Models\WorkspaceMember::where('user_id', $user->id)
                ->where('workspace_id', $user->current_workspace_id)
                ->first();

            return $workspaceMember ? [$workspaceMember->role] : [];
        }

        return [];
    }

    /**
     * Get user permissions based on SaaS mode
     */
    private function getUserPermissions(Request $request): array
    {
        $user = $request->user();
        if (!$user) {
            return [];
        }

        // If user is superadmin, give all permissions
        if ($user->type === 'superadmin') {
            return $user->getAllPermissions()->pluck('name')->toArray();
        }
        
        if ($user->current_workspace_id) {
            $workspace = $user->currentWorkspace;
            if ($workspace && $workspace->owner_id == $user->id) {
                return $user->getAllPermissions()->pluck('name')->toArray();
            } else {
                $workspaceMember = \App\Models\WorkspaceMember::where('user_id', $user->id)
                    ->where('workspace_id', $user->current_workspace_id)
                    ->first();
                if ($workspaceMember) {
                    $role = Role::findByName($workspaceMember->role);
                    return $role->permissions->pluck('name')->values()->toArray();
                }
            }
        }
        return [];
    }

    /**
     * Get brand settings from cookies (demo mode)
     */
    private function getCookieSettings(Request $request): array
    {
        $settings = settings(); // Start with default settings
        $cookieOverrides = [];

        // Theme settings cookie
        $themeCookie = $request->cookie('themeSettings');
        if ($themeCookie) {
            try {
                $themeData = json_decode($themeCookie, true);
                if ($themeData) {
                    if (isset($themeData['themeColor']))
                        $cookieOverrides['themeColor'] = $themeData['themeColor'];
                    if (isset($themeData['customColor']))
                        $cookieOverrides['customColor'] = $themeData['customColor'];
                    if (isset($themeData['appearance']))
                        $cookieOverrides['themeMode'] = $themeData['appearance'];
                }
            } catch (\Exception $e) {
            }
        }

        // Sidebar settings cookie
        $sidebarCookie = $request->cookie('sidebarSettings');
        if ($sidebarCookie) {
            try {
                $sidebarData = json_decode($sidebarCookie, true);
                if ($sidebarData) {
                    if (isset($sidebarData['variant']))
                        $cookieOverrides['sidebarVariant'] = $sidebarData['variant'];
                    if (isset($sidebarData['style']))
                        $cookieOverrides['sidebarStyle'] = $sidebarData['style'];
                }
            } catch (\Exception $e) {
            }
        }

        // Layout position cookie
        $layoutCookie = $request->cookie('layoutPosition');
        if ($layoutCookie) {
            $cookieOverrides['layoutDirection'] = $layoutCookie;
        }

        // Brand settings cookie (complete brand settings)
        $brandCookie = $request->cookie('brandSettings');
        if ($brandCookie) {
            try {
                $brandData = json_decode($brandCookie, true);
                if ($brandData) {
                    $cookieOverrides = array_merge($cookieOverrides, $brandData);
                }
            } catch (\Exception $e) {
            }
        }

        return array_merge($settings, $cookieOverrides);
    }

    /**
     * Apply user preference cookies for sidebar and layout (normal mode)
     */
    private function applyUserPreferenceCookies(Request $request, array $settings): array
    {
        $cookieOverrides = [];

        // Theme settings cookie — dark/light mode preference
        $themeCookie = $request->cookie('themeSettings');
        if ($themeCookie) {
            try {
                $themeData = json_decode($themeCookie, true);
                if ($themeData) {
                    if (isset($themeData['appearance']))
                        $cookieOverrides['themeMode'] = $themeData['appearance'];
                }
            } catch (\Exception $e) {
            }
        }

        // Sidebar settings cookie
        $sidebarCookie = $request->cookie('sidebarSettings');
        if ($sidebarCookie) {
            try {
                $sidebarData = json_decode($sidebarCookie, true);
                if ($sidebarData) {
                    if (isset($sidebarData['variant']))
                        $cookieOverrides['sidebarVariant'] = $sidebarData['variant'];
                    if (isset($sidebarData['style']))
                        $cookieOverrides['sidebarStyle'] = $sidebarData['style'];
                }
            } catch (\Exception $e) {
            }
        }

        // Layout position cookie
        $layoutCookie = $request->cookie('layoutPosition');
        if ($layoutCookie) {
            $cookieOverrides['layoutDirection'] = $layoutCookie;
        }

        return array_merge($settings, $cookieOverrides);
    }

    /**
     * Apply cookie overrides to base settings (demo mode)
     */
    private function applyCookieOverrides(Request $request, array $settings): array
    {
        $cookieOverrides = [];

        // Theme settings cookie
        $themeCookie = $request->cookie('themeSettings');
        if ($themeCookie) {
            try {
                $themeData = json_decode($themeCookie, true);
                if ($themeData) {
                    if (isset($themeData['themeColor']))
                        $cookieOverrides['themeColor'] = $themeData['themeColor'];
                    if (isset($themeData['customColor']))
                        $cookieOverrides['customColor'] = $themeData['customColor'];
                    if (isset($themeData['appearance']))
                        $cookieOverrides['themeMode'] = $themeData['appearance'];
                }
            } catch (\Exception $e) {
            }
        }

        // Sidebar settings cookie
        $sidebarCookie = $request->cookie('sidebarSettings');
        if ($sidebarCookie) {
            try {
                $sidebarData = json_decode($sidebarCookie, true);
                if ($sidebarData) {
                    if (isset($sidebarData['variant']))
                        $cookieOverrides['sidebarVariant'] = $sidebarData['variant'];
                    if (isset($sidebarData['style']))
                        $cookieOverrides['sidebarStyle'] = $sidebarData['style'];
                }
            } catch (\Exception $e) {
            }
        }

        // Layout position cookie
        $layoutCookie = $request->cookie('layoutPosition');
        if ($layoutCookie) {
            $cookieOverrides['layoutDirection'] = $layoutCookie;
        }

        // Brand settings cookie (complete brand settings)
        $brandCookie = $request->cookie('brandSettings');
        if ($brandCookie) {
            try {
                $brandData = json_decode($brandCookie, true);
                if ($brandData) {
                    $cookieOverrides = array_merge($cookieOverrides, $brandData);
                }
            } catch (\Exception $e) {
            }
        }

        return array_merge($settings, $cookieOverrides);
    }


}
