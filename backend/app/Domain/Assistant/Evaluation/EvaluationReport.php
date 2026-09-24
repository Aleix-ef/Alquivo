<?php

namespace App\Domain\Assistant\Evaluation;

use App\Domain\Assistant\Services\AICostCalculator;
use InvalidArgumentException;

final class EvaluationReport
{
    public function summarize(array $results): array
    {
        $checks = [];
        foreach ($results as $result) {
            foreach ($result['grade']['checks'] as $name => $passed) {
                $checks[$name] = ($checks[$name] ?? 0) + (int) $passed;
            }
        }
        $latencies = array_column($results, 'latency_ms');
        sort($latencies);
        $costs = array_column($results, 'estimated_cost_nano_usd');
        $costNano = array_sum($costs);

        return [
            'cases' => count($results),
            'passed' => count(array_filter($results, fn (array $result) => $result['grade']['passed'])),
            'checks_passed' => $checks,
            'input_tokens' => array_sum(array_column(array_column($results, 'usage'), 'input_tokens')),
            'output_tokens' => array_sum(array_column(array_column($results, 'usage'), 'output_tokens')),
            'provider_calls' => array_sum(array_column($results, 'provider_calls')),
            'estimated_cost_nano_usd' => in_array(null, $costs, true) ? null : $costNano,
            'estimated_cost_usd' => in_array(null, $costs, true) ? null : AICostCalculator::dollars($costNano),
            'latency_p50_ms' => $this->percentile($latencies, 0.5),
            'latency_p95_ms' => $this->percentile($latencies, 0.95),
        ];
    }

    public function compare(array $baseline, array $candidate): array
    {
        if (array_column($baseline, 'case_id') !== array_column($candidate, 'case_id')) {
            throw new InvalidArgumentException('Solo se pueden comparar los mismos casos en el mismo orden.');
        }
        $regressions = [];
        $improvements = [];
        foreach ($baseline as $index => $result) {
            if ($result['grade']['passed'] && ! $candidate[$index]['grade']['passed']) {
                $regressions[] = $result['case_id'];
            }
            if (! $result['grade']['passed'] && $candidate[$index]['grade']['passed']) {
                $improvements[] = $result['case_id'];
            }
        }

        return ['regressions' => $regressions, 'improvements' => $improvements];
    }

    private function percentile(array $values, float $percentile): ?float
    {
        return $values === [] ? null : $values[max(0, (int) ceil(count($values) * $percentile) - 1)];
    }
}
