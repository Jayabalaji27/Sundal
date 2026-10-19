<?php

namespace App\Services\Ai;

use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Exceptions\TransporterException;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Exceptions\PrismProviderOverloadedException;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Prism\Prism\Exceptions\PrismRequestTooLargeException;
use Throwable;

/**
 * Turns a failed vendor call into a message the user can act on, and logs the
 * vendor's own error. Prism and openai-php report errors differently per
 * vendor (Prism's OpenRouter driver keeps the HTTP status only in the message
 * text), so the status is read from wherever it ended up, then the vendor's
 * error text decides the cases a status alone cannot.
 */
class ProviderErrors
{
    /** Prism's OpenRouter driver drops the response and names the status in its message. */
    private const OPENROUTER_STATUSES = [
        'openrouter bad request' => 400,
        'openrouter authentication error' => 401,
        'openrouter insufficient credits' => 402,
        'openrouter moderation error' => 403,
        'openrouter request timeout' => 408,
        'openrouter model error' => 502,
    ];

    /**
     * @param  array<string, string>  $messages  Per-kind wording that replaces the default.
     */
    public static function wrap(Throwable $e, string $provider, string $model, array $messages = []): AiProviderException
    {
        $kind = self::kind($e);

        // OpenRouter's free models (":free") have small fixed allowances, and Prism drops the
        // body that says which one ran out, so say what the limits are instead of "a minute".
        if ($kind === 'rate_limited' && $provider === 'openrouter' && str_ends_with($model, ':free')) {
            $messages += ['rate_limited' => __('The free AI model has used up its requests for now. OpenRouter allows 20 a minute and 50 a day on an account that never bought credits. Try again later, or ask your company owner to connect another provider.')];
        }

        AiLog::warning('ai.provider.error', [
            'user_id' => auth()->id(),
            'workspace_id' => auth()->user()?->current_workspace_id,
            'provider' => $provider,
            'model' => $model,
            'kind' => $kind,
            'error' => self::detail($e),
        ]);

        return new AiProviderException($messages[$kind] ?? self::message($kind), 0, $e);
    }

    private static function kind(Throwable $e): string
    {
        $status = self::status($e);
        $detail = strtolower(self::detail($e));

        return match (true) {
            self::has($e, PrismRequestTooLargeException::class), $status === 413 => 'too_large',
            self::has($e, PrismProviderOverloadedException::class), $status === 503, $status === 529 => 'overloaded',
            self::isConnection($e) => self::mentions($detail, ['timed out', 'curl error 28']) ? 'timeout' : 'unreachable',
            $status === 402, self::mentions($detail, ['insufficient credits', 'insufficient_quota', 'credit balance is too low', 'exceeded your current quota']) => 'credits',
            self::mentions($detail, ['support tool use', 'does not support tools', 'tool use is not supported', 'function calling is not enabled']) => 'no_tools',
            self::mentions($detail, ['moderation', 'content management policy', 'content_filter']) => 'moderation',
            $status === 401, $status === 403, self::mentions($detail, ['api key not valid', 'api_key_invalid', 'invalid api key', 'incorrect api key', 'invalid x-api-key', 'no auth credentials', 'api key expired']) => 'key',
            $status === 404, self::mentions($detail, ['not a valid model', 'model_not_found', 'is not found for api version']) => 'model',
            $status === 429, self::has($e, PrismRateLimitedException::class) => 'rate_limited',
            $status === 408 => 'timeout',
            $status !== null && $status >= 500 => 'provider_error',
            $status === 400, $status === 422 => 'bad_request',
            default => 'unknown',
        };
    }

    private static function message(string $kind): string
    {
        return match ($kind) {
            'key' => __('Your AI provider rejected the API key. Ask your company owner to check the AI settings.'),
            'model' => __('Your AI provider does not recognise the selected model. Ask your company owner to check the AI settings.'),
            'credits' => __('Your AI provider account has no credits left. Ask your company owner to add credits with the provider.'),
            'no_tools' => __('The selected model cannot call tools, which the assistant needs. Ask your company owner to choose another model.'),
            'moderation' => __("Your AI provider's content filter blocked this request."),
            'rate_limited' => __('Your AI provider is rate limiting requests, or the account is out of quota. Please try again in a minute.'),
            'too_large' => __('This request is too large for your AI provider. Try a shorter message or fewer attachments.'),
            'overloaded' => __('Your AI provider is overloaded right now. Please try again in a few minutes.'),
            'provider_error' => __('Your AI provider had an internal error. Please try again in a few minutes.'),
            'timeout' => __('Your AI provider took too long to answer. Please try again.'),
            'unreachable' => __('Your AI provider could not be reached. Please try again.'),
            'bad_request' => __('Your AI provider rejected the request. Ask your company owner to check the AI settings.'),
            default => __('Your AI provider returned an unexpected error. Please try again.'),
        };
    }

    private static function status(Throwable $e): ?int
    {
        foreach (self::chain($e) as $current) {
            if ($current instanceof RequestException) {
                return $current->response->status();
            }
            if ($current instanceof ErrorException) {
                return $current->getStatusCode();
            }
            // PrismException::providerRequestErrorWithDetails() stores the status as the code.
            if ($current instanceof PrismException && $current->getCode() >= 400 && $current->getCode() < 600) {
                return $current->getCode();
            }
        }

        $message = strtolower($e->getMessage());

        foreach (self::OPENROUTER_STATUSES as $prefix => $status) {
            if (str_contains($message, $prefix)) {
                return $status;
            }
        }

        // An error inside a 200 body, e.g. "OpenRouter Error [401]: ...".
        return preg_match('/error \[(\d{3})\]/', $message, $match) ? (int) $match[1] : null;
    }

    private static function isConnection(Throwable $e): bool
    {
        return self::has($e, ConnectionException::class)
            || self::has($e, ConnectException::class)
            || self::has($e, TransporterException::class);
    }

    /** Every message in the chain. Vendors mask keys in their errors, and keys travel in headers, not URLs. */
    private static function detail(Throwable $e): string
    {
        return collect(self::chain($e))->map(fn (Throwable $current) => $current->getMessage())->filter()->unique()->implode(' | ');
    }

    /** @return list<Throwable> */
    private static function chain(Throwable $e): array
    {
        for ($chain = []; $e; $e = $e->getPrevious()) {
            $chain[] = $e;
        }

        return $chain;
    }

    /** @param class-string $class */
    private static function has(Throwable $e, string $class): bool
    {
        return collect(self::chain($e))->contains(fn (Throwable $current) => $current instanceof $class);
    }

    /** @param list<string> $needles */
    private static function mentions(string $detail, array $needles): bool
    {
        return collect($needles)->contains(fn (string $needle) => str_contains($detail, $needle));
    }
}
