<?php

namespace App\Services\Ai;

final class AiRequest
{
    /**
     * @param  array<int, array{role: 'user'|'assistant', content: string}>  $messages
     * @param  ToolSpec[]  $tools
     */
    public function __construct(
        public readonly string $system,
        public readonly array $messages,
        public readonly array $tools = [],
        public readonly int $maxSteps = 10,
        public readonly int $maxTokens = 4096,
    ) {}
}
