<?php

namespace App\Domain\Assistant\Services;

use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Portfolio\Services\PlanService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssistantUsageService
{
    public function __construct(private readonly PlanService $plans) {}

    public function summary(Portfolio $portfolio, User $user, ?string $month = null): array
    {
        $plan = $this->plans->effectiveCode($portfolio);
        $onTrial = $portfolio->trial_ends_at?->isFuture() ?? false;
        $limitCode = $onTrial ? 'trial' : $plan;
        $limit = (int) config("assistant.limits.{$limitCode}", 0);
        $tokenLimits = config("assistant.token_limits.{$limitCode}", ['input' => 0, 'output' => 0]);
        $usage = DB::table('ai_monthly_usage')->where('portfolio_id', $portfolio->id)
            ->where('user_id', $user->id)->where('month', $month ?? now()->startOfMonth()->toDateString())->first();
        $used = (int) ($usage?->requests ?? 0);
        $inputUsed = (int) ($usage?->input_tokens ?? 0);
        $outputUsed = (int) ($usage?->output_tokens ?? 0);
        $tokensAvailable = $inputUsed < (int) $tokenLimits['input'] && $outputUsed < (int) $tokenLimits['output'];

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $tokensAvailable ? max(0, $limit - $used) : 0,
            'tokens' => [
                'input' => ['used' => $inputUsed, 'limit' => (int) $tokenLimits['input']],
                'output' => ['used' => $outputUsed, 'limit' => (int) $tokenLimits['output']],
            ],
            'resets_at' => now()->addMonthNoOverflow()->startOfMonth()->toIso8601String(),
        ];
    }

    public function reserve(Portfolio $portfolio, User $user): void
    {
        DB::transaction(function () use ($portfolio, $user) {
            $portfolio = Portfolio::whereKey($portfolio->id)->lockForUpdate()->firstOrFail();
            if ($this->summary($portfolio, $user)['remaining'] < 1) {
                throw ValidationException::withMessages(['message' => ['Has alcanzado el límite mensual del asistente.']]);
            }
            $key = ['portfolio_id' => $portfolio->id, 'user_id' => $user->id, 'month' => now()->startOfMonth()->toDateString()];
            DB::table('ai_monthly_usage')->insertOrIgnore([...$key, 'requests' => 0]);
            DB::table('ai_monthly_usage')->where($key)->increment('requests');
        });
    }

    public function recordTokens(Portfolio $portfolio, User $user, int $inputTokens, int $outputTokens, ?string $month = null): void
    {
        $key = ['portfolio_id' => $portfolio->id, 'user_id' => $user->id, 'month' => $month ?? now()->startOfMonth()->toDateString()];
        DB::table('ai_monthly_usage')->insertOrIgnore([...$key, 'requests' => 0]);
        DB::table('ai_monthly_usage')->where($key)->incrementEach(['input_tokens' => max(0, $inputTokens), 'output_tokens' => max(0, $outputTokens)]);
    }
}
