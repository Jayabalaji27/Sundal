<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMultiplePermissions
{
    /**
     * Handle an incoming request.
     * 
     * Usage: 
     * - 'permissions:permission1,permission2' (requires ANY of the permissions)
     * - 'permissions:permission1&permission2' (requires ALL permissions)
     */
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        
        // Super admin has all permissions
        if ($user->type === 'superadmin') {
            return $next($request);
        }

        // Parse permissions string
        $requireAll = strpos($permissions, '&') !== false;
        $permissionList = $requireAll 
            ? explode('&', $permissions)
            : explode(',', $permissions);

        $permissionList = array_map('trim', $permissionList);

        if ($requireAll) {
            // User must have ALL permissions
            foreach ($permissionList as $permission) {
                if (!$user->hasPermissionTo($permission)) {
                    if ($request->expectsJson()) {
                        return response()->json(['message' => 'Forbidden'], 403);
                    }
                    return redirect()->route('dashboard.redirect');
                }
            }
        } else {
            // User must have at least ONE permission
            $hasPermission = false;
            foreach ($permissionList as $permission) {
                if ($user->hasPermissionTo($permission)) {
                    $hasPermission = true;
                    break;
                }
            }
            
            if (!$hasPermission) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Forbidden'], 403);
                }
                return redirect()->route('dashboard.redirect');
            }
        }

        return $next($request);
    }
}