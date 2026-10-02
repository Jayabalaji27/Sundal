<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Plan;
use App\Models\Workspace;

class CheckPlanAccess
{
    public function handle(Request $request, Closure $next)
    {
        // Skip plan checks in non-SaaS mode
        if (!isSaasMode()) {
            return $next($request);
        }
        
        $user = auth()->user();
        
        if (!$user) {
            return $next($request);
        }

        // Allow dashboard access without a plan subscription (show subscribe prompt there)
        $currentRoute = $request->route()?->getName();
        $skipRoutes = ['dashboard', 'dashboard.redirect'];
        if ($currentRoute && in_array($currentRoute, $skipRoutes)) {
            return $next($request);
        }

        // Super admin has full access
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        //check if user has a current workspace assigned, saftey check
        if (!$user->current_workspace_id) {
            auth()->logout();
            return redirect()->route('login')->with('error', __('No workspace assigned. Please contact support.'));
        }
        // Get the user's role in the current workspace
        $role = $user->getCurrentWorkspaceRole();
        //dd($role);

        // --- Owner Check ---
        if ($role === 'owner') {
            if (!$user->needsPlanSubscription()) {
                return $next($request);
            }

            $message = __('Please subscribe to a plan to continue.');

            if ($user->isTrialExpired()) {
                $message = __('Your trial period has expired. Please subscribe to a plan to continue.');

                $getDefaultPlan = Plan::getDefaultPlan();

                if ($getDefaultPlan) {
                    $planExpireDate = match ($getDefaultPlan->duration) {
                        'yearly'  => now()->addYear(),
                        'monthly' => now()->addMonth(),
                        default   => now(),
                    };
                    // Assign default plan to user
                    $user->update([
                        'plan_id'          => $getDefaultPlan->id,
                        'plan_expire_date' => $planExpireDate,
                        'plan_is_active'   => 1,
                        'is_trial'         => 0,
                        'trial_expire_date'=> null,
                        'trial_day'        => 0,
                    ]);

                    createPlanOrder([
                        'user_id'        => $user->id,
                        'plan_id'        => $getDefaultPlan->id,
                        'billing_cycle'  => $getDefaultPlan->duration,
                        'payment_method' => 'manual',
                        'coupon_code'    => null,
                        'payment_id'     => null,
                        'status'         => 'approved',
                        'processed_at'   => now(),
                    ]);
                } else {
                    $user->update([
                        'plan_id'          => null,
                        'plan_expire_date' => null,
                        'plan_is_active'   => 0,
                        'is_trial'         => 0,
                        'trial_expire_date'=> null,
                        'trial_day'        => 0,
                    ]);
                }
            } elseif ($user->isPlanExpired()) {
                $message = __('Your plan has expired. Please renew your subscription.');
                // Reset expired plan
                $user->update([
                    'plan_id'          => null,
                    'plan_expire_date' => null,
                    'plan_is_active'   => 0,
                ]);
            }

            return redirect()->route('plans.index')->with('error', $message);
        }

        // --- Member / Manager / Client's Owner Check ---
        $workspace = Workspace::find($user->current_workspace_id);
        $owner     = $workspace?->owner;

        //if no owner found, saftey check 
        if (!$owner) {
            auth()->logout();
            return redirect()->route('login')->with('status', __('Workspace owner not found. Please contact support.'));
        }

        // Check if the owner's plan is active
        if (!$owner->needsPlanSubscription()) {
            return $next($request);
        }

        // Owner's plan expired — try another workspace
        $activeWorkspace = $user->findWorkspaceWithActivePlan();

        // If an active workspace is found, switch to it
        if ($activeWorkspace) {
            $user->switchWorkspace($activeWorkspace);
            return redirect()->route('dashboard')->with('success', __('Your previous workspace plan has expired. You have been switched to :name.', ['name' => $activeWorkspace->name]));
        }

        // No active workspace found — log out the user
        auth()->logout();
        return redirect()->route('login')->with('status', __('All workspaces you belong to have expired plans. Please contact a workspace owner.'))->with('statusType', 'error');
    }
}