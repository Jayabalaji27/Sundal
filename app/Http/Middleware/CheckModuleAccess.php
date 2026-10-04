<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the AI / Knowledge Base / Chat / Meetings module cluster behind the
 * "Pro Add-on" plan, on top of (not instead of) the existing Spatie
 * `permission:` middleware already on these routes. SaaS-mode only, mirroring
 * CheckPlanAccess/CheckPlanLimits.
 */
class CheckModuleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!isSaasMode()) {
            return $next($request);
        }

        $user = auth()->user();

        if (!self::locksModulesFor($user)) {
            return $next($request);
        }

        $message = __('This feature requires the Pro Add-on plan (AI, Knowledge Base, Chat & Meetings). Please upgrade to continue.');

        // Only the workspace owner can view/manage plans (plan_view_any is an
        // owner-only permission, see RoleSeeder). Redirecting a manager/member/
        // client to /plans would just trade this error for a 403 on that route,
        // so send them somewhere they can actually land instead.
        $canViewPlans = $user->can('plan_view_any');

        if ($request->expectsJson()) {
            return response()->json([
                'error' => $message,
                'redirect' => $canViewPlans ? route('plans.index') : route('dashboard'),
            ], 402);
        }

        if (!$canViewPlans) {
            $message = __('This feature requires the Pro Add-on plan. Please ask your workspace owner to upgrade.');

            return redirect()->route('dashboard')->with('error', $message);
        }

        return redirect()->route('plans.index')->with('error', $message);
    }

    /**
     * Whether the add-on modules are locked for this user. Shared with the
     * frontend too, so gated buttons are hidden by the same rule that blocks
     * the route.
     */
    public static function locksModulesFor(?User $user): bool
    {
        if (!isSaasMode() || !$user || $user->isSuperAdmin()) {
            return false;
        }

        // Members ride on their workspace owner's add-on.
        $moduleUser = $user->getCurrentWorkspaceRole() === 'owner'
            ? $user
            : Workspace::find($user->current_workspace_id)?->owner;

        return $moduleUser && !$moduleUser->hasActiveAddon();
    }
}
