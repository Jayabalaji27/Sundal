<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BlockSuperAdminWorkspaceAccess
{
    /**
     * Super Admin operates the SaaS platform (companies, plans, billing), not
     * individual tenant workspaces — it has every Spatie permission by design
     * (see RoleSeeder), which let it fall through permission middleware straight
     * into workspace-scoped controllers with no current_workspace_id, crashing
     * some (500) and 404/200-ing others. Reaching real workspace data is meant
     * to go through Impersonate (ImpersonateController swaps the authenticated
     * user to the target company, so this check no longer applies).
     */
    public function handle(Request $request, Closure $next)
    {
        abort_if($request->user()?->type === 'superadmin', 403);

        return $next($request);
    }
}
