<?php

namespace App\Services\Ai;

final class AiRequest
{
    /**
     * @param  array<int, array{role: 'user'|'assistant', content: string}>  $messages
     * @param  ToolSpec[]  $tools
     * @param  array<int, array{content: string, mime: string, name: string}>  $documents  files sent with the last user message (scanned PDFs)
     * @param  array{content: string, mime: string}|null  $audio  a recording sent with the last user message (voice transcription)
     * @param  array<int, array{content: string, mime: string}>  $images  pictures sent with the last user message (image attachments)
     */
    public function __construct(
        public readonly string $system,
        public readonly array $messages,
        public readonly array $tools = [],
        public readonly int $maxSteps = 10,
        public readonly int $maxTokens = 4096,
        public readonly array $documents = [],
        public readonly ?array $audio = null,
        public readonly array $images = [],
    ) {}
}
