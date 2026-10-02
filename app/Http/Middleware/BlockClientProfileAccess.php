<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BlockClientProfileAccess
{
    /**
     * Client accounts are managed by the workspace owner - clients may edit their own
     * profile and password, but can't delete their account (profile.destroy).
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $role = $user?->currentWorkspace?->getMemberRole($user);

        abort_if($role === 'client', 403);

        return $next($request);
    }
}
