<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Collection;

class PermissionService
{
    /**
     * Get all permissions grouped by module
     */
    public function getPermissionsByModule(): Collection
    {
        return Permission::orderBy('module')
            ->orderBy('name')
            ->get()
            ->groupBy('module');
    }
    
    /**
     * Get permissions for a specific module
     */
    public function getModulePermissions(string $module): Collection
    {
        return Permission::where('module', $module)
            ->orderBy('name')
            ->get();
    }
    
    /**
     * Check if user has permission for a specific action on a module
     */
    public function userCan(User $user, string $module, string $action): bool
    {
        // Super admin has all permissions
        if ($user->type === 'superadmin' || $user->type === 'super admin') {
            return true;
        }
        
        $permissionName = "{$module}_{$action}";
        return $user->hasPermissionTo($permissionName);
    }
    
    /**
     * Get user's CRUD permissions for a module
     */
    public function getUserModuleCrudPermissions(User $user, string $module): array
    {
        // Super admin has all permissions
        if ($user->type === 'superadmin' || $user->type === 'super admin') {
            return [
                'view_any' => true,
                'view' => true,
                'create' => true,
                'update' => true,
                'delete' => true,
            ];
        }
        
        return [
            'view_any' => $user->hasPermissionTo("{$module}_view_any"),
            'view' => $user->hasPermissionTo("{$module}_view"),
            'create' => $user->hasPermissionTo("{$module}_create"),
            'update' => $user->hasPermissionTo("{$module}_update"),
            'delete' => $user->hasPermissionTo("{$module}_delete"),
        ];
    }
    
    /**
     * Get all permissions for a user in a specific module
     */
    public function getUserModulePermissions(User $user, string $module): array
    {
        // Super admin has all permissions
        if ($user->type === 'superadmin' || $user->type === 'super admin') {
            return $this->getModulePermissions($module)->pluck('name')->toArray();
        }
        
        return $user->permissions()
            ->where('module', $module)
            ->pluck('name')
            ->toArray();
    }
    
    /**
     * Sync permissions for a user
     */
    public function syncUserPermissions(User $user, array $permissionNames): void
    {
        $permissions = Permission::whereIn('name', $permissionNames)
            ->get();
            
        $user->syncPermissions($permissions);
    }
    
    /**
     * Get permission hierarchy for UI display
     */
    public function getPermissionHierarchy(): array
    {
        $permissions = $this->getPermissionsByModule();
        $hierarchy = [];
        
        foreach ($permissions as $module => $modulePermissions) {
            $hierarchy[$module] = [
                'label' => $this->getModuleLabel($module),
                'permissions' => $modulePermissions->map(function ($permission) {
                    return [
                        'name' => $permission->name,
                        'label' => $permission->label,
                        'description' => $permission->description,
                        'category' => $this->getPermissionCategory($permission->name),
                    ];
                })->groupBy('category')->toArray()
            ];
        }
        
        return $hierarchy;
    }
    
    /**
     * Get module display label
     */
    private function getModuleLabel(string $module): string
    {
        $labels = [
            'dashboard' => 'Dashboard',
            'workspace' => 'Workspaces',
            'projects' => 'Projects',
            'tasks' => 'Tasks',
            'bugs' => 'Bug Tracking',
            'timesheet' => 'Timesheets',
            'budget' => 'Budget Management',
            'expense' => 'Expense Management',
            'expense_approval' => 'Expense Approvals',
            'invoice' => 'Invoicing',
            'media' => 'Media Library',
            'plan' => 'Plan Management',
            'report' => 'Reports & Analytics',
            'user' => 'User Management',
            'role' => 'Role Management',
            'permission' => 'Permission Management',
            'company' => 'Company Management',
            'payment' => 'Payment Processing',
            'coupon' => 'Coupon Management',
            'currency' => 'Currency Management',
            'referral' => 'Referral System',
            'landing_page' => 'Landing Page',
            'custom_page' => 'Custom Pages',
            'email_template' => 'Email Templates',
            'webhook' => 'Webhooks',
            'language' => 'Language Management',
            'business' => 'Business Management',
            'settings' => 'System Settings',
        ];
        
        return $labels[$module] ?? ucfirst(str_replace('_', ' ', $module));
    }
    
    /**
     * Categorize permissions for better organization
     */
    private function getPermissionCategory(string $permissionName): string
    {
        if (str_contains($permissionName, '_view')) {
            return 'View';
        }
        
        if (str_contains($permissionName, '_create')) {
            return 'Create';
        }
        
        if (str_contains($permissionName, '_update') || str_contains($permissionName, '_edit')) {
            return 'Update';
        }
        
        if (str_contains($permissionName, '_delete')) {
            return 'Delete';
        }
        
        if (str_contains($permissionName, '_manage')) {
            return 'Management';
        }
        
        if (str_contains($permissionName, '_assign')) {
            return 'Assignment';
        }
        
        if (str_contains($permissionName, '_approve') || str_contains($permissionName, '_reject')) {
            return 'Approval';
        }
        
        if (str_contains($permissionName, '_report') || str_contains($permissionName, '_generate')) {
            return 'Reports';
        }
        
        return 'Other';
    }
    
    /**
     * Check if permission exists and is active
     */
    public function permissionExists(string $permissionName): bool
    {
        return Permission::where('name', $permissionName)
            ->exists();
    }
    
    /**
     * Get dashboard permissions for current user
     */
    public function getDashboardPermissions(User $user): array
    {
        return [
            'dashboard_view' => $this->userCan($user, 'dashboard', 'view'),
            'dashboard_manage' => $this->userCan($user, 'dashboard', 'manage'),
        ];
    }
}