<?php

namespace App\Domain\Assistant\Services;

final class AICostCalculator
{
    public function calculate(string $model, array $usage): array
    {
        return $this->atRates($usage, config('ai.models')[$model] ?? null, config('ai.pricing_version'));
    }

    public function atRates(array $usage, ?array $rates, string $version): array
    {
        $input = max(0, (int) ($usage['input_tokens'] ?? 0));
        $output = max(0, (int) ($usage['output_tokens'] ?? 0));
        $cached = min($input, max(0, (int) data_get($usage, 'input_tokens_details.cached_tokens', 0)));
        $written = min($input - $cached, max(0, (int) data_get($usage, 'input_tokens_details.cache_write_tokens', 0)));
        $cost = $rates === null ? null : ($input - $cached - $written) * $rates['input']
            + $cached * $rates['cached_input'] + $written * $rates['cache_write'] + $output * $rates['output'];

        return [
            'input_tokens' => $input, 'output_tokens' => $output,
            'cached_input_tokens' => $cached, 'cache_write_tokens' => $written,
            'estimated_cost_nano_usd' => $cost,
            'estimated_cost_usd' => $cost === null ? null : self::dollars($cost),
            'pricing_version' => $version, 'rates' => $rates,
        ];
    }

    public function validUsage(array $usage): bool
    {
        foreach (['input_tokens', 'output_tokens'] as $key) {
            if (! isset($usage[$key]) || ! is_int($usage[$key]) || $usage[$key] < 0 || $usage[$key] > 1000000000) {
                return false;
            }
        }
        $details = $usage['input_tokens_details'] ?? [];
        if (! is_array($details)) {
            return false;
        }
        $sum = 0;
        foreach (['cached_tokens', 'cache_write_tokens'] as $key) {
            $value = $details[$key] ?? 0;
            if (! is_int($value) || $value < 0 || $value > $usage['input_tokens']) {
                return false;
            }
            $sum += $value;
        }

        return $sum <= $usage['input_tokens'];
    }

    public static function dollars(int $nano): string
    {
        return intdiv($nano, 1000000000).'.'.str_pad((string) ($nano % 1000000000), 9, '0', STR_PAD_LEFT);
    }
}
