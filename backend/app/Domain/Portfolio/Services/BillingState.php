<?php

namespace App\Domain\Portfolio\Services;

use App\Models\User;

final class BillingState
{
    public function blocksCheckout(User $user): bool
    {
        return $user->subscriptions()->get()->contains(fn ($subscription) => ! in_array($subscription->stripe_status, ['canceled', 'incomplete_expired'], true)
            || $subscription->onGracePeriod());
    }
}
