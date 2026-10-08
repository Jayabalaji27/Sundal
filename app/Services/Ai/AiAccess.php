<?php

namespace App\Services\Ai;

use App\Http\Middleware\CheckModuleAccess;
use App\Models\AiProviderSetting;
use App\Models\User;

/**
 * Who may use the AI Assistant. Server-side source of truth for the route
 * middleware, the tool registry and every confirm.
 *
 * Only the workspace owner ("company") and managers get the assistant, only
 * when the workspace owner has the AI add-on (CheckModuleAccess), and
 * managers only while the owner leaves them switched on.
 */
class AiAccess
{
    public const ALLOWED = 'allowed';
    public const WRONG_ROLE = 'role';
    public const NEEDS_PLAN = 'plan';
    public const MANAGERS_OFF = 'managers_off';

    public const ROLES = ['owner', 'manager'];

    public static function role(?User $user): ?string
    {
        if (!$user || $user->isSuperAdmin()) {
            return null;
        }

        $role = $user->getCurrentWorkspaceRole();

        return in_array($role, self::ROLES, true) ? $role : null;
    }

    public static function status(?User $user): string
    {
        $role = self::role($user);

        if ($role === null) {
            return self::WRONG_ROLE;
        }

        if (CheckModuleAccess::locksModulesFor($user)) {
            return self::NEEDS_PLAN;
        }

        if ($role === 'manager' && self::settings($user)?->managers_enabled === false) {
            return self::MANAGERS_OFF;
        }

        return self::ALLOWED;
    }

    public static function canUse(?User $user): bool
    {
        return self::status($user) === self::ALLOWED;
    }

    public static function canManageSettings(?User $user): bool
    {
        return self::role($user) === 'owner';
    }

    public static function settings(User $user): ?AiProviderSetting
    {
        if (!$user->current_workspace_id) {
            return null;
        }

        return AiProviderSetting::where('workspace_id', $user->current_workspace_id)->first();
    }
}
