<?php

/**
 * Explicit, synthetic-only smoke probe. Never run against real customer data.
 * Usage: ALQUIVO_LIVE_AI_PROBE=YES php tests/Support/live-ai-probe.php
 */

use App\Domain\Assistant\Contracts\AIProviderInterface;
use App\Domain\Assistant\Evaluation\EvaluationRunner;
use App\Domain\Assistant\Evaluation\FixtureSuite;
use App\Domain\Assistant\Providers\OpenAIProvider;
use App\Domain\Assistant\Services\AIModelRouter;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (getenv('ALQUIVO_LIVE_AI_PROBE') !== 'YES' || ! app()->environment(['local', 'testing'])) {
    fwrite(STDERR, "Live probe disabled. Requires explicit opt-in and local/testing environment.\n");
    exit(2);
}
if (! filled(config('services.openai.key'))) {
    fwrite(STDERR, "OpenAI key missing.\n");
    exit(2);
}

// Pessimistic preflight: count each request byte as an input token, all output at full price.
// No paid tools are enabled. A missing usage report keeps the full reservation charged.
final class BudgetedProbeProvider implements AIProviderInterface
{
    public int $spentNano = 0;
    public int $calls = 0;

    public function __construct(private readonly AIProviderInterface $inner, private readonly int $ceilingNano) {}

    public function generate(array $request, float $timeout): array
    {
        $rates = config('ai.models.'.$request['model']);
        if (! is_array($rates) || ! isset($rates['input'], $rates['output'])) {
            throw new RuntimeException('Unknown model pricing.');
        }
        $reserved = strlen(json_encode($request, JSON_THROW_ON_ERROR)) * (int) $rates['input']
            + (int) $request['max_output_tokens'] * (int) $rates['output'];
        if ($this->spentNano + $reserved > $this->ceilingNano) {
            throw new RuntimeException('Probe budget exceeded before request.');
        }
        $this->spentNano += $reserved;
        $this->calls++;
        $response = $this->inner->generate($request, $timeout);
        $usage = $response['usage'] ?? null;
        if (is_array($usage) && isset($usage['input_tokens'], $usage['output_tokens'])) {
            $actual = (int) $usage['input_tokens'] * (int) $rates['input']
                + (int) $usage['output_tokens'] * (int) $rates['output'];
            // Deliberately ignore cache discounts and retain the reservation if usage is anomalous.
            $this->spentNano += min($actual - $reserved, 0);
        }

        return $response;
    }
}

$provider = new BudgetedProbeProvider(app(OpenAIProvider::class), 250_000_000); // USD 0.25.
$cases = collect(app(FixtureSuite::class)->cases())->keyBy('id');
$ids = ['portfolio_overview', 'monthly_expenses', 'resolve_property', 'pending_rents', 'ambiguous_property', 'missing_valuation', 'support_required', 'out_of_scope', 'untrusted_property_name'];
$results = [];
$runner = app(EvaluationRunner::class);
$router = app(AIModelRouter::class);
foreach (['fast', 'complex'] as $profile) {
    $route = $router->route($profile);
    $route['max_output_tokens'] = min(900, $route['max_output_tokens']);
    foreach ($ids as $id) {
        $result = $runner->run($cases[$id], $provider, $route);
        $results[] = [
            'case' => $id,
            'model' => $route['model'],
            'passed' => $result['grade']['passed'],
            'failed_checks' => array_keys(array_filter($result['grade']['checks'], fn ($ok) => ! $ok)),
            'error_type' => $result['error_type'],
            'trace' => $result['trace'],
            'reply' => $result['reply'],
            'calls' => $result['provider_calls'],
            'usage' => $result['usage'],
            'cost_usd' => $result['estimated_cost_usd'],
            'latency_ms' => $result['latency_ms'],
        ];
        if ($provider->spentNano >= 225_000_000) {
            break 2;
        }
    }
}

echo json_encode([
    'mode' => 'live_synthetic_smoke',
    'max_budget_usd' => 0.25,
    'conservative_spend_upper_bound_usd' => round($provider->spentNano / 1_000_000_000, 6),
    'provider_calls_attempted' => $provider->calls,
    'cases' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
