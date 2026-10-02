<?php

namespace App\Models\Concerns;

/**
 * Phase 1.4 — single source of truth for workspace tenant scoping.
 *
 * Scopes every query on the model to the authenticated user's current workspace,
 * and stamps workspace_id on create. Queries made outside an authenticated
 * request (console commands, queue jobs) are left unscoped since there is no
 * workspace context to scope to. An authenticated user with no current
 * workspace (e.g. Super Admin) sees nothing by default — use
 * withoutGlobalScope('workspace') explicitly for legitimate cross-workspace
 * reporting, with a comment explaining why.
 */
trait BelongsToWorkspace
{
    protected static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function ($query) {
            if (! auth()->check()) {
                return;
            }

            $workspaceId = auth()->user()->current_workspace_id;

            if ($workspaceId === null) {
                $query->whereRaw('1 = 0');
                return;
            }

            $query->where($query->getModel()->getTable() . '.workspace_id', $workspaceId);
        });

        static::creating(function ($model) {
            if (empty($model->workspace_id) && auth()->check()) {
                $model->workspace_id = auth()->user()->current_workspace_id;
            }
        });
    }
}
