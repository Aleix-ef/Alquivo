<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Portfolio\Services\BillingState;
use App\Domain\Portfolio\Services\PlanService;
use App\Domain\Portfolio\Services\StorageUsageService;
use App\Http\Controllers\Controller;
use App\Support\ProductFeatures;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function __construct(private readonly PlanService $plans, private readonly StorageUsageService $storage) {}

    public function __invoke(Request $request)
    {
        $portfolio = $request->user()->portfolio();

        $subscription = $request->user()->subscription('default');
        $blocked = app(BillingState::class)->blocksCheckout($request->user());
        $features = app(ProductFeatures::class);
        $beta = $features->betaProgram();

        return [
            'admin_preview' => $request->user()->local_admin,
            'beta_program' => $beta,
            'billing_enabled' => $features->billing(),
            'current' => [
                ...$this->plans->summary($portfolio, $this->storage->used($portfolio)),
                'subscribed' => ! $beta && (bool) ($subscription?->valid()),
                'can_manage_billing' => $features->billing() && filled($request->user()->stripe_id),
                'has_billing_history' => filled($request->user()->stripe_id),
                'payment_pending' => ! $beta && in_array($subscription?->stripe_status, ['past_due', 'unpaid', 'incomplete', 'paused'], true),
                'billing_status' => $beta ? null : $subscription?->stripe_status,
                'ends_at' => $beta ? null : $subscription?->ends_at?->toIso8601String(),
            ],
            'plans' => collect($this->plans->visibleCatalog($request->user()))->filter(fn (array $plan) => $request->user()->local_admin || $beta || $plan['commercial'])->map(fn (array $plan) => [
                ...collect($plan)->except('prices')->all(),
                'checkout_available' => $features->billing() && is_string($plan['prices']['monthly'] ?? null)
                    && str_starts_with($plan['prices']['monthly'], 'price_') && filled(config('cashier.secret')) && ! $blocked,
            ]),
        ];
    }
}
