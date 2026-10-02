<?php

namespace App\Http\Controllers;

use App\Models\PlanRequest;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PlanRequestController extends BaseController
{
    use HasPermissionChecks;
    public function index(Request $request)
    {
        $this->authorizePermission('plan_manage_requests');
        
        $query = PlanRequest::with(['user', 'plan', 'approver', 'rejector']);

        // Apply search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhereHas('plan', function ($planQuery) use ($search) {
                    $planQuery->where('name', 'like', "%{$search}%");
                });
            });
        }

        // Apply filters
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('plan_requests.status', $request->status);
        }

        // Apply sorting
        $sortField = $request->get('sort_field');
        $sortDirection = $request->get('sort_direction', 'desc');
        
        // Only apply sorting if sort_field is explicitly provided
        if ($sortField) {
            // Validate sort direction
            if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
                $sortDirection = 'desc';
            }
            
            // Handle nested relationship sorting
            switch ($sortField) {
                case 'user.name':
                    $query->join('users', 'plan_requests.user_id', '=', 'users.id')
                          ->orderBy('users.name', $sortDirection)
                          ->select('plan_requests.*');
                    break;
                case 'user.email':
                    $query->join('users', 'plan_requests.user_id', '=', 'users.id')
                          ->orderBy('users.email', $sortDirection)
                          ->select('plan_requests.*');
                    break;
                case 'plan.name':
                    $query->join('plans', 'plan_requests.plan_id', '=', 'plans.id')
                          ->orderBy('plans.name', $sortDirection)
                          ->select('plan_requests.*');
                    break;
                case 'created_at':
                case 'status':
                    $query->orderBy($sortField, $sortDirection);
                    break;
                default:
                    // Invalid sort field, use default
                    $query->orderBy('created_at', 'desc');
                    $sortField = null;
                    $sortDirection = null;
                    break;
            }
        } else {
            // Default sorting when no sort is specified
            $query->orderBy('created_at', 'desc');
            // Don't set sortField and sortDirection in response when using default
            $sortField = null;
            $sortDirection = null;
        }

        $perPage = $request->get('per_page', 10);
        $planRequests = $query->paginate($perPage);

        $planRequests->getCollection()->transform(function ($planRequest) {
            if ($planRequest->user) {
                $planRequest->user->avatar = check_file($planRequest->user->avatar)
                    ? get_file($planRequest->user->avatar)
                    : get_file('avatars/avatar.png');
            }
            return $planRequest;
        });

        return Inertia::render('plans/plan-request', [
            'planRequests' => $planRequests,
            'filters' => $request->only(['search', 'status', 'per_page']) + [
                'sort_field' => $sortField,
                'sort_direction' => $sortDirection
            ]
        ]);
    }

    public function approve(PlanRequest $planRequest)
    {
        $this->authorizePermission('plan_manage_requests');
        
        $planRequest->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);

        // Create plan order
        \App\Models\PlanOrder::create([
            'user_id' => $planRequest->user_id,
            'plan_id' => $planRequest->plan_id,
            'billing_cycle' => $planRequest->duration,
            'original_price' => $planRequest->duration === 'yearly' ? $planRequest->plan->yearly_price : $planRequest->plan->price,
            'final_price' => $planRequest->duration === 'yearly' ? $planRequest->plan->yearly_price : $planRequest->plan->price,
            'status' => 'approved'
        ]);

        // Assign the plan to the user (routes to plan_id or addon_plan_id
        // depending on the requested plan's type — see PlanOrder::assignToUser())
        $expiresAt = $planRequest->duration === 'yearly' ? now()->addYear() : now()->addMonth();
        $user = \App\Models\User::findOrFail($planRequest->user_id);

        if ($planRequest->plan->plan_type === 'addon') {
            $user->update([
                'addon_plan_id' => $planRequest->plan_id,
                'addon_is_active' => 1,
                'addon_expire_date' => $expiresAt,
            ]);
        } else {
            $user->update([
                'plan_id' => $planRequest->plan_id,
                'plan_is_active' => 1,
                'plan_expire_date' => $expiresAt,
            ]);
        }

        return redirect()->route('plan-requests.index')->with('success', __('Plan request approved successfully!'));
    }

    public function reject(PlanRequest $planRequest)
    {
        $this->authorizePermission('plan_manage_requests');
        
        $planRequest->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => Auth::id(),
        ]);

        return redirect()->route('plan-requests.index')->with('success', __('Plan request rejected successfully!'));
    }

    public function myRequests(Request $request)
    {
        $query = PlanRequest::with(['user', 'plan', 'approver', 'rejector'])
            ->where('user_id', Auth::id());

        // Apply search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('plan', function ($planQuery) use ($search) {
                $planQuery->where('name', 'like', "%{$search}%");
            });
        }

        // Apply filters
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('plan_requests.status', $request->status);
        }

        // Apply sorting
        $sortField = $request->get('sort_field');
        $sortDirection = $request->get('sort_direction', 'desc');
        
        if ($sortField) {
            if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
                $sortDirection = 'desc';
            }
            
            switch ($sortField) {
                case 'user.name':
                    $query->join('users', 'plan_requests.user_id', '=', 'users.id')
                          ->orderBy('users.name', $sortDirection)
                          ->select('plan_requests.*');
                    break;
                case 'user.email':
                    $query->join('users', 'plan_requests.user_id', '=', 'users.id')
                          ->orderBy('users.email', $sortDirection)
                          ->select('plan_requests.*');
                    break;
                case 'plan.name':
                    $query->join('plans', 'plan_requests.plan_id', '=', 'plans.id')
                          ->orderBy('plans.name', $sortDirection)
                          ->select('plan_requests.*');
                    break;
                case 'created_at':
                case 'status':
                    $query->orderBy($sortField, $sortDirection);
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
                    $sortField = null;
                    $sortDirection = null;
                    break;
            }
        } else {
            $query->orderBy('created_at', 'desc');
            $sortField = null;
            $sortDirection = null;
        }

        $perPage = $request->get('per_page', 10);
        $planRequests = $query->paginate($perPage);

        $planRequests->getCollection()->transform(function ($planRequest) {
            if ($planRequest->user) {
                $planRequest->user->avatar = check_file($planRequest->user->avatar)
                    ? get_file($planRequest->user->avatar)
                    : get_file('avatars/avatar.png');
            }
            return $planRequest;
        });

        return Inertia::render('plans/plan-request', [
            'planRequests' => $planRequests,
            'filters' => $request->only(['search', 'status', 'per_page']) + [
                'sort_field' => $sortField,
                'sort_direction' => $sortDirection
            ],
            'isMyRequests' => true
        ]);
    }

    public function cancelMyRequest(PlanRequest $planRequest)
    {
        // Ensure user can only cancel their own pending requests
        if ($planRequest->user_id !== Auth::id()) {
            abort(403, __('Unauthorized'));
        }

        if ($planRequest->status !== 'pending') {
            return back()->withErrors(['error' => __('Only pending requests can be cancelled')]);
        }

        $planRequest->update([
            'status' => 'cancelled',
            'rejected_at' => now(),
            'rejected_by' => Auth::id()
        ]);

        return back()->with('success', __('Plan request cancelled successfully'));
    }

    public function destroy(PlanRequest $planRequest)
    {
        $this->authorizePermission('plan_manage_requests');
        
        // Only allow deleting cancelled requests
        if ($planRequest->status !== 'cancelled') {
            return back()->withErrors(['error' => __('Only cancelled requests can be deleted')]);
        }

        $planRequest->delete();

        return back()->with('success', __('Plan request deleted successfully'));
    }
}
