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
        
        // Apply status filter
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        // Apply date filters
        if ($request->has('start_date') && !empty($request->start_date)) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        
        if ($request->has('end_date') && !empty($request->end_date)) {
            $query->whereDate('created_at', '<=', $request->end_date);
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
            ];
        });
        
        // Get plans for dropdown
        $plans = Plan::all(['id', 'name']);
        
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
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'nullable|string|min:8',
            'status' => 'required|in:active,inactive',
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
        
        // Assign default plan
        $defaultPlan = Plan::where('is_default', true)->first();
        if ($defaultPlan) {
            $company->plan_id = $defaultPlan->id;
            
            // Set plan expiry date based on plan duration
            if ($defaultPlan->duration === 'yearly') {
                $company->plan_expire_date = now()->addYear();
            } else {
                $company->plan_expire_date = now()->addMonth();
            }
            
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
            'email' => 'required|string|email|max:255|unique:users,email,' . $company->id,
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
                'storage_limit' => $plan->storage_limit . ' ' . __('GB'),
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
        ]);
        
        $plan = Plan::find($validated['plan_id']);
        if (!$plan) {
            return back()->with('error', __('Plan not found'));
        }
        
        // Update company plan
        $company->plan_id = $plan->id;
        
        // Set plan expiry date based on plan duration
        if ($plan->duration === 'yearly') {
            $company->plan_expire_date = now()->addYear();
        } else {
            $company->plan_expire_date = now()->addMonth();
        }
        
        // Set plan is active
        $company->plan_is_active = 1;
        
        $company->save();
        
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