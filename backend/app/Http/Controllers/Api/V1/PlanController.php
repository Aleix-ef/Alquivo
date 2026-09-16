<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Portfolio\Services\BillingState;
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
        $blocked = app(BillingState::class)->blocksCheckout($request->user());

        return [
            'billing_enabled' => (bool) config('beta.billing_enabled'),
            'current' => [
                ...$this->plans->summary($portfolio, $this->storage->used($portfolio)),
                'subscribed' => (bool) ($subscription?->valid()),
                'can_manage_billing' => config('beta.billing_enabled') && filled($request->user()->stripe_id),
                'has_billing_history' => filled($request->user()->stripe_id),
                'payment_pending' => in_array($subscription?->stripe_status, ['past_due', 'unpaid', 'incomplete', 'paused'], true),
                'billing_status' => $subscription?->stripe_status,
                'on_trial' => $portfolio->trial_ends_at?->isFuture() ?? false,
                'trial_ends_at' => $portfolio->trial_ends_at?->toIso8601String(),
                'ends_at' => $subscription?->ends_at?->toIso8601String(),
            ],
            'plans' => collect($this->plans->catalog())->filter(fn (array $plan) => $plan['commercial'])->map(fn (array $plan) => [
                ...collect($plan)->except('prices')->all(),
                'checkout_available' => config('beta.billing_enabled') && is_string($plan['prices']['monthly'] ?? null)
                    && str_starts_with($plan['prices']['monthly'], 'price_') && filled(config('cashier.secret')) && ! $blocked,
            ]),
        ];
    }
}
