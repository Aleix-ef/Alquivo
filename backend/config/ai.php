<?php

// Prices: standard processing, USD, verified 2026-09-25 against OpenAI's pricing.
// Integer nano-USD per token avoids floating point accumulation. Version prices before changing them.
return [
    'provider' => 'openai',
    'pricing_version' => 'openai-standard-2026-09-25',
    'routing_version' => 'alquivo-v1',
    'routing' => [
        'chat' => env('AI_CHAT_PROFILE', 'fast'),
        'legacy' => null, // Transitional: honors assistant.model, including existing deployments.
        'fast' => 'gpt-6-luna',
        'document' => 'gpt-5.6-terra',
        'analysis' => 'gpt-5.6-terra',
        'complex' => 'gpt-6-sol',
        'exceptional' => 'gpt-6-astra',
    ],
    'exceptional_enabled' => false,
    // One technical retry only; this is not semantic complexity routing. GPT-6 Astra remains opt-in.
    'fallback_profile' => env('AI_FALLBACK_PROFILE', 'complex'),
    'models' => [
        'gpt-6-luna' => ['input' => 100, 'cached_input' => 10, 'cache_write' => 125, 'output' => 500],
        'gpt-6-sol' => ['input' => 2000, 'cached_input' => 200, 'cache_write' => 2500, 'output' => 10000],
        'gpt-5.4-mini' => ['input' => 750, 'cached_input' => 75, 'cache_write' => 938, 'output' => 4500],
        'gpt-5.6-luna' => ['input' => 200, 'cached_input' => 20, 'cache_write' => 250, 'output' => 1200],
        'gpt-5.6-terra' => ['input' => 2000, 'cached_input' => 200, 'cache_write' => 2500, 'output' => 12000],
        'gpt-5.6-sol' => ['input' => 4000, 'cached_input' => 400, 'cache_write' => 5000, 'output' => 20000],
        'gpt-6-astra' => ['input' => 10000, 'cached_input' => 1000, 'cache_write' => 12500, 'output' => 50000],
    ],
    'limits' => [
        'max_rounds' => 4,
        'max_input_bytes' => 100000,
        'run_cost_nano_usd' => 100000000, // $0.10 maximum reserved estimate per turn.
        'portfolio_monthly_cost_nano_usd' => 3000000000, // $3, independent of visible usage counts.
        'global_monthly_cost_nano_usd' => (int) env('AI_GLOBAL_MONTHLY_BUDGET_USD', 100) * 1000000000,
        'metrics_retention_months' => 12,
    ],
    'actions' => [
        'enabled' => (bool) env('AI_ACTIONS_ENABLED', true),
        'plans' => ['beta', 'founder', 'trial', 'admin'],
        'proposal_ttl_minutes' => 30,
    ],
];
