<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     * "Continue" after the idle pause: resumes with the current login, no
     * password. The chat stays as it was; only the idle clock restarts. The
     * other locks (workspace changed, Sundal closed) are not lifted by this.
     */
    public function unlock(Request $request): JsonResponse
    {
        AiMode::touch($request);
        $problem = AiMode::problem($request, $request->user());

        return response()->json(['code' => $problem['code'] ?? 'ok', 'message' => $problem['message'] ?? null]);
    }
}
