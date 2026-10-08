<?php

namespace App\Http\Middleware;

use App\Services\Ai\AiAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The AI Assistant is for the workspace owner (company) and managers only.
 * Everyone else gets a 403, whatever their plan. The AI add-on itself is
 * checked by `module.access`, which runs after this.
 */
class EnsureAiAssistantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(AiAccess::role($request->user()) === null, 403, __('The AI Assistant is available to company owners and managers only.'));

        return $next($request);
    }
}
