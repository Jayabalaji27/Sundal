<?php

namespace App\Services\Ai;

use App\Models\AiProviderSetting;

/**
 * The Test connection button: one small request that must come back as a
 * tool call. A model that cannot call tools cannot drive the assistant.
 */
class ConnectionTester
{
    public function __construct(private readonly AiProviderFactory $providers) {}

    /** @return array{passed: bool, message: string} */
    public function test(AiProviderSetting $settings): array
    {
        $called = false;

        $request = new AiRequest(
            system: 'You are testing a connection. Always use the tool you are given.',
            messages: [['role' => 'user', 'content' => 'Call the confirm_connection tool with status "ok", then reply "done".']],
            tools: [new ToolSpec(
                'confirm_connection',
                'Confirms that the connection works.',
                ['status' => ['type' => 'string', 'required' => true, 'description' => 'Always "ok".']],
                function (array $args) use (&$called) {
                    $called = true;

                    return 'Connection confirmed.';
                },
            )],
            maxSteps: 2,
            maxTokens: 256,
        );

        try {
            $this->providers->make($settings)->run($request);
        } catch (AiProviderException $e) {
            return ['passed' => false, 'message' => $e->getMessage()];
        }

        return $called
            ? ['passed' => true, 'message' => __('Connected. The model can call tools.')]
            : ['passed' => false, 'message' => __('Connected, but the model did not call the test tool. Choose a model that supports tool calling.')];
    }
}
