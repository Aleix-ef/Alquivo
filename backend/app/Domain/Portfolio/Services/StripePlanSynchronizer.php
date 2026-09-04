<?php

namespace App\Domain\Portfolio\Services;

use App\Models\User;

class StripePlanSynchronizer
{
    public function sync(User $user): void
    {
        $portfolio = $user->portfolio();
        if (! $portfolio) {
            return;
        }
        $subscription = $user->subscription('default');
        $price = $subscription?->stripe_price;
        $planCode = collect(config('plans'))->search(fn (array $plan) => in_array($price, array_filter($plan['prices']), true));
        $planCode = $subscription?->valid() && $planCode !== false ? $planCode : 'free';
        $definition = config("plans.{$planCode}");

        $update = [
            'plan' => $planCode,
            'storage_limit_bytes' => $definition['storage_limit_bytes'],
            'subscription_status' => $subscription?->stripe_status ?? 'cancelled',
            'plan_changed_at' => now(),
            'billing_customer_id' => $user->stripe_id,
            'billing_subscription_id' => $subscription?->stripe_id,
        ];
        if ($portfolio->pending_plan === $planCode) {
            $update = [...$update, 'pending_plan' => null, 'pending_billing_period' => null, 'pending_plan_effective_at' => null, 'billing_schedule_id' => null];
        }
        $portfolio->update($update);
    }
}
