<?php

namespace App\Services\Ai;

use App\Models\AiProviderSetting;
use App\Services\Ai\Providers\AzureOpenAiProvider;
use App\Services\Ai\Providers\PrismProvider;
use Prism\Prism\Enums\Provider;

/**
 * Builds the driver for a workspace's BYOA settings. Tests swap in a fake
 * with `app()->instance(AiProviderFactory::class, AiProviderFactory::fake($provider))`.
 */
class AiProviderFactory
{
    public function __construct(private readonly ?AiProvider $fake = null) {}

    public static function fake(AiProvider $provider): self
    {
        return new self($provider);
    }

    public function make(AiProviderSetting $settings): AiProvider
    {
        if ($this->fake) {
            return $this->fake;
        }

        return match ($settings->provider) {
            'anthropic' => new PrismProvider(Provider::Anthropic, $settings->model, $settings->api_key),
            'openai' => new PrismProvider(Provider::OpenAI, $settings->model, $settings->api_key, $settings->organization),
            'gemini' => new PrismProvider(Provider::Gemini, $settings->model, $settings->api_key),
            'openrouter' => new PrismProvider(Provider::OpenRouter, $settings->model, $settings->api_key),
            'azure_openai' => new AzureOpenAiProvider(
                (string) $settings->azure_endpoint,
                (string) ($settings->azure_deployment ?: $settings->model),
                $settings->api_key,
            ),
            default => throw new AiProviderException(__('This AI provider is not supported.')),
        };
    }
}
