<?php

namespace App\Services\Ai;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The AI session. Opening the AI Assistant (its page, or the AI mode tab,
 * both using the current login) starts it; every assistant request
 * then needs it, on top of the normal login and plan checks. Refused when:
 *
 *  - the assistant was opened in another workspace than the session's
 *    current one (switched in Sundal meanwhile)           → 409 workspace_changed
 *  - the user did nothing in the assistant for the idle timeout → 423 locked_idle
 *  - it was never opened in this session                   → 423 ai_mode_ended
 *  - AI mode tab only (X-AI-Mode header): no Sundal tab has
 *    checked in recently                                   → 423 locked_app_closed
 *
 * Only the user's own actions (POST requests) count as activity; automatic
 * checks (GET) never keep the session alive.
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

    /** Opening the assistant (page or AI mode tab): remember its workspace and start the idle clock. */
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
    public static function problem(Request $request, User $user, bool $fromAiModeTab = true): ?array
    {
        $state = $request->session()->get(self::SESSION_KEY);
        if (!$state) {
            return self::refuse(self::ENDED, 423, __('Your AI Assistant session has ended. Reload the page to continue.'));
        }

        $workspace = (int) $request->header(self::WORKSPACE_HEADER, $state['workspace_id']);
        if ($workspace !== (int) $user->current_workspace_id || $workspace !== (int) $state['workspace_id']) {
            return self::refuse(self::WORKSPACE_CHANGED, 409, __('You switched workspace in Sundal. Reload the AI Assistant to work in the new workspace.'));
        }

        if (now()->getTimestamp() - (int) $state['last_activity'] > self::idleSeconds($user)) {
            return self::refuse(self::LOCKED_IDLE, 423, __('The AI Assistant was paused after :minutes minutes without activity. Reload the page to continue.', ['minutes' => intdiv(self::idleSeconds($user), 60)]));
        }

        // The AI mode tab only works while a Sundal tab is open (the normal page is Sundal).
        $lastSeen = (int) Cache::get((string) self::heartbeatKey($request), 0);
        if ($fromAiModeTab && now()->getTimestamp() - $lastSeen > self::tolerance()) {
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
