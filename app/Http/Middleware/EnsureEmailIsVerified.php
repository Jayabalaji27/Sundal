<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Cache;

class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next)
    {
        $emailVerificationEnabled = isSaasMode()
            ? $this->saasEmailVerificationEnabled()
            : getSetting('emailVerification', false);

        if ($emailVerificationEnabled &&
            $request->user() &&
            $request->user() instanceof MustVerifyEmail &&
            !$request->user()->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return $next($request);
    }

    /**
     * In SaaS mode, `emailVerification` is a platform-wide toggle only the
     * superadmin can set (SystemSettingsController stores it at
     * user_id=superadmin, workspace_id=null). getSetting()'s default
     * auto-scoping resolves to the *acting* user's own settings row instead
     * (their own id/workspace for a company owner, or their creator's for a
     * team member) - which never matches the superadmin's row, so the toggle
     * was always read as unset/false. Look it up against the superadmin's
     * row explicitly, reusing the same cache key `settings()` uses to find
     * the superadmin (helper.php).
     */
    private function saasEmailVerificationEnabled(): bool
    {
        $superAdmin = Cache::remember('settings_default_user', 60, function () {
            return User::where('type', 'superadmin')->first();
        });

        if (! $superAdmin) {
            return false;
        }

        return (bool) getSetting('emailVerification', false, $superAdmin->id, null);
    }
}