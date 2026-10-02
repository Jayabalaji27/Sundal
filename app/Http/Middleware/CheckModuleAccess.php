<?php

namespace App\Http\Middleware;

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

        if (!$user || $user->isSuperAdmin()) {
            return $next($request);
        }

        $role = $user->getCurrentWorkspaceRole();

        if ($role === 'owner') {
            $moduleUser = $user;
        } else {
            $workspace = Workspace::find($user->current_workspace_id);
            $moduleUser = $workspace?->owner;
        }

        if (!$moduleUser || $moduleUser->hasActiveAddon()) {
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
}
