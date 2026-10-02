<?php

namespace Tests\Support;

use App\Domain\Assistant\Contracts\AIProviderInterface;
use RuntimeException;

/**
 * Test-only, persistent envelope across invocations. Failed/unknown calls keep their reservation.
 * The application's AiRunLedger remains active as a second independent budget control.
 */
final class ProductionAiBudgetProvider implements AIProviderInterface
{
    private array $trace = [];

    private string $caseId = '';

    public function __construct(
        private readonly AIProviderInterface $inner,
        private readonly string $budgetFile,
        private readonly int $limitNano = 1_000_000_000,
    ) {}

    public function startCase(string $id): void
    {
        $this->caseId = $id;
        $this->trace = [];
    }

    public function trace(): array
    {
        return $this->trace;
    }

    public function spentNano(): int
    {
        return $this->locked(fn (array $state) => (int) $state['spent_nano']);
    }

    public function generate(array $request, float $timeout): array
    {
        if ($this->caseId === '') {
            throw new RuntimeException('A synthetic case must be selected first.');
        }
        $model = (string) ($request['model'] ?? '');
        $rates = config('ai.models.'.$model);
        if (! is_array($rates) || ! isset($rates['input'], $rates['cache_write'], $rates['output'])) {
            throw new RuntimeException('Model pricing unavailable.');
        }
        if (($request['store'] ?? null) !== false || isset($request['tools'][0]['type']) && $request['tools'][0]['type'] !== 'function') {
            throw new RuntimeException('Unsafe provider request.');
        }
        $bytes = strlen(json_encode($request, JSON_THROW_ON_ERROR));
        $reserve = ($bytes + 2048) * max((int) $rates['input'], (int) $rates['cache_write'])
            + (int) ($request['max_output_tokens'] ?? 0) * (int) $rates['output'];
        $this->locked(function (array &$state) use ($reserve): void {
            if ($this->limitNano < $state['spent_nano'] + $reserve) {
                throw new RuntimeException('Phase budget exhausted before provider call.');
            }
            $state['spent_nano'] += $reserve;
            $state['calls']++;
        }, true);
        fprintf(STDERR, "[Alquivo eval] calls=%d cumulative_reserved_or_spent_usd=%.6f / %.6f\n",
            $this->calls(), $this->spentNano() / 1_000_000_000, $this->limitNano / 1_000_000_000);

        $response = $this->inner->generate($request, $timeout);
        $usage = $response['usage'] ?? null;
        if (is_array($usage) && isset($usage['input_tokens'], $usage['output_tokens'])) {
            $actual = (int) $usage['input_tokens'] * (int) $rates['input']
                + (int) $usage['output_tokens'] * (int) $rates['output'];
            $this->locked(function (array &$state) use ($reserve, $actual): void {
                $state['spent_nano'] += $actual - $reserve;
            }, true);
        }
        foreach (($response['output'] ?? []) as $item) {
            if (($item['type'] ?? null) !== 'function_call') {
                continue;
            }
            $decoded = json_decode((string) ($item['arguments'] ?? ''), true);
            $this->trace[] = ['name' => $item['name'] ?? null, 'arguments' => is_array($decoded) ? $decoded : null];
        }

        return $response;
    }

    public function calls(): int
    {
        return $this->locked(fn (array $state) => (int) $state['calls']);
    }

    private function locked(callable $callback, bool $write = false): mixed
    {
        $handle = fopen($this->budgetFile, 'c+');
        if ($handle === false || ! flock($handle, LOCK_EX)) {
            throw new RuntimeException('Could not lock evaluation budget.');
        }
        try {
            rewind($handle);
            $raw = stream_get_contents($handle);
            $state = $raw === '' ? ['spent_nano' => 0, 'calls' => 0] : json_decode($raw, true);
            if (! is_array($state) || ! isset($state['spent_nano'], $state['calls'])
                || ! is_int($state['spent_nano']) || ! is_int($state['calls'])) {
                throw new RuntimeException('Evaluation budget file is invalid.');
            }
            $result = $callback($state);
            if ($write) {
                rewind($handle);
                ftruncate($handle, 0);
                fwrite($handle, json_encode($state, JSON_THROW_ON_ERROR));
                fflush($handle);
            }

            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
