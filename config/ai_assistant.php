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
        // A cloud router to many vendors' models, on the company's own
        // OpenRouter key. Model ids are "vendor/model", e.g. openai/gpt-4o.
        'openrouter' => [
            'label' => 'OpenRouter',
            'default_model' => 'openai/gpt-4o',
            'models' => [
                'openai/gpt-4o' => ['label' => 'OpenAI GPT-4o'],
            ],
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

    /*
    | AI mode: the assistant in its own browser tab, opened with the "AI mode"
    | switch in the Sundal header.
    */
    'mode' => [
        // The assistant pauses after this long without the user doing anything in it;
        // "Continue" resumes it with the current login.
        // The company owner picks one of the options; the first default is used otherwise.
        'idle_timeout_minutes' => 30,
        'idle_timeout_options' => [15, 30, 60],
        // Warning shown this long before the idle lock.
        'idle_warning_seconds' => 120,
        // AI mode only works while a Sundal tab is open. Sundal tabs check in every
        // `heartbeat_seconds`; AI mode locks when none did for `heartbeat_tolerance_seconds`.
        // Generous because browsers slow timers in background tabs to about once a minute.
        'heartbeat_seconds' => 30,
        'heartbeat_tolerance_seconds' => 120,
    ],

    // PHP time limit for a reply answered during the web request (not queued).
    // Must be above request_timeout so a slow provider ends as a normal error.
    'sync_time_limit' => env('AI_ASSISTANT_SYNC_TIME_LIMIT', 300),

    // Run AI calls on the queue so a 2-20 s reply does not hold a web worker;
    // the page polls for the answer. Needs a running queue worker, so it is
    // off by default (shared hosting often has none).
    'queue' => env('AI_ASSISTANT_QUEUE', false),

    // Files given to the assistant (+ button). Sundal reads them itself; the
    // model only gets summaries, sections and rows it asks for.
    'attachments' => [
        'disk' => env('AI_ASSISTANT_ATTACHMENT_DISK', 'local'),
        'max_size_mb' => 10,
        'max_per_message' => 5,
        'extensions' => ['pdf', 'xlsx', 'xls', 'csv', 'docx', 'txt', 'md'],
        // Rows kept per sheet (an import takes at most max_import_rows of them).
        'max_sheet_rows' => 1000,
        'max_import_rows' => 500,
        // Pages and characters read from a document.
        'max_pages' => 300,
        'max_chars' => 400000,
        // A document analysis (BRD → tasks) reads at most this much text (about
        // 60 pages), in chunks of analysis_chunk_chars, one AI call per chunk.
        'max_analysis_chars' => 180000,
        'analysis_chunk_chars' => 24000,
        'max_plan_tasks' => 300,
        'max_plan_milestones' => 30,
        // Text per section handed to the model at once.
        'section_chars' => 6000,
        // Zip-based files (xlsx, docx): refuse ones that expand past this.
        'max_unzipped_mb' => 100,
        // Files uploaded but never sent are removed after this many hours.
        'orphan_hours' => 24,
        // Google Drive picker: the Google Cloud project's OAuth client id and API key.
        'google_drive' => [
            'client_id' => env('AI_ASSISTANT_GOOGLE_CLIENT_ID'),
            'api_key' => env('AI_ASSISTANT_GOOGLE_API_KEY'),
            'app_id' => env('AI_ASSISTANT_GOOGLE_APP_ID'),
        ],
    ],
];
