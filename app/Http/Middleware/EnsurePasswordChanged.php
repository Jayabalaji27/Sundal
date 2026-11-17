<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A user whose owner gave them a temporary password (Team page → Set password)
 * can do nothing but choose their own password, or sign out.
 */
class EnsurePasswordChanged
{
    /** Routes a user with a temporary password may still use. */
    private const ALLOWED = ['password.change', 'password.change.update', 'logout', 'impersonate.leave'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->must_change_password
            // A Super Admin looking around as this user shouldn't be stopped.
            || $request->session()->has('impersonated_by')
            || $request->routeIs(...self::ALLOWED)) {
            return $next($request);
        }

        if ($request->expectsJson() && !$request->header('X-Inertia')) {
            return response()->json(['message' => __('Choose a new password to continue.')], 423);
        }

        return redirect()->route('password.change');
    }
}
