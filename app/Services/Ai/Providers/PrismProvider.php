<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiProvider;
use App\Services\Ai\AiProviderException;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiResult;
use App\Services\Ai\ToolSpec;
use Illuminate\Http\Client\RequestException;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Text\Step;
use Prism\Prism\Tool;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Throwable;

/**
 * Anthropic, OpenAI and Gemini through Prism, using the company's own key.
 */
class PrismProvider implements AiProvider
{
    public function __construct(
        private readonly Provider $provider,
        private readonly string $model,
        private readonly string $apiKey,
        private readonly ?string $organization = null,
    ) {}

    public function run(AiRequest $request): AiResult
    {
        $config = $this->providerConfig();

        try {
            $response = Prism::text()
                ->using($this->provider, $this->model, $config)
                ->withSystemPrompt($request->system)
                ->withMessages($this->messages($request))
                ->withTools(array_map(fn (ToolSpec $spec) => $this->tool($spec), $request->tools))
                ->withMaxSteps($request->maxSteps)
                ->withMaxTokens($request->maxTokens)
                ->withClientOptions(['timeout' => config('ai_assistant.request_timeout', 60)])
                ->asText();
        } catch (Throwable $e) {
            throw new AiProviderException($this->friendlyError($e), 0, $e);
        }

        return new AiResult(
            text: $response->text,
            inputTokens: (int) $response->steps->sum(fn (Step $step) => $step->usage->promptTokens),
            outputTokens: (int) $response->steps->sum(fn (Step $step) => $step->usage->completionTokens),
        );
    }

    private function providerConfig(): array
    {
        // Prism merges this over config/prism.php, which reads server keys such as
        // ANTHROPIC_API_KEY from .env. Never let an empty company key fall back to them.
        if (trim($this->apiKey) === '') {
            throw new AiProviderException(__('No API key is saved for this AI provider.'));
        }

        $config = ['api_key' => $this->apiKey];

        if ($this->provider === Provider::OpenAI && $this->organization) {
            $config['organization'] = $this->organization;
        }

        if ($this->provider === Provider::OpenRouter) {
            // Shown in the company's OpenRouter activity log.
            $config['site'] = ['x_title' => 'Sundal AI Assistant', 'http_referer' => config('app.url')];
        }

        return $config;
    }

    private function messages(AiRequest $request): array
    {
        $last = array_key_last($request->messages);

        return array_map(
            fn (array $message, int $i) => $message['role'] === 'assistant'
                ? new AssistantMessage($message['content'])
                : new UserMessage($message['content'], $i === $last ? array_map(
                    fn (array $doc) => Document::fromRawContent($doc['content'], $doc['mime'], $doc['name']),
                    $request->documents,
                ) : []),
            $request->messages,
            array_keys($request->messages),
        );
    }

    private function tool(ToolSpec $spec): Tool
    {
        $tool = (new Tool)
            ->as($spec->name)
            ->for($spec->description)
            // Prism passes arguments as named parameters.
            ->using(fn (...$arguments) => $spec->call($arguments));

        foreach ($spec->parameters as $name => $param) {
            $required = (bool) ($param['required'] ?? false);
            $description = $param['description'] ?? '';

            match ($param['type'] ?? 'string') {
                'number' => $tool->withNumberParameter($name, $description, $required),
                'boolean' => $tool->withBooleanParameter($name, $description, $required),
                'enum' => $tool->withEnumParameter($name, $description, array_values($param['options'] ?? []), $required),
                default => $tool->withStringParameter($name, $description, $required),
            };
        }

        return $tool;
    }

    private function friendlyError(Throwable $e): string
    {
        if ($e instanceof PrismRateLimitedException) {
            return __('Your AI provider is rate limiting requests. Please try again in a minute.');
        }

        $status = $this->statusCode($e);
        $detail = strtolower($e->getMessage());

        return match (true) {
            str_contains($detail, 'insufficient credits'), str_contains($detail, 'insufficient_quota'), $status === 402
                => __('Your AI provider account has no credits left. Ask your company owner to add credits with the provider.'),
            $status === 401, $status === 403 => __('Your AI provider rejected the API key. Ask your company owner to check the AI settings.'),
            $status === 404 => __('Your AI provider does not recognise the selected model. Ask your company owner to check the AI settings.'),
            $status === 429 => __('Your AI provider account is out of quota or rate limited.'),
            default => __('Your AI provider could not be reached. Please try again.'),
        };
    }

    private function statusCode(Throwable $e): ?int
    {
        for ($current = $e; $current; $current = $current->getPrevious()) {
            if ($current instanceof RequestException) {
                return $current->response->status();
            }
        }

        return null;
    }
}
