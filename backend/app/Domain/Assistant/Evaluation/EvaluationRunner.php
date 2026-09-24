<?php

namespace App\Domain\Assistant\Evaluation;

use App\Domain\Assistant\Contracts\AIProviderInterface;
use App\Domain\Assistant\Services\AICostCalculator;
use App\Domain\Assistant\Services\AssistantReply;
use App\Domain\Assistant\Services\PortfolioAssistantTools;
use RuntimeException;
use Throwable;

/** Exercises provider contracts with synthetic tool outputs. Never invokes domain tools. */
final class EvaluationRunner
{
    public function __construct(
        private readonly EvaluationGrader $grader,
        private readonly AICostCalculator $costs,
        private readonly PortfolioAssistantTools $definitions,
    ) {}

    public function run(array $case, AIProviderInterface $provider, array $route): array
    {
        if (($case['synthetic'] ?? false) !== true) {
            throw new RuntimeException('Solo se permiten fixtures declaradas sintéticas.');
        }
        $started = hrtime(true);
        $input = [['role' => 'user', 'content' => $case['input']]];
        $trace = [];
        $reply = null;
        $error = null;
        $steps = [];
        $hasEvidence = false;
        $usage = ['input_tokens' => 0, 'output_tokens' => 0, 'cached_tokens' => 0];
        $costNano = 0;
        $costKnown = true;
        try {
            for ($round = 0; $round < 4; $round++) {
                $request = [
                    'model' => $route['model'],
                    'instructions' => 'Evaluación sintética de lectura de Alquivo. Consulta únicamente las herramientas autorizadas. No inventes cifras ni entidades. Si faltan datos o hay ambigüedad pide concreción. No ejecutes escrituras. Fecha de referencia: '.$case['context_fixture']['as_of'].'.',
                    'input' => $input,
                    'tools' => $this->definitions->definitions(),
                    'parallel_tool_calls' => false,
                    'text' => ['format' => AssistantReply::format()],
                    'max_output_tokens' => min(1000, $route['max_output_tokens']),
                    'store' => false,
                ];
                $response = $provider->generate($request, 10.0);
                $model = (string) ($response['model'] ?? $route['model']);
                $stepUsage = $response['usage'] ?? [];
                $usage['input_tokens'] += (int) ($stepUsage['input_tokens'] ?? 0);
                $usage['output_tokens'] += (int) ($stepUsage['output_tokens'] ?? 0);
                $usage['cached_tokens'] += (int) data_get($stepUsage, 'input_tokens_details.cached_tokens', 0);
                $cost = $this->costs->calculate($model, $stepUsage);
                $costKnown = $costKnown && isset($cost['estimated_cost_nano_usd']);
                $costNano += (int) ($cost['estimated_cost_nano_usd'] ?? 0);
                $steps[] = ['model' => $model, 'usage' => $stepUsage, 'estimated_cost_usd' => $cost['estimated_cost_usd'] ?? null, 'pricing_version' => $cost['pricing_version'] ?? null];
                if (($response['status'] ?? 'completed') !== 'completed') {
                    throw new RuntimeException('provider_incomplete');
                }
                $output = $response['output'] ?? [];
                $calls = array_values(array_filter($output, fn (array $item) => ($item['type'] ?? null) === 'function_call'));
                if (count($calls) > 1) {
                    throw new RuntimeException('parallel_tools_not_allowed');
                }
                if ($calls === []) {
                    $reply = AssistantReply::parse($output, $hasEvidence);
                    break;
                }
                $call = $calls[0];
                $decoded = json_decode((string) ($call['arguments'] ?? ''), false, 64, JSON_THROW_ON_ERROR);
                if (! is_object($decoded)) {
                    throw new RuntimeException('tool_arguments_must_be_object');
                }
                $arguments = json_decode((string) $call['arguments'], true, 64, JSON_THROW_ON_ERROR);
                $name = (string) ($call['name'] ?? '');
                $trace[] = ['name' => $name, 'arguments' => $arguments];
                $fixture = $case['context_fixture']['tool_results'][count($trace) - 1] ?? null;
                // Strict fixture boundary: no invented IDs or unexpected tool calls reach any data source.
                if ($fixture === null || $name !== $fixture['name'] || ! $this->sameArguments($arguments, $fixture['arguments'])) {
                    throw new RuntimeException('tool_not_in_fixture');
                }
                $result = $fixture['result'];
                $hasEvidence = $hasEvidence || (! isset($result['error']) && ($result['found'] ?? true) !== false);
                $input = [...$input, ...$output, [
                    'type' => 'function_call_output',
                    'call_id' => (string) ($call['call_id'] ?? ''),
                    'output' => json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ]];
            }
            if ($reply === null) {
                throw new RuntimeException('round_limit');
            }
        } catch (Throwable $exception) {
            // Never print provider bodies, prompts or arbitrary exception messages.
            $error = $exception::class;
        }

        return [
            'case_id' => $case['id'],
            'profile' => $route['profile'],
            'requested_model' => $route['model'],
            'grade' => $this->grader->grade($case, $trace, $reply, $error),
            'trace' => $trace,
            'reply' => $reply,
            'error_type' => $error,
            'provider_calls' => count($steps),
            'usage' => $usage,
            'estimated_cost_nano_usd' => $costKnown ? $costNano : null,
            'estimated_cost_usd' => $costKnown ? AICostCalculator::dollars($costNano) : null,
            'latency_ms' => round((hrtime(true) - $started) / 1000000, 3),
            'steps' => $steps,
        ];
    }

    private function sameArguments(array $left, array $right): bool
    {
        ksort($left);
        ksort($right);

        return $left === $right;
    }
}
