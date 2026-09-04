<?php

namespace App\Domain\Portfolio\Services;

use App\Domain\Portfolio\Models\Portfolio;
use Illuminate\Validation\ValidationException;

class PlanService
{
    public function catalog(): array
    {
        return config('plans');
    }

    public function definition(Portfolio $portfolio): array
    {
        return $this->catalog()[$this->effectiveCode($portfolio)] ?? $this->catalog()['free'];
    }

    public function effectiveCode(Portfolio $portfolio): string
    {
        if ($portfolio->trial_ends_at?->isFuture()) {
            return 'investor';
        }

        return array_key_exists($portfolio->plan, $this->catalog()) ? $portfolio->plan : 'free';
    }

    public function assertCanCreateProperty(Portfolio $portfolio): void
    {
        $limit = $this->definition($portfolio)['property_limit'];
        if ($portfolio->properties()->count() >= $limit) {
            throw ValidationException::withMessages(['plan' => ["Tu plan permite hasta {$limit} inmuebles."]]);
        }
    }

    public function summary(Portfolio $portfolio, int $storageUsed): array
    {
        $plan = $this->definition($portfolio);
        $properties = $portfolio->properties()->count();

        return [
            'code' => $this->effectiveCode($portfolio), 'name' => $plan['name'],
            'status' => $portfolio->trial_ends_at?->isFuture()
                ? 'trialing'
                : ($portfolio->plan === 'free' && ! $portfolio->billing_subscription_id ? 'free' : $portfolio->subscription_status),
            'on_trial' => $portfolio->trial_ends_at?->isFuture() ?? false,
            'trial_ends_at' => $portfolio->trial_ends_at?->toIso8601String(),
            'properties' => ['used' => $properties, 'limit' => $plan['property_limit']],
            'storage' => ['used' => $storageUsed, 'limit' => (int) $plan['storage_limit_bytes'], 'percentage' => $plan['storage_limit_bytes'] ? round($storageUsed / $plan['storage_limit_bytes'] * 100, 1) : 0],
        ];
    }
}
