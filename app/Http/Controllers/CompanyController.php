<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Plan;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Exports\CompanyExport;
use App\Imports\CompanyImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        // `users.type` defaults to 'company' for every non-superadmin account (owners,
        // managers, members, clients alike) — it's not a tenant marker. The Spatie
        // 'company' role is what actually distinguishes a workspace-owning tenant.
        $query = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'company'))
            ->with('plan');
            
        // Apply search filter
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }
        
        // Apply status filter. "expired" = plan expiry date has passed (shown and
        // counted separately from the login-enabled active/inactive flag).
        if ($request->status === 'expired') {
            $query->whereNotNull('plan_expire_date')->where('plan_expire_date', '<', now());
        } elseif ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status)
                ->when($request->status === 'active', fn ($q) => $q->where(fn ($q) => $q
                    ->whereNull('plan_expire_date')
                    ->orWhere('plan_expire_date', '>=', now())));
        }
        
        // Apply date filters. Dates are shown in the viewer's timezone, so the
        // picked days are taken in that timezone (sent as `tz`) and converted
        // to the stored timezone, rather than compared as UTC dates (QA C2).
        $viewerTz = in_array($request->input('tz'), timezone_identifiers_list(), true)
            ? $request->input('tz')
            : config('app.timezone');
        $storedTz = config('app.timezone');

        if ($request->has('start_date') && !empty($request->start_date)) {
            $from = \Carbon\Carbon::parse($request->start_date, $viewerTz)->startOfDay()->setTimezone($storedTz);
            $query->where('created_at', '>=', $from->format('Y-m-d H:i:s'));
        }

        if ($request->has('end_date') && !empty($request->end_date)) {
            $to = \Carbon\Carbon::parse($request->end_date, $viewerTz)->endOfDay()->setTimezone($storedTz);
            $query->where('created_at', '<=', $to->format('Y-m-d H:i:s'));
        }
        
        // Apply sorting
        $sortField = $request->input('sort_field', 'created_at');
        $defaultDirection = config('app.is_demo', false) ? 'asc' : 'desc';
        $sortDirection = $request->has('sort_direction') ? $request->input('sort_direction') : $defaultDirection;
        $query->orderBy($sortField, $sortDirection);
        
        // Get paginated results
        $perPage = $request->input('per_page', 10);
        $companies = $query->paginate($perPage)->withQueryString();
        
        // Transform data for frontend
        $companies->getCollection()->transform(function ($company) {
            return [
                'id' => $company->id,
                'name' => $company->name,
                'email' => $company->email,
                'status' => $company->status,
                'created_at' => $company->created_at,
                'plan_name' => $company->plan ? $company->plan->name : __('No Plan'),
                'plan_expiry_date' => $company->plan_expire_date,
                'plan_expired' => $company->isPlanExpired(),
            ];
        });
        
        // Get plans for dropdown
        $plans = Plan::orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default']);
        
        return Inertia::render('companies/index', [
            'companies' => $companies,
            'plans' => $plans,
            'filters' => $request->only(['search', 'status', 'start_date', 'end_date', 'sort_field', 'sort_direction', 'per_page'])
        ]);
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            // email:rfc,filter rejects addresses without a dotted domain (e.g. test@test)
            'email' => 'required|string|email:rfc,filter|max:255|unique:users',
            'password' => 'nullable|string|min:8',
            'status' => 'required|in:active,inactive',
            'plan_id' => 'nullable|exists:plans,id',
            'billing_cycle' => 'nullable|in:monthly,yearly',
        ]);
        
        $company = new User();
        $company->name = $validated['name'];
        $company->email = $validated['email'];
        
        // Only set password if provided
        if (isset($validated['password'])) {
            $company->password = Hash::make($validated['password']);
        }
        
        $company->type = 'company';
        $company->status = $validated['status'];
        $company->is_enable_login = $validated['status'] === 'active' ? 1 : 0;
        
        // Set company language same as creator (superadmin)
        $creator = auth()->user();
        if ($creator && $creator->lang) {
            $company->lang = $creator->lang;
        }
        
        // Assign the chosen plan (falls back to the default plan)
        $plan = !empty($validated['plan_id'])
            ? Plan::find($validated['plan_id'])
            : Plan::where('is_default', true)->first();
        if ($plan) {
            $company->plan_id = $plan->id;

            // Expiry follows the chosen billing cycle, else the plan's own duration
            $cycle = $validated['billing_cycle'] ?? ($plan->duration === 'yearly' ? 'yearly' : 'monthly');
            $company->plan_expire_date = $cycle === 'yearly' ? now()->addYear() : now()->addMonth();
            
            // Set plan is active
            $company->plan_is_active = 1;
        }
        
        $company->save();
        
        // Assign role and settings to the user (includes workspace creation)
        defaultRoleAndSetting($company);
        
        // Trigger email notification
        if (!config('app.is_demo', true)) {
            event(new \App\Events\UserCreated($company, $validated['password'] ?? ''));
        }
        
        // Check for email errors
        if (session()->has('email_error')) {
            return redirect()->back()->with('warning', __('Company created successfully, but welcome email failed: ') . session('email_error'));
        }
        
        return redirect()->back()->with('success', __('Company created successfully'));
    }
    
    public function update(Request $request, User $company)
    {
        // Ensure this is a company type user
        if (!$company->hasRole('company')) {
            return redirect()->back()->with('error', __('Invalid company record'));
        }
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email:rfc,filter|max:255|unique:users,email,' . $company->id,
            'status' => 'required|in:active,inactive',
        ]);
        
        $company->name = $validated['name'];
        $company->email = $validated['email'];
        $company->status = $validated['status'];
        $company->is_enable_login = $validated['status'] === 'active' ? 1 : 0;
        
        $company->save();
        
        return redirect()->back()->with('success', __('Company updated successfully'));
    }
    
    public function destroy(User $company)
    {
        // Ensure this is a company type user
        if (!$company->hasRole('company')) {
            return redirect()->back()->with('error', __('Invalid company record'));
        }
        
        $company->delete();
        
        return redirect()->back()->with('success', __('Company deleted successfully'));
    }
    
    public function resetPassword(Request $request, User $company)
    {
        // Ensure this is a company type user
        if (!$company->hasRole('company')) {
            return redirect()->back()->with('error', __('Invalid company record'));
        }
        
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);
        
        $company->password = Hash::make($validated['password']);
        $company->save();
        
        return redirect()->back()->with('success', __('Password reset successfully'));
    }
    
    public function toggleStatus(User $company)
    {
        // Ensure this is a company type user
        if (!$company->hasRole('company')) {
            return redirect()->back()->with('error', __('Invalid company record'));
        }
        
        $newStatus = $company->status === 'active' ? 'inactive' : 'active';
        $company->status = $newStatus;
        $company->is_enable_login = $newStatus === 'active' ? 1 : 0;
        $company->save();
        
        return redirect()->back()->with('success', __('Company status updated successfully'));
    }
    
    /**
     * Get available plans for upgrade
     */
    public function getPlans(User $company)
    {
        // Ensure this is a company type user
        if (!$company->hasRole('company')) {
            return response()->json(['error' => __('Invalid company record')], 400);
        }
        
        $plans = Plan::where('is_plan_enable', 'on')->get();
        
        $formattedPlans = $plans->map(function ($plan) use ($company) {
            // Format features
            $features = [];
            if ($plan->enable_custdomain === 'on') $features[] = __('Custom Domain');
            if ($plan->enable_custsubdomain === 'on') $features[] = __('Subdomain');
            if ($plan->enable_chatgpt === 'on') $features[] = __('AI Integration');
            
            // Calculate yearly price
            $yearlyPrice = $plan->yearly_price;
            if ($yearlyPrice === null) {
                $yearlyPrice = $plan->price * 12 * 0.8;
            }
            
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => '$' . number_format($plan->price, 2),
                'yearly_price' => '$' . number_format($yearlyPrice, 2),
                'duration' => __('Monthly'),
                'description' => $plan->description,
                'features' => $features,
                'business' => $plan->business,
                'max_users' => $plan->max_users,
                'storage_limit' => $plan->formattedStorage(),
                'yearly_savings_percent' => $plan->yearlySavingsPercent(),
                'is_current' => $company->plan_id === $plan->id,
                'is_default' => $plan->is_default
            ];
        });
        
        return response()->json([
            'plans' => $formattedPlans,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'current_plan_id' => $company->plan_id
            ]
        ]);
    }
    
    /**
     * Upgrade company plan
     */
    public function upgradePlan(Request $request, User $company)
    {
        // Ensure this is a company type user
        if (!$company->hasRole('company')) {
            return back()->with('error', __('Invalid company record'));
        }
        
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'duration' => 'nullable|in:monthly,yearly',
        ]);

        $plan = Plan::find($validated['plan_id']);
        if (!$plan) {
            return back()->with('error', __('Plan not found'));
        }

        // The dialog's Monthly/Yearly choice; previously ignored in favour of the plan's own duration.
        $billingCycle = $validated['duration'] ?? (strtolower((string) $plan->duration) === 'yearly' ? 'yearly' : 'monthly');

        // Update company plan
        $company->plan_id = $plan->id;

        // Set plan expiry date based on the billing cycle
        if ($billingCycle === 'yearly') {
            $company->plan_expire_date = now()->addYear();
        } else {
            $company->plan_expire_date = now()->addMonth();
        }

        // Set plan is active
        $company->plan_is_active = 1;

        $company->save();

        // Record the change as an order (QA R3): nothing is charged, but Plan
        // Orders and plan stats should show admin plan changes too.
        $listPrice = $billingCycle === 'yearly' ? $plan->yearly_price : $plan->price;
        \App\Models\PlanOrder::create([
            'user_id' => $company->id,
            'plan_id' => $plan->id,
            'billing_cycle' => $billingCycle,
            'original_price' => $listPrice ?? 0,
            'discount_amount' => 0,
            'final_price' => 0,
            'payment_method' => 'admin',
            'status' => 'approved',
            'ordered_at' => now(),
            'processed_at' => now(),
            'processed_by' => auth()->id(),
            'notes' => __('Plan changed by Super Admin'),
        ]);

        return back()->with('success', __('Plan upgraded successfully'));
    }

    public function export(Request $request)
    {
        return Excel::download(new CompanyExport($request), 'companies_' . date('Y-m-d_His') . '.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|mimes:xlsx,xls,csv|max:2048']);
        $import = new CompanyImport();
        Excel::import($import, $request->file('file'));
        $imported = $import->getImportedCount();
        $skipped  = $import->getSkippedCount();
        return back()->with('success', __('Imported :imported companies. Skipped :skipped duplicates.', compact('imported', 'skipped')));
    }

    public function downloadSample()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="company_import_sample.csv"',
        ];
        $callback = function() {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['name', 'email', 'status']);
            fputcsv($file, ['Acme Corporation', 'acme@example.com', 'active']);
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }
}