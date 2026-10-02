<?php
namespace App\Http\Controllers;

use App\Models\Role;
use Inertia\Inertia;
use App\Models\Permission;
use Illuminate\Support\Str;
use App\Http\Requests\RoleRequest;
use Illuminate\Support\Facades\Auth;

class RoleController extends BaseController
{
    /**
     * Constructor to apply middleware
     */
   

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $roles = Role::withPermissionCheck()->with(['permissions', 'creator'])->latest()->paginate(10);

        $permissions = $this->getFilteredPermissions();

        return Inertia::render('roles/index', [
            'roles'       => $roles,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Get permissions filtered by user role
     */
    private function getFilteredPermissions()
    {
        $user = Auth::user();
        $userType = $user->type ?? 'company';
        
        // Superadmin can see all permissions
        if ($userType === 'superadmin' || $userType === 'super admin') {
            return Permission::all()->groupBy('module');
        }
        
        // Get allowed modules for current user role
        $allowedModules = config('role-permissions.' . $userType, config('role-permissions.company'));
        
        // Filter permissions by allowed modules
        $query = Permission::whereIn('module', $allowedModules);
        
        // For company users, filter specific settings permissions
        if ($userType === 'company') {
            // When in settings module, only show email, system and brand settings permissions
            $query->where(function($q) {
                $q->where('module', '!=', 'settings')
                  ->orWhereIn('name', [
                      'manage-email-settings',
                      'manage-system-settings',
                      'manage-brand-settings'
                  ]);
            });
        }
        
        $permissions = $query->get()->groupBy('module');
        
        return $permissions;
    }

    /**
     * Validate permissions against user's allowed modules
     */
    private function validatePermissions(array $permissionNames)
    {
        $user = Auth::user();
        $userType = $user->type ?? 'company';
        
        // Superadmin can assign any permission
        if ($userType === 'superadmin' || $userType === 'super admin') {
            return $permissionNames;
        }
        
        // Get allowed modules for current user role
        $allowedModules = config('role-permissions.' . $userType, config('role-permissions.company'));
        
        // Build query to get valid permissions
        $query = Permission::whereIn('module', $allowedModules)
            ->whereIn('name', $permissionNames);
        
        // For company users, restrict settings permissions to only email, system and brand settings
        if ($userType === 'company') {
            $query->where(function($q) {
                $q->where('module', '!=', 'settings')
                  ->orWhereIn('name', [
                      'manage-email-settings',
                      'manage-system-settings',
                      'manage-brand-settings'
                  ]);
            });
        }
        
        $validPermissions = $query->pluck('name')->toArray();
        
        return $validPermissions;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RoleRequest $request)
    {
        // Validate permissions against user's allowed modules
        $validatedPermissions = $this->validatePermissions($request->permissions ?? []);
        
        // Use direct model creation to bypass Spatie's duplicate check
        $role = new Role();
        $role->label = $request->label;
        $role->name = Str::slug($request->label);
        $role->description = $request->description;
        $role->created_by = Auth::id();
        $role->guard_name = 'web';
        $role->save();

        if ($role) {
            $role->syncPermissions($validatedPermissions);

            return redirect()->route('roles.index')->with('success', __('Role created successfully with Permissions!'));
        }
        return redirect()->back()->with('error', __('Unable to create Role with permissions. Please try again!'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(RoleRequest $request, Role $role)
    {
        if ($role) {
            // Validate permissions against user's allowed modules
            $validatedPermissions = $this->validatePermissions($request->permissions ?? []);
            
            $newSlug = Str::slug($request->label);
            
            // Only update name if it's different to avoid duplicate key error
            if ($role->name !== $newSlug) {
                $role->name = $newSlug;
            }
            
            $role->label       = $request->label;
            $role->description = $request->description;

            $role->save();

            # Update the permissions
            $role->syncPermissions($validatedPermissions);

            return redirect()->route('roles.index')->with('success', __('Role updated successfully with Permissions!'));
        }
        return redirect()->back()->with('error', __('Unable to update Role with permissions. Please try again!'));

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        if ($role) {
            // Prevent deletion of system roles
            if ($role->is_system_role) {
                return redirect()->back()->with('error', __('System roles cannot be deleted!'));
            }
            
            $role->delete();

            return redirect()->route('roles.index')->with('success', __('Role deleted successfully!'));
        }
        return redirect()->back()->with('error', __('Unable to delete Role. Please try again!'));
    }
}