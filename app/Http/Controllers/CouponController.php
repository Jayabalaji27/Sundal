<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Http\Requests\CouponRequest;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CouponController extends BaseController
{
    use HasPermissionChecks;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorizePermission('coupon_view_any');
        $query = Coupon::with('creator');

        // Apply search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Apply filters
        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Apply sorting
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        
        // Validate sort field to prevent SQL injection
        $allowedSortFields = ['name', 'code', 'type', 'expiry_date', 'created_at'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'created_at';
        }
        
        // Validate sort direction
        if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }
        
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 10);
        $coupons = $query->paginate($perPage);

        return Inertia::render('coupons/index', [
            'coupons' => $coupons,
            'filters' => $request->only(['search', 'type', 'status', 'per_page', 'sort_field', 'sort_direction'])
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Coupon $coupon)
    {
        $this->authorizePermission('coupon_view');
        
        $coupon->load('creator');
        
        // Get usage history from plan_orders table
        $usageHistory = \DB::table('plan_orders')
            ->join('users', 'plan_orders.user_id', '=', 'users.id')
            ->join('plans', 'plan_orders.plan_id', '=', 'plans.id')
            ->where('plan_orders.coupon_id', $coupon->id)
            ->select(
                'plan_orders.id',
                'users.name as user_name',
                'users.email as user_email',
                'plan_orders.order_number',
                'plan_orders.original_price as amount',
                'plan_orders.discount_amount',
                'plan_orders.created_at as used_at'
            )
            ->orderBy('plan_orders.created_at', 'desc')
            ->paginate($request->get('per_page', 10));
        
        // Calculate used count
        $coupon->used_count = \DB::table('plan_orders')
            ->where('coupon_id', $coupon->id)
            ->count();
        
        return Inertia::render('coupons/show', [
            'coupon' => $coupon,
            'usage_history' => $usageHistory
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CouponRequest $request)
    {
        $this->authorizePermission('coupon_create');

        $data = $request->all();
        $data['created_by'] = Auth::id();

        // Generate code if auto-generate is selected
        if ($request->code_type === 'auto') {
            do {
                $data['code'] = strtoupper(Str::random(8));
            } while (Coupon::where('code', $data['code'])->exists());
        }

        $coupon = Coupon::create($data);

        return redirect()->route('coupons.index')->with('success', __('Coupon created successfully!'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CouponRequest $request, Coupon $coupon)
    {
        $this->authorizePermission('coupon_update');

        $data = $request->all();

        // Generate new code if switching to auto-generate
        if ($request->code_type === 'auto' && $coupon->code_type !== 'auto') {
            do {
                $data['code'] = strtoupper(Str::random(8));
            } while (Coupon::where('code', $data['code'])->where('id', '!=', $coupon->id)->exists());
        }

        $coupon->update($data);

        return redirect()->route('coupons.index')->with('success', __('Coupon updated successfully!'));
    }

    /**
     * Validate coupon code
     */
    public function validate(Request $request)
    {
        $request->validate([
            'coupon_code' => 'required|string',
            'plan_id' => 'required|integer',
            'amount' => 'required|numeric|min:0'
        ]);
        
        $coupon = Coupon::where('code', $request->coupon_code)
            ->where('status', 1)
            ->first();
            
        if (!$coupon) {
            return response()->json([
                'valid' => false,
                'message' => __('Invalid or inactive coupon code')
            ], 400);
        }
        
        // Check if coupon is expired
        if ($coupon->expiry_date && $coupon->expiry_date < now()) {
            return response()->json([
                'valid' => false,
                'message' => __('Coupon has expired')
            ], 400);
        }
        
        // Check usage limit
        if ($coupon->use_limit_per_coupon && $coupon->used_count >= $coupon->use_limit_per_coupon) {
            return response()->json([
                'valid' => false,
                'message' => __('Coupon usage limit exceeded')
            ], 400);
        }
        
        // Check minimum amount
        if ($coupon->minimum_spend && $request->amount < $coupon->minimum_spend) {
            return response()->json([
                'valid' => false,
                'message' => __('Minimum spend requirement not met')
            ], 400);
        }
        
        return response()->json([
            'valid' => true,
            'coupon' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => $coupon->discount_amount
            ]
        ]);
    }

    /**
     * Toggle the status of the specified coupon.
     */
    public function toggleStatus(Coupon $coupon)
    {
        $this->authorizePermission('coupon_toggle_status');
        $coupon->update([
            'status' => !$coupon->status
        ]);

        return redirect()->back()->with('success', __('Coupon status updated successfully!'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Coupon $coupon)
    {
        $this->authorizePermission('coupon_delete');
        $coupon->delete();

        return redirect()->route('coupons.index')->with('success', __('Coupon deleted successfully!'));
    }
}
