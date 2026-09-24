<?php

namespace App\Domain\Assistant\Evaluation;

use InvalidArgumentException;
use RuntimeException;

final class FixtureSuite
{
    public const VERSION = 'synthetic-read-v1';

    public function cases(): array
    {
        $data = json_decode(file_get_contents(base_path('tests/fixtures/assistant/read-cases.json')), true, 64, JSON_THROW_ON_ERROR);
        if (($data['version'] ?? null) !== self::VERSION || ($data['synthetic'] ?? false) !== true || ! is_array($data['cases'] ?? null)) {
            throw new RuntimeException('Suite de evaluación no válida.');
        }

        return $data['cases'];
    }

    /** A replay is a test fixture, never a model benchmark. */
    public function responses(array $case, string $candidate, string $model): array
    {
        if (! in_array($candidate, ['reference', 'regression'], true)) {
            throw new InvalidArgumentException('Candidato desconocido. Usa reference o regression.');
        }
        $responses = $case['replay'];
        if ($candidate === 'regression' && $case['id'] === 'monthly_expenses') {
            $last = count($responses) - 1;
            $responses[$last]['output'][0]['content'][0]['text'] = json_encode([
                'kind' => 'answer', 'basis' => 'portfolio_data', 'content' => 'Los gastos pagados son 999 €.',
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        return array_map(fn (array $response) => ['model' => $model, ...$response], $responses);
    }
}
