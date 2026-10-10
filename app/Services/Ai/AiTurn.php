<?php

namespace App\Services\Ai;

use App\Models\AiConversation;
use App\Models\AiProviderSetting;
use App\Models\User;

/** State for one user message while the model calls tools. */
final class AiTurn
{
    public int $calls = 0;

    /** @var int[] every ai_tool_calls row written this turn */
    public array $toolCallIds = [];

    /** @var int[] the pending confirm cards among them */
    public array $cardIds = [];

    public function __construct(
        public readonly AiConversation $conversation,
        public readonly User $user,
        public readonly string $prompt,
        public readonly AiProviderSetting $settings,
    ) {}
}
