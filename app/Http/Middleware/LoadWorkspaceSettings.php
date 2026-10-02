<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LoadWorkspaceSettings
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        if ($user && $user->current_workspace_id && !session()->has('workspace_settings')) {
            $user->loadWorkspaceSettings();
        }
        
        return $next($request);
    }
}