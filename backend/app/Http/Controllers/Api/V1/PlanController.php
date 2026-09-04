<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Portfolio\Services\PlanService;
use App\Domain\Portfolio\Services\StorageUsageService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function __construct(private readonly PlanService $plans, private readonly StorageUsageService $storage) {}

    public function __invoke(Request $request)
    {
        $portfolio = $request->user()->portfolio();

        $subscription = $request->user()->subscription('default');

        return [
            'current' => [
                ...$this->plans->summary($portfolio, $this->storage->used($portfolio)),
                'subscribed' => (bool) $subscription,
                'on_trial' => $portfolio->trial_ends_at?->isFuture() ?? false,
                'trial_ends_at' => $portfolio->trial_ends_at?->toIso8601String(),
                'ends_at' => $subscription?->ends_at?->toIso8601String(),
                'pending_change' => $portfolio->pending_plan ? [
                    'plan' => $portfolio->pending_plan,
                    'name' => config("plans.{$portfolio->pending_plan}.name"),
                    'period' => $portfolio->pending_billing_period,
                    'effective_at' => $portfolio->pending_plan_effective_at?->toIso8601String(),
                ] : null,
            ],
            'plans' => collect($this->plans->catalog())->filter(fn (array $plan) => $plan['commercial'])->map(fn (array $plan) => [
                ...collect($plan)->except('prices')->all(),
                'checkout_available' => collect($plan['prices'])->every(fn ($price) => is_string($price) && str_starts_with($price, 'price_')),
            ]),
        ];
    }
}
