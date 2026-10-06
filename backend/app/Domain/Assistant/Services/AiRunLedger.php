<?php

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Models\AiRun;
use App\Domain\Assistant\Models\AiRunStep;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AiRunLedger
{
    public function reserveCall(AiRun $run, array $route, array $request): AiRunStep
    {
        // UTF-8 bytes provide a conservative bound for text tokens, including instructions and schemas.
        // The first release accepts text only. File/image accounting requires a separate bounded pipeline.
        $bytes = strlen(json_encode($request, JSON_THROW_ON_ERROR));
        if ($bytes > config('ai.limits.max_input_bytes')) {
            throw new RuntimeException('Contexto demasiado grande.');
        }
        $rates = $route['pricing'];
        $reserve = $rates ? ($bytes + 2048) * max($rates['input'], $rates['cache_write'])
            + $request['max_output_tokens'] * $rates['output'] : 0;

        return $this->reserve($run, $route, $reserve);
    }

    public function reserveDocument(AiRun $run, array $route): AiRunStep
    {
        // Reserve the full document envelope. Never count base64 bytes as text tokens.
        // Live document processing remains blocked until actual costs are evaluated.
        $reserve = (int) config('ai_documents.budget_nano_usd');
        if ($reserve <= 0) {
            throw new RuntimeException('Presupuesto documental no configurado.');
        }

        return $this->reserve($run, $route, $reserve);
    }

    private function reserve(AiRun $run, array $route, int $reserve): AiRunStep
    {
        return DB::transaction(function () use ($run, $route, $reserve) {
            $global = $this->lockGlobal($run->billing_month->toDateString());
            Portfolio::whereKey($run->portfolio_id)->lockForUpdate()->firstOrFail();
            $run = AiRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            $monthly = AiRun::where('portfolio_id', $run->portfolio_id)->where('billing_month', $run->billing_month)
                ->selectRaw('COALESCE(SUM(estimated_cost_nano_usd + reserved_cost_nano_usd), 0) as total')->value('total');
            if ($run->estimated_cost_nano_usd + $run->reserved_cost_nano_usd + $reserve > config('ai.limits.run_cost_nano_usd')
                || $monthly + $reserve > config('ai.limits.portfolio_monthly_cost_nano_usd')
                || $global->estimated_cost_nano_usd + $global->reserved_cost_nano_usd + $reserve > config('ai.limits.global_monthly_cost_nano_usd')) {
                throw new RuntimeException('Presupuesto de IA alcanzado.');
            }
            $run->increment('reserved_cost_nano_usd', $reserve);
            DB::table('ai_provider_usage')->where('provider', 'openai')->where('month', $run->billing_month->toDateString())
                ->increment('reserved_cost_nano_usd', $reserve);

            return $run->steps()->create([
                'kind' => 'provider', 'status' => 'started', 'provider' => $route['provider'], 'model' => $route['model'],
                'pricing_version' => config('ai.pricing_version'), 'reserved_cost_nano_usd' => $reserve,
                'metadata' => ['profile' => $route['profile'], 'rates' => $route['pricing'],
                    'reasoning_effort' => $route['reasoning_effort'] ?? null],
            ]);
        });
    }

    public function completeCall(AiRunStep $step, array $response, int $latency, Portfolio $portfolio, User $user): void
    {
        // Price the requested model's contract, not an unrecognized provider snapshot alias.
        $calculator = app(AICostCalculator::class);
        $knownUsage = is_array($response['usage'] ?? null) && $calculator->validUsage($response['usage']);
        $cost = $calculator->atRates($knownUsage ? $response['usage'] : [], $step->metadata['rates'] ?? null, $step->pricing_version);
        $reasoningTokens = data_get($response, 'usage.output_tokens_details.reasoning_tokens');
        $reasoningTokens = $knownUsage && is_int($reasoningTokens) && $reasoningTokens >= 0
            && $reasoningTokens <= $cost['output_tokens'] ? $reasoningTokens : null;
        DB::transaction(function () use ($step, $response, $latency, $portfolio, $user, $knownUsage, $cost, $reasoningTokens) {
            $month = AiRun::findOrFail($step->run_id)->billing_month->toDateString();
            $global = $this->lockGlobal($month);
            Portfolio::whereKey($portfolio->id)->lockForUpdate()->firstOrFail();
            $run = AiRun::whereKey($step->run_id)->lockForUpdate()->firstOrFail();
            abort_unless($run->portfolio_id === $portfolio->id && $run->user_id === $user->id, 404);
            $step = AiRunStep::whereKey($step->id)->lockForUpdate()->firstOrFail();
            if ($step->status !== 'started') {
                return;
            }
            $settled = $knownUsage && $cost['estimated_cost_nano_usd'] !== null;
            $step->update([
                'status' => ($response['status'] ?? 'completed') === 'completed' ? 'completed' : 'incomplete',
                'provider_request_id' => $response['id'] ?? null,
                'input_tokens' => $cost['input_tokens'], 'output_tokens' => $cost['output_tokens'],
                'cached_input_tokens' => $cost['cached_input_tokens'], 'cache_write_tokens' => $cost['cache_write_tokens'],
                'estimated_cost_nano_usd' => $settled ? $cost['estimated_cost_nano_usd'] : null,
                'latency_ms' => $latency,
                'metadata' => [...$step->metadata, 'reported_model' => $response['model'] ?? $step->model,
                    'usage_known' => $knownUsage, 'reasoning_tokens' => $reasoningTokens],
            ]);
            $run->update([
                'input_tokens' => $run->input_tokens + $cost['input_tokens'],
                'output_tokens' => $run->output_tokens + $cost['output_tokens'],
                'estimated_cost_nano_usd' => $run->estimated_cost_nano_usd + ($settled ? $cost['estimated_cost_nano_usd'] : 0),
                // Unknown consumption stays reserved, never silently treated as free.
                'reserved_cost_nano_usd' => $run->reserved_cost_nano_usd - ($settled ? $step->reserved_cost_nano_usd : 0),
                'cost_incomplete' => $run->cost_incomplete || ! $settled,
            ]);
            if ($settled) {
                DB::table('ai_provider_usage')->where('provider', 'openai')->where('month', $month)->update([
                    'estimated_cost_nano_usd' => $global->estimated_cost_nano_usd + $cost['estimated_cost_nano_usd'],
                    'reserved_cost_nano_usd' => $global->reserved_cost_nano_usd - $step->reserved_cost_nano_usd,
                ]);
            }
            app(AssistantUsageService::class)->recordTokens($portfolio, $user, $cost['input_tokens'], $cost['output_tokens'], $run->billing_month->toDateString());
        });
    }

    public function failCall(AiRunStep $step, string $code, int $latency): void
    {
        DB::transaction(function () use ($step, $code, $latency) {
            $run = AiRun::whereKey($step->run_id)->lockForUpdate()->firstOrFail();
            $step = AiRunStep::whereKey($step->id)->lockForUpdate()->firstOrFail();
            if ($step->status !== 'started') {
                return;
            }
            $step->update(['status' => 'failed', 'error_code' => $code, 'latency_ms' => $latency]);
            $run->update(['cost_incomplete' => true]);
        });
    }

    private function lockGlobal(string $month): object
    {
        $key = ['provider' => 'openai', 'month' => $month];
        DB::table('ai_provider_usage')->insertOrIgnore($key);

        return DB::table('ai_provider_usage')->where($key)->lockForUpdate()->first();
    }
}
