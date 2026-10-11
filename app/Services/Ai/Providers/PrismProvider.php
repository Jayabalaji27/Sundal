<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiProviderException;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiResult;
use App\Services\Ai\ProviderErrors;
use App\Services\Ai\StreamsReplies;
use App\Services\Ai\ToolSpec;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Text\PendingRequest;
use Prism\Prism\Text\Step;
use Prism\Prism\Tool;
use Prism\Prism\ValueObjects\Media\Audio;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Throwable;

/**
 * Anthropic, OpenAI, Gemini and OpenRouter through Prism, using the company's own key.
 */
class PrismProvider implements StreamsReplies
{
    public function __construct(
        private readonly Provider $provider,
        private readonly string $model,
        private readonly string $apiKey,
        private readonly ?string $organization = null,
        private readonly ?int $timeout = null,
    ) {}

    public function run(AiRequest $request): AiResult
    {
        // Built first: a missing key is a clear message of its own, not a wrapped vendor error.
        $pending = $this->pending($request);
        try {
            $response = $pending->asText();
        } catch (Throwable $e) {
            throw ProviderErrors::wrap($e, $this->provider->value, $this->model);
        }

        return new AiResult(
            text: $response->text,
            inputTokens: (int) $response->steps->sum(fn (Step $step) => $step->usage->promptTokens),
            outputTokens: (int) $response->steps->sum(fn (Step $step) => $step->usage->completionTokens),
        );
    }

    public function stream(AiRequest $request, callable $onDelta): AiResult
    {
        $text = '';
        $usage = null;

        $pending = $this->pending($request);
        try {
            foreach ($pending->asStream() as $event) {
                if ($event instanceof TextDeltaEvent && $event->delta !== '') {
                    $text .= $event->delta;
                    $onDelta($event->delta);
                } elseif ($event instanceof StreamEndEvent) {
                    // Prism adds the steps' usage up; the last end event has the total.
                    $usage = $event->usage;
                }
            }
        } catch (Throwable $e) {
            throw ProviderErrors::wrap($e, $this->provider->value, $this->model);
        }

        return new AiResult(
            text: $text,
            inputTokens: (int) ($usage?->promptTokens ?? 0),
            outputTokens: (int) ($usage?->completionTokens ?? 0),
        );
    }

    private function pending(AiRequest $request): PendingRequest
    {
        return Prism::text()
            ->using($this->provider, $this->model, $this->providerConfig())
            ->withSystemPrompt($request->system)
            ->withMessages($this->messages($request))
            ->withTools(array_map(fn (ToolSpec $spec) => $this->tool($spec), $request->tools))
            ->withMaxSteps($request->maxSteps)
            ->withMaxTokens($request->maxTokens)
            ->withClientOptions(['timeout' => $this->timeout ?? config('ai_assistant.request_timeout', 60)]);
    }

    /**
     * Speech to text with the vendor's transcription endpoint (OpenAI), where
     * $this->model is the transcription model.
     *
     * @param  string|null  $language  ISO-639-1, e.g. "en"
     * @param  string|null  $prompt  words to expect (names), which helps the model spell them
     * @return array{text: string, inputTokens: int, outputTokens: int}
     */
    public function transcribe(string $audio, string $mime, ?string $language = null, ?string $prompt = null): array
    {
        $config = $this->providerConfig();

        try {
            $response = Prism::audio()
                ->using($this->provider, $this->model, $config)
                ->withInput(Audio::fromRawContent($audio, $mime))
                ->withProviderOptions(array_filter(['language' => $language, 'prompt' => $prompt]))
                ->withClientOptions(['timeout' => config('ai_assistant.request_timeout', 60)])
                ->asText();
        } catch (Throwable $e) {
            throw ProviderErrors::wrap($e, $this->provider->value, $this->model);
        }

        return [
            'text' => $response->text,
            'inputTokens' => (int) ($response->usage?->promptTokens ?? 0),
            'outputTokens' => 0,
        ];
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
                : new UserMessage($message['content'], $i === $last ? [
                    ...array_map(fn (array $doc) => Document::fromRawContent($doc['content'], $doc['mime'], $doc['name']), $request->documents),
                    ...($request->audio ? [Audio::fromRawContent($request->audio['content'], $request->audio['mime'])] : []),
                    ...array_map(fn (array $image) => Image::fromRawContent($image['content'], $image['mime']), $request->images),
                ] : []),
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
}
