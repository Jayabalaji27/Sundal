<?php

namespace App\Traits;

trait HasPermissionChecks
{
    /**
     * Check if the current user has the specified permission
     */
    protected function checkPermission(string $permission): bool
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }
        
        // Super admin has all permissions
        if ($user->type === 'superadmin' || $user->type === 'super admin') {
            return true;
        }
        
        // Company users have broad access
        if ($user->type === 'company') {
            return true;
        }
        
        return $user->hasPermissionTo($permission);
    }
    
    /**
     * Check permission and abort if not authorized
     */
    protected function authorizePermission(string $permission): void
    {
        if (!$this->checkPermission($permission)) {
            abort(403, 'You do not have permission to perform this action.');
        }
    }
    
    /**
     * Check multiple permissions (user must have at least one)
     */
    protected function checkAnyPermission(array $permissions): bool
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }
        
        // Super admin has all permissions
        if ($user->type === 'superadmin' || $user->type === 'super admin') {
            return true;
        }
        
        // Company users have broad access
        if ($user->type === 'company') {
            return true;
        }
        
        foreach ($permissions as $permission) {
            if ($user->hasPermissionTo($permission)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check multiple permissions (user must have all)
     */
    protected function checkAllPermissions(array $permissions): bool
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }
        
        // Super admin has all permissions
        if ($user->type === 'superadmin' || $user->type === 'super admin') {
            return true;
        }
        
        // Company users have broad access
        if ($user->type === 'company') {
            return true;
        }
        
        foreach ($permissions as $permission) {
            if (!$user->hasPermissionTo($permission)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get user permissions for a specific module
     */
    protected function getModulePermissions(string $module): array
    {
        $user = auth()->user();
        
        if (!$user) {
            return [];
        }
        
        // Super admin has all permissions
        if ($user->type === 'superadmin' || $user->type === 'super admin') {
            return ['*']; // Indicates all permissions
        }
        
        // Company users have broad access
        if ($user->type === 'company') {
            return ['*']; // Indicates all permissions
        }
        
        return $user->permissions()
            ->where('module', $module)
            ->pluck('name')
            ->toArray();
    }
    
    /**
     * Check if user can perform CRUD operations on a module
     */
    protected function getModuleCrudPermissions(string $module): array
    {
        return [
            'view_any' => $this->checkPermission("{$module}_view_any"),
            'view' => $this->checkPermission("{$module}_view"),
            'create' => $this->checkPermission("{$module}_create"),
            'update' => $this->checkPermission("{$module}_update"),
            'delete' => $this->checkPermission("{$module}_delete"),
        ];
    }
}