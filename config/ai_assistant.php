<?php

/*
|--------------------------------------------------------------------------
| AI Assistant (BYOA)
|--------------------------------------------------------------------------
|
| The assistant only ever runs on the company's own AI vendor account
| (bring your own key). There is no Sundal-managed key and no self-hosted
| or local model support: every provider below is a fixed cloud endpoint.
|
| `models` is the tested model list an owner can pick from. A model marked
| 'read_only' => true only gets read tools until it passes the write evals.
|
*/

return [

    'providers' => [
        'anthropic' => [
            'label' => 'Anthropic Claude',
            'default_model' => 'claude-sonnet-5-5',
            'models' => [
                'claude-sonnet-5-5' => ['label' => 'Claude Sonnet 5.5 (recommended)'],
                'claude-opus-5-5' => ['label' => 'Claude Opus 5.5'],
                'claude-haiku-4-5-20251001' => ['label' => 'Claude Haiku 4.5', 'read_only' => true],
            ],
        ],
        'openai' => [
            'label' => 'OpenAI',
            'default_model' => null,
            'models' => [],
        ],
        'gemini' => [
            'label' => 'Google Gemini',
            'default_model' => null,
            'models' => [],
        ],
        'azure_openai' => [
            'label' => 'Azure OpenAI',
            'default_model' => null,
            'models' => [],
            // Azure endpoints must be the company's own Azure OpenAI resource.
            // Anything else (internal IPs, other hosts) is rejected.
            'endpoint_pattern' => '/^https:\/\/[a-z0-9][a-z0-9-]{1,62}\.openai\.azure\.com\/?$/i',
            'api_version' => env('AI_ASSISTANT_AZURE_API_VERSION', '2024-10-21'),
        ],
    ],

    // OpenAI, Gemini and Azure model lists are filled from the Phase 1 eval
    // results. Until then owners type a model name and the Test connection
    // button decides whether it can call tools.
    'allow_custom_model' => env('AI_ASSISTANT_ALLOW_CUSTOM_MODEL', true),

    // Hard limit on tool calls the model may make for one user message.
    'max_tool_calls_per_message' => 10,

    // How many earlier messages are sent back to the model as context.
    'history_messages' => 20,

    // Messages a single user may send per minute.
    'messages_per_minute' => 20,

    // A pending confirm card expires after this many minutes.
    'confirmation_ttl_minutes' => 30,

    // Default chat retention; the owner can pick one of `retention_options`.
    'retention_days' => 90,
    'retention_options' => [30, 90, 180, 365],

    'request_timeout' => env('AI_ASSISTANT_REQUEST_TIMEOUT', 60),
];
