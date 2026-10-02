<?php

namespace App\Http\Controllers;

use App\Models\PlanOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PlanOrderController extends BaseController
{
    public function index(Request $request)
    {
        // In non-SaaS mode, redirect to dashboard
        if (!isSaasMode()) {
            return redirect()->route('dashboard');
        }
        
        $query = PlanOrder::with(['user', 'plan', 'coupon', 'processedBy']);

        // Apply search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('coupon_code', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('plan', function ($planQuery) use ($search) {
                      $planQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Apply filters
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
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
                    $query->join('users', 'plan_orders.user_id', '=', 'users.id')
                          ->orderBy('users.name', $sortDirection)
                          ->select('plan_orders.*');
                    break;
                case 'plan.name':
                    $query->join('plans', 'plan_orders.plan_id', '=', 'plans.id')
                          ->orderBy('plans.name', $sortDirection)
                          ->select('plan_orders.*');
                    break;
                case 'order_number':
                case 'ordered_at':
                case 'status':
                case 'original_price':
                case 'final_price':
                    $query->orderBy($sortField, $sortDirection);
                    break;
                default:
                    // Invalid sort field, use default
                    $query->orderBy('ordered_at', 'desc');
                    $sortField = null;
                    $sortDirection = null;
                    break;
            }
        } else {
            // Default sorting when no sort is specified
            $query->orderBy('ordered_at', 'desc');
            // Don't set sortField and sortDirection in response when using default
            $sortField = null;
            $sortDirection = null;
        }

        $perPage = $request->get('per_page', 10);
        $planOrders = $query->paginate($perPage);
        $planOrders->getCollection()->transform(function ($order) {
            $order->receipt_url = ($order->receipt_path && check_file($order->receipt_path))
                ? get_file($order->receipt_path)
                : null;
            return $order;
        });

        return Inertia::render('plans/plan-orders', [
            'planOrders' => $planOrders,
            'filters' => $request->only(['search', 'status', 'per_page']) + [
                'sort_field' => $sortField,
                'sort_direction' => $sortDirection
            ]
        ]);
    }

    public function approve(PlanOrder $planOrder)
    {
        try {
            $planOrder->approve(Auth::id());
            
            return redirect()->route('plan-orders.index')
                ->with('success', __('Plan order approved successfully!'));
        } catch (\Exception $e) {
            return redirect()->route('plan-orders.index')
                ->with('error', __('Failed to approve plan order') . ': ' . $e->getMessage());
        }
    }

    public function reject(Request $request, PlanOrder $planOrder)
    {
        try {
            $request->validate([
                'notes' => 'nullable|string|max:500'
            ]);

            $planOrder->reject(Auth::id(), $request->notes);
            
            return redirect()->route('plan-orders.index')
                ->with('success', __('Plan order rejected successfully!'));
        } catch (\Exception $e) {
            return redirect()->route('plan-orders.index')
                ->with('error', __('Failed to reject plan order') . ': ' . $e->getMessage());
        }
    }

    public function myOrders(Request $request)
    {
        // In non-SaaS mode, redirect to dashboard
        if (!isSaasMode()) {
            return redirect()->route('dashboard');
        }
        
        $query = PlanOrder::with(['user', 'plan', 'coupon', 'processedBy'])
            ->where('user_id', Auth::id());

        // Apply search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('coupon_code', 'like', "%{$search}%")
                  ->orWhereHas('plan', function ($planQuery) use ($search) {
                      $planQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Apply filters
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
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
                    $query->join('users', 'plan_orders.user_id', '=', 'users.id')
                          ->orderBy('users.name', $sortDirection)
                          ->select('plan_orders.*');
                    break;
                case 'plan.name':
                    $query->join('plans', 'plan_orders.plan_id', '=', 'plans.id')
                          ->orderBy('plans.name', $sortDirection)
                          ->select('plan_orders.*');
                    break;
                case 'order_number':
                case 'ordered_at':
                case 'status':
                case 'original_price':
                case 'final_price':
                    $query->orderBy($sortField, $sortDirection);
                    break;
                default:
                    $query->orderBy('ordered_at', 'desc');
                    $sortField = null;
                    $sortDirection = null;
                    break;
            }
        } else {
            $query->orderBy('ordered_at', 'desc');
            $sortField = null;
            $sortDirection = null;
        }

        $perPage = $request->get('per_page', 10);
        $planOrders = $query->paginate($perPage);
        $planOrders->getCollection()->transform(function ($order) {
            $order->receipt_url = ($order->receipt_path && check_file($order->receipt_path))
                ? get_file($order->receipt_path)
                : null;
            return $order;
        });

        return Inertia::render('plans/plan-orders', [
            'planOrders' => $planOrders,
            'filters' => $request->only(['search', 'status', 'per_page']) + [
                'sort_field' => $sortField,
                'sort_direction' => $sortDirection
            ],
            'isMyOrders' => true
        ]);
    }
}
