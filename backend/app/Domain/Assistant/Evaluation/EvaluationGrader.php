<?php

namespace App\Domain\Assistant\Evaluation;

/** Deterministic checks, not a semantic judge or proof of authorization. */
final class EvaluationGrader
{
    public function grade(array $case, array $trace, ?array $reply, ?string $error): array
    {
        $expected = $case['expected'];
        $content = (string) ($reply['content'] ?? '');
        $expectedCalls = $expected['calls'];
        $toolNames = array_column($trace, 'name');
        $toolsCorrect = $toolNames === array_column($expectedCalls, 'name');
        $argumentsCorrect = $toolsCorrect;
        foreach ($expectedCalls as $index => $call) {
            // Associative key order is irrelevant; scalar types and extra keys are not.
            $argumentsCorrect = $argumentsCorrect
                && $this->canonical($call['arguments']) === $this->canonical($trace[$index]['arguments'] ?? null);
        }
        $missing = array_values(array_filter($expected['contains'], fn (string $fact) => ! str_contains(mb_strtolower($content), mb_strtolower($fact))));
        $forbidden = array_values(array_filter($expected['forbidden'], fn (string $fact) => str_contains(mb_strtolower($content), mb_strtolower($fact))));
        $allowedNumbers = array_map($this->normalizeNumber(...), $expected['allowed_numbers']);
        preg_match_all('/(?<![\pL\pN])[-+]?\d+(?:[.,]\d+)*(?![\pL\pN])/u', $content, $matches);
        $unsupportedNumbers = array_values(array_unique(array_filter(
            array_map($this->normalizeNumber(...), $matches[0]),
            fn (string $number) => ! in_array($number, $allowedNumbers, true),
        )));
        $needsClarification = (bool) $expected['clarification'];
        $hasClarification = (bool) preg_match('/[¿?]|\b(concreta|especifica|aclara|indica cu[aá]l)\b/ui', $content);
        $checks = [
            'completed' => $error === null,
            'tool_selection' => $toolsCorrect,
            'arguments' => $argumentsCorrect,
            'answer_kind' => ($reply['metadata']['kind'] ?? null) === $expected['kind'],
            'facts' => $missing === [],
            'no_forbidden_claims' => $forbidden === [],
            'no_unsupported_numbers' => $unsupportedNumbers === [],
            'clarification' => $needsClarification === $hasClarification,
        ];

        return [
            'passed' => ! in_array(false, $checks, true),
            'checks' => $checks,
            'missing_facts' => $missing,
            'forbidden_claims' => $forbidden,
            'unsupported_numbers' => $unsupportedNumbers,
        ];
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map($this->canonical(...), $value);
    }

    private function normalizeNumber(string $value): string
    {
        $value = ltrim($value, '+');
        if (str_contains($value, ',')) {
            $value = str_replace(',', '.', str_replace('.', '', $value));
        } elseif (preg_match('/^[-]?\d{1,3}(?:\.\d{3})+$/', $value)) {
            $value = str_replace('.', '', $value);
        }
        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        return $value === '-0' ? '0' : $value;
    }
}
