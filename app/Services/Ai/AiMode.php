<?php

namespace App\Services\Ai;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * AI mode: the assistant in its own browser tab. Its requests carry the
 * X-AI-Mode header, and on top of the normal login and plan checks they are
 * refused when:
 *
 *  - the tab was opened for another workspace than the session's current
 *    one (switched in Sundal meanwhile)                  → 409 workspace_changed
 *  - the user did nothing in AI mode for the idle timeout → 423 locked_idle
 *  - no Sundal tab has checked in recently                → 423 locked_app_closed
 *  - AI mode was never opened in this session            → 423 ai_mode_ended
 *
 * Only the user's own actions (POST requests) count as activity; the tab's
 * automatic checks (GET) never keep AI mode alive.
 */
class AiMode
{
    public const HEADER = 'X-AI-Mode';
    public const WORKSPACE_HEADER = 'X-AI-Workspace';

    public const WORKSPACE_CHANGED = 'workspace_changed';
    public const LOCKED_IDLE = 'locked_idle';
    public const LOCKED_APP_CLOSED = 'locked_app_closed';
    public const ENDED = 'ai_mode_ended';

    private const SESSION_KEY = 'ai_mode';

    public static function isAiModeRequest(Request $request): bool
    {
        return $request->header(self::HEADER) === '1';
    }

    /** Opening the AI mode tab: remember its workspace and start the idle clock. */
    public static function start(Request $request, User $user): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'workspace_id' => (int) $user->current_workspace_id,
            'last_activity' => now()->getTimestamp(),
            'started_at' => now()->getTimestamp(),
            // Names this AI mode session in the cache, for the Sundal-open check.
            'key' => Str::random(32),
        ]);
        // AI mode is opened from Sundal, so Sundal is open right now.
        self::heartbeat($request);
    }

    /** A user action in AI mode: restart the idle clock. */
    public static function touch(Request $request): void
    {
        if ($state = $request->session()->get(self::SESSION_KEY)) {
            $request->session()->put(self::SESSION_KEY, [...$state, 'last_activity' => now()->getTimestamp()]);
        }
    }

    /**
     * A Sundal tab checking in. Both tabs share one login session, so the
     * check-in is stored under that session's AI mode key. Nothing to do
     * when AI mode was never opened in this session.
     */
    public static function heartbeat(Request $request): void
    {
        if ($key = self::heartbeatKey($request)) {
            Cache::put($key, now()->getTimestamp(), now()->addSeconds(self::tolerance() * 3));
        }
    }

    /**
     * Why an AI mode request must be refused, or null when it may go ahead.
     *
     * @return array{code: string, status: int, message: string}|null
     */
    public static function problem(Request $request, User $user): ?array
    {
        $state = $request->session()->get(self::SESSION_KEY);
        if (!$state) {
            return self::refuse(self::ENDED, 423, __('AI mode has ended. Reopen it from Sundal.'));
        }

        $workspace = (int) $request->header(self::WORKSPACE_HEADER, $state['workspace_id']);
        if ($workspace !== (int) $user->current_workspace_id || $workspace !== (int) $state['workspace_id']) {
            return self::refuse(self::WORKSPACE_CHANGED, 409, __('You switched workspace in Sundal. Reload AI mode to work in the new workspace.'));
        }

        if (now()->getTimestamp() - (int) $state['last_activity'] > self::idleSeconds($user)) {
            return self::refuse(self::LOCKED_IDLE, 423, __('AI mode was locked after :minutes minutes without activity.', ['minutes' => intdiv(self::idleSeconds($user), 60)]));
        }

        $lastSeen = (int) Cache::get((string) self::heartbeatKey($request), 0);
        if (now()->getTimestamp() - $lastSeen > self::tolerance()) {
            return self::refuse(self::LOCKED_APP_CLOSED, 423, __('Sundal is closed. Open Sundal to keep using AI mode.'));
        }

        return null;
    }

    /** Seconds left before the idle lock (for the page's countdown). */
    public static function idleSecondsLeft(Request $request, User $user): int
    {
        $state = $request->session()->get(self::SESSION_KEY);

        return $state ? max(0, self::idleSeconds($user) - (now()->getTimestamp() - (int) $state['last_activity'])) : 0;
    }

    public static function idleSeconds(User $user): int
    {
        $minutes = AiAccess::settings($user)?->idle_timeout_minutes ?: config('ai_assistant.mode.idle_timeout_minutes', 30);

        return max(1, (int) $minutes) * 60;
    }

    /** The settings the AI mode page needs for its timers. */
    public static function clientConfig(User $user): array
    {
        return [
            'workspaceId' => (int) $user->current_workspace_id,
            'idleSeconds' => self::idleSeconds($user),
            'warningSeconds' => (int) config('ai_assistant.mode.idle_warning_seconds', 120),
            'heartbeatSeconds' => (int) config('ai_assistant.mode.heartbeat_seconds', 30),
        ];
    }

    private static function tolerance(): int
    {
        return (int) config('ai_assistant.mode.heartbeat_tolerance_seconds', 120);
    }

    private static function heartbeatKey(Request $request): ?string
    {
        $key = $request->session()->get(self::SESSION_KEY . '.key');

        return $key ? 'ai-mode:app:' . $key : null;
    }

    private static function refuse(string $code, int $status, string $message): array
    {
        return ['code' => $code, 'status' => $status, 'message' => $message];
    }
}
