<?php

return [
    'enabled' => (bool) env('ASSISTANT_ENABLED', true),
    'notice_version' => '2026-09-22',
    'retention_days' => 30,
    'max_conversations' => 20,
    'max_messages' => 100,
    'timeout_seconds' => 45,
    'max_tool_output_bytes' => 24000,
    'model' => env('OPENAI_ASSISTANT_MODEL', 'gpt-5.4-mini'),
    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    'max_output_tokens' => (int) env('OPENAI_ASSISTANT_MAX_OUTPUT_TOKENS', 900),
    'history_messages' => 20,
    'limits' => [
        'beta' => 20,
        'free' => 5,
        'founder' => 50,
        'trial' => 50,
    ],
    // A request limit controls product usage; token limits also protect beta unit economics.
    'token_limits' => [
        'beta' => ['input' => 200000, 'output' => 20000],
        'free' => ['input' => 100000, 'output' => 12000],
        'founder' => ['input' => 600000, 'output' => 80000],
        'trial' => ['input' => 600000, 'output' => 80000],
    ],
];
