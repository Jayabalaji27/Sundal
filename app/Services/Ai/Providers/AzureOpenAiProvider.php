<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiProvider;
use App\Services\Ai\AiProviderException;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiResult;
use App\Services\Ai\ToolSpec;
use OpenAI;
use OpenAI\Contracts\ClientContract;
use OpenAI\Exceptions\ErrorException;
use Throwable;

/**
 * Azure OpenAI on the company's own Azure resource. Prism has no Azure
 * driver, so this runs the tool loop itself over openai-php/client.
 */
class AzureOpenAiProvider implements AiProvider
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $deployment,
        private readonly string $apiKey,
        private ?ClientContract $client = null,
    ) {}

    public function run(AiRequest $request): AiResult
    {
        $tools = collect($request->tools)->keyBy('name');
        $messages = [['role' => 'system', 'content' => $request->system], ...$request->messages];
        $inputTokens = 0;
        $outputTokens = 0;
        $text = '';

        for ($step = 0; $step < $request->maxSteps; $step++) {
            try {
                $response = $this->client()->chat()->create(array_filter([
                    'messages' => $messages,
                    'tools' => $tools->isEmpty() ? null : $tools->map(fn (ToolSpec $spec) => [
                        'type' => 'function',
                        'function' => [
                            'name' => $spec->name,
                            'description' => $spec->description,
                            'parameters' => $spec->jsonSchema(),
                        ],
                    ])->values()->all(),
                    'max_tokens' => $request->maxTokens,
                ]));
            } catch (Throwable $e) {
                throw new AiProviderException($this->friendlyError($e), 0, $e);
            }

            $inputTokens += $response->usage?->promptTokens ?? 0;
            $outputTokens += $response->usage?->completionTokens ?? 0;

            $message = $response->choices[0]->message;
            $text = $message->content ?? '';

            if (empty($message->toolCalls)) {
                break;
            }

            $messages[] = [
                'role' => 'assistant',
                'content' => $message->content,
                'tool_calls' => array_map(fn ($call) => $call->toArray(), $message->toolCalls),
            ];

            foreach ($message->toolCalls as $call) {
                $spec = $tools->get($call->function->name);
                $arguments = json_decode($call->function->arguments ?: '{}', true) ?: [];
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call->id,
                    'content' => $spec ? $spec->call($arguments) : 'Unknown tool.',
                ];
            }
        }

        return new AiResult($text, $inputTokens, $outputTokens);
    }

    private function client(): ClientContract
    {
        $host = preg_replace('#^https://#i', '', rtrim($this->endpoint, '/'));

        return $this->client ??= OpenAI::factory()
            ->withBaseUri("{$host}/openai/deployments/{$this->deployment}")
            ->withHttpHeader('api-key', $this->apiKey)
            ->withQueryParam('api-version', (string) config('ai_assistant.providers.azure_openai.api_version'))
            ->make();
    }

    private function friendlyError(Throwable $e): string
    {
        $status = $e instanceof ErrorException ? $e->getStatusCode() : null;

        return match (true) {
            $status === 401, $status === 403 => __('Azure OpenAI rejected the API key. Ask your company owner to check the AI settings.'),
            $status === 404 => __('Azure OpenAI could not find that deployment. Ask your company owner to check the AI settings.'),
            $status === 429 => __('Your Azure OpenAI deployment is out of quota or rate limited.'),
            default => __('Azure OpenAI could not be reached. Please try again.'),
        };
    }
}
