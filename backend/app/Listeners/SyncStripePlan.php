<?php

namespace App\Listeners;

use App\Domain\Portfolio\Services\StripePlanSynchronizer;
use App\Models\User;
use Laravel\Cashier\Events\WebhookHandled;

class SyncStripePlan
{
    public function __construct(private readonly StripePlanSynchronizer $synchronizer) {}

    public function handle(WebhookHandled $event): void
    {
        $customer = data_get($event->payload, 'data.object.customer');
        if ($customer && $user = User::where('stripe_id', $customer)->first()) {
            $this->synchronizer->sync($user);
        }
    }
}
