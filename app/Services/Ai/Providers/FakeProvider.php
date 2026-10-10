<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiProvider;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiResult;
use Closure;

/**
 * Scripted provider for tests: no network, no cost. Each script step is
 * either a tool call ['tool' => name, 'args' => [...]] or a final reply
 * ['text' => '...']. A closure step receives the request and the tool
 * results so far and returns one of those.
 */
class FakeProvider implements AiProvider
{
    /** @var AiRequest[] */
    public array $requests = [];

    /** @var array<int, array{tool: string, args: array, result: string}> */
    public array $toolResults = [];

    public function __construct(private array $script) {}

    public function run(AiRequest $request): AiResult
    {
        $this->requests[] = $request;
        $tools = collect($request->tools)->keyBy('name');

        foreach ($this->script as $i => $step) {
            if ($i >= $request->maxSteps) {
                break;
            }
            if ($step instanceof Closure) {
                $step = $step($request, $this->toolResults);
            }
            if (isset($step['text'])) {
                return new AiResult($step['text'], 10, 5);
            }

            $spec = $tools->get($step['tool']);
            $result = $spec ? $spec->call($step['args'] ?? []) : "Unknown tool {$step['tool']}.";
            $this->toolResults[] = ['tool' => $step['tool'], 'args' => $step['args'] ?? [], 'result' => $result];
        }

        return new AiResult('', 10, 5);
    }
}
