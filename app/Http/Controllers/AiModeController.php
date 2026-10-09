<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * The small endpoints around the AI mode tab. The tab itself is served by
 * AiAssistantController::aiMode; the rules live in AiMode.
 */
class AiModeController extends Controller
{
    /** A Sundal tab checking in, so AI mode knows Sundal is still open. */
    public function heartbeat(Request $request): JsonResponse
    {
        AiMode::heartbeat($request);

        return response()->json(['ok' => true]);
    }

    /**
     * The AI mode tab asking whether it may continue (while locked, or on its
     * own timer). Never counts as activity.
     */
    public function status(Request $request): JsonResponse
    {
        $problem = AiMode::problem($request, $request->user());

        return response()->json([
            'code' => $problem['code'] ?? 'ok',
            'message' => $problem['message'] ?? null,
            'idle_seconds_left' => AiMode::idleSecondsLeft($request, $request->user()),
        ]);
    }

    /** "Stay signed in" on the idle warning. Too late once the lock has started. */
    public function keepAlive(Request $request): JsonResponse
    {
        $problem = AiMode::problem($request, $request->user());
        if ($problem) {
            return response()->json(['code' => $problem['code'], 'message' => $problem['message']], $problem['status']);
        }

        AiMode::touch($request);

        return response()->json(['code' => 'ok', 'idle_seconds_left' => AiMode::idleSecondsLeft($request, $request->user())]);
    }

    /**
     * Unlock after the idle timeout with the account password. The chat stays
     * as it was; only the idle clock restarts. The other locks (workspace,
     * Sundal closed) are not lifted by a password.
     */
    public function unlock(Request $request): JsonResponse
    {
        $request->validate(['password' => 'required|string']);
        $user = $request->user();

        $key = 'ai-mode-unlock:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['password' => __('Too many attempts. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)])]);
        }

        if (!Auth::guard('web')->validate(['email' => $user->email, 'password' => $request->input('password')])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['password' => __('auth.password')]);
        }

        RateLimiter::clear($key);
        $request->session()->put('auth.password_confirmed_at', time());
        AiMode::touch($request);

        $problem = AiMode::problem($request, $user);

        return response()->json(['code' => $problem['code'] ?? 'ok', 'message' => $problem['message'] ?? null]);
    }
}
