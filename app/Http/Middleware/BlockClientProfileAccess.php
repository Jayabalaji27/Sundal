<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BlockClientProfileAccess
{
    /**
     * The Client role has no self-service profile page (avatar/name/email/password are
     * managed by the workspace owner) — block direct navigation to profile.* routes,
     * not just the nav link, so a client can't bypass the removed UI by URL.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $role = $user?->currentWorkspace?->getMemberRole($user);

        abort_if($role === 'client', 403);

        return $next($request);
    }
}
