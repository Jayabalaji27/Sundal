<?php

namespace App\Services\Ai;

use App\Models\AiUsage;
use App\Models\User;

/**
 * One AI for every feature: plain text completions (AI Project Generator, AI
 * agents, the chatbot, the writing helper) run on the provider and model the
 * workspace connected for the AI Assistant (Settings → AI Assistant), e.g. a
 * free OpenRouter model. Usage counts against the same monthly token cap.
 */
class WorkspaceAi
{
    public function __construct(private readonly AiProviderFactory $factory) {}

    /** Whether the user's workspace has connected an AI provider. */
    public function available(User $user): bool
    {
        return AiAccess::settings($user) !== null;
    }

    /**
     * @throws WorkspaceAiUnavailable when no provider is connected or the token cap is reached
     * @throws AiProviderException when the provider call fails
     */
    public function complete(User $user, string $system, string $prompt, int $maxTokens = 2048): string
    {
        $settings = AiAccess::settings($user);
        if (!$settings) {
            throw new WorkspaceAiUnavailable(__('AI is not set up for this workspace yet. The workspace owner can connect it under Settings → AI Assistant.'));
        }
        if ($settings->monthly_token_cap && AiUsage::tokensThisMonth($settings->workspace_id) >= $settings->monthly_token_cap) {
            throw new WorkspaceAiUnavailable(__('This workspace has reached its monthly AI token cap.'));
        }

        $result = $this->factory->make($settings)->run(new AiRequest(
            system: $system,
            messages: [['role' => 'user', 'content' => $prompt]],
            maxSteps: 1,
            maxTokens: $maxTokens,
        ));

        if ($result->inputTokens || $result->outputTokens) {
            AiUsage::create([
                'workspace_id' => $settings->workspace_id, 'user_id' => $user->id,
                'provider' => $settings->provider, 'model' => $settings->model,
                'input_tokens' => $result->inputTokens, 'output_tokens' => $result->outputTokens,
            ]);
        }

        return trim($result->text);
    }
}
