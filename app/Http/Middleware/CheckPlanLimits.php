<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Services\PlanLimitService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanLimits
{
    public function __construct(private PlanLimitService $planLimitService)
    {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip plan limits in non-SaaS mode
        if (!isSaasMode()) {
            return $next($request);
        }

        // Same exemption as CheckPlanAccess — the dashboard must stay reachable
        // without a plan so it can show a subscribe prompt in place, instead of
        // bouncing the user straight to /plans with no context. Without this,
        // the two plan middleware disagreed: CheckPlanAccess let dashboard
        // through, CheckPlanLimits silently redirected it away anyway.
        $currentRoute = $request->route()?->getName();
        if ($currentRoute && in_array($currentRoute, ['dashboard', 'dashboard.redirect'])) {
            return $next($request);
        }

        $user = auth()->user();

        if (!$user || $user->isSuperAdmin()) {
            return $next($request);
        }

        $role = $user->getCurrentWorkspaceRole();

        // Determine whose plan to check
        if ($role === 'owner') {
            $planUser = $user;
        } else {
            $workspace = Workspace::find($user->current_workspace_id);
            $planUser  = $workspace?->owner;
        }

        if (!$planUser) {
            return $next($request);
        }

        if ($planUser->needsPlanSubscription()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error'    => 'Plan subscription required',
                    'redirect' => route('plans.index'),
                ], 402);
            }
            
            return redirect()->route('plans.index')
                ->with('error', 'Please subscribe to a plan to continue using the service.');
        }

        // Approaching limits warnings — only relevant for the owner
        if ($role === 'owner') {
            $warnings = $this->planLimitService->getApproachingLimits($user);
            if (!empty($warnings)) {
                session()->flash('plan_warnings', $warnings);
            }
        }

        return $next($request);
    }
}