<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One line per call to an outside service, in
 * storage/logs/integrations-YYYY-MM-DD.log: the service and action
 * ("zoom.meeting.create"), the workspace and user, whether it worked, how
 * long it took and, when it failed, why. Bot tokens, webhook paths and access
 * tokens are masked before anything is written. Each line also carries the
 * request_id of the request it came from (App\Http\Middleware\AssignRequestId).
 */
final class IntegrationLog
{
    public static function ok(string $service, string $action, ?int $started = null, array $context = []): void
    {
        self::write('info', $service, $action, $started, $context);
    }

    public static function failed(string $service, string $action, string|Throwable|null $error, ?int $started = null, array $context = []): void
    {
        $message = $error instanceof Throwable ? $error->getMessage() : (string) $error;
        self::write('warning', $service, $action, $started, ['error' => mb_substr(self::redact($message), 0, 500) ?: 'unknown'] + $context);
    }

    /** Masks the secrets outside services put in URLs, headers and error bodies. */
    public static function redact(string $text): string
    {
        return (string) preg_replace(
            [
                '#bot\d+:[A-Za-z0-9_-]+#',
                '#(hooks\.slack\.com/services)/[^\s"\'<>]+#i',
                '#\b(access_token|refresh_token|client_secret|api_key|key|token|signature)=[^&\s"\'<>]+#i',
                '#\bBearer\s+[A-Za-z0-9._~+/=-]+#i',
                '#"(access_token|refresh_token|client_secret|private_key|id_token)"\s*:\s*"[^"]*"#i',
            ],
            ['bot***', '$1/***', '$1=***', 'Bearer ***', '"$1":"***"'],
            $text,
        );
    }

    /** "jaya@example.com" -> "ja***@example.com": enough to tell recipients apart. */
    public static function maskEmail(?string $email): ?string
    {
        if (!$email || !str_contains($email, '@')) {
            return $email;
        }
        [$name, $domain] = explode('@', $email, 2);

        return mb_substr($name, 0, 2) . '***@' . $domain;
    }

    /** Only the host of a customer's URL: its path or query may hold a secret. */
    public static function host(?string $url): ?string
    {
        return $url ? (parse_url($url, PHP_URL_HOST) ?: null) : null;
    }

    private static function write(string $level, string $service, string $action, ?int $started, array $context): void
    {
        try {
            $user = auth()->user();
            Log::channel('integrations')->log($level, "{$service}.{$action}", array_filter([
                'ok' => $level === 'info',
                'workspace_id' => $user?->current_workspace_id,
                'user_id' => $user?->id,
                'ms' => $started ? (int) round((hrtime(true) - $started) / 1e6) : null,
                ...$context,
            ], fn ($value) => $value !== null));
        } catch (Throwable) {
            // Logging must never break the call it records.
        }
    }
}
