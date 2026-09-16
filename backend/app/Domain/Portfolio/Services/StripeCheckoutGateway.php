<?php

namespace App\Domain\Portfolio\Services;

use App\Models\User;

class StripeCheckoutGateway
{
    public function customer(User $user): string
    {
        return $user->createOrGetStripeCustomer()->id;
    }

    public function hasOpenSubscription(User $user, string $customer): bool
    {
        foreach ($user->stripe()->subscriptions->all(['customer' => $customer, 'status' => 'all', 'limit' => 100])->autoPagingIterator() as $subscription) {
            if (! in_array($subscription->status, ['canceled', 'incomplete_expired'], true)) {
                return true;
            }
        }

        return false;
    }

    public function price(User $user, string $id): array
    {
        return $user->stripe()->prices->retrieve($id)->toArray();
    }

    public function session(User $user, string $id): array
    {
        return $user->stripe()->checkout->sessions->retrieve($id)->toArray();
    }

    public function create(User $user, array $parameters, string $key): array
    {
        return $user->stripe()->checkout->sessions->create($parameters, ['idempotency_key' => $key])->toArray();
    }
}
