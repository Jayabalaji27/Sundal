<?php

namespace App\Http\Middleware;

use App\Services\Ai\AiMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For requests from the AI mode tab (X-AI-Mode: 1): refuse them when that
 * tab is locked (idle, Sundal closed) or bound to another workspace, and let
 * the user's own actions restart the idle clock. Requests from the normal
 * AI Assistant page carry no header and pass straight through.
 */
class EnsureAiModeSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!AiMode::isAiModeRequest($request) || !$request->user()) {
            return $next($request);
        }

        if ($problem = AiMode::problem($request, $request->user())) {
            return response()->json(['code' => $problem['code'], 'message' => $problem['message']], $problem['status']);
        }

        // Only the user's actions count as activity, never the tab's polling.
        if (!$request->isMethodSafe()) {
            AiMode::touch($request);
        }

        return $next($request);
    }
}
