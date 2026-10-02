<?php

namespace App\Traits;

trait HasPermissionChecks
{
    /**
     * Check if the current user has the specified permission in their current
     * workspace (see User::hasWorkspacePermission()).
     */
    protected function checkPermission(string $permission): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        return $user->hasWorkspacePermission($permission);
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
        foreach ($permissions as $permission) {
            if ($this->checkPermission($permission)) {
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
        if (!auth()->user()) {
            return false;
        }

        foreach ($permissions as $permission) {
            if (!$this->checkPermission($permission)) {
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

        $role = $user->getCurrentWorkspaceRole();

        if ($user->isSuperAdmin() || $role === 'owner') {
            return ['*']; // Indicates all permissions
        }

        if ($role !== null) {
            $spatieRole = \Spatie\Permission\Models\Role::where('name', $role)->first();
            return $spatieRole
                ? $spatieRole->permissions->where('module', $module)->pluck('name')->values()->toArray()
                : [];
        }

        return $user->getAllPermissions()
            ->where('module', $module)
            ->pluck('name')
            ->values()
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
