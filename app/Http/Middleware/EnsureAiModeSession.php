<?php

namespace App\Http\Middleware;

use App\Services\Ai\AiMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every AI Assistant request needs a live AI session: opened from the AI
 * Assistant page or the AI mode tab (password checked there), still in the
 * same workspace, and not idle. Requests from the AI mode tab (X-AI-Mode: 1)
 * also need a Sundal tab open. The user's own actions restart the idle clock.
 */
class EnsureAiModeSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return $next($request);
        }

        if ($problem = AiMode::problem($request, $request->user(), AiMode::isAiModeRequest($request))) {
            return response()->json(['code' => $problem['code'], 'message' => $problem['message']], $problem['status']);
        }

        // Only the user's actions count as activity, never the tab's polling.
        if (!$request->isMethodSafe()) {
            AiMode::touch($request);
        }

        return $next($request);
    }
}
