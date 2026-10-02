<?php

namespace App\Domain\Portfolio\Services;

use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use App\Support\ProductFeatures;
use Illuminate\Validation\ValidationException;

class PlanService
{
    public function catalog(): array
    {
        return array_map(fn ($plan) => app(ProductFeatures::class)->plan($plan), config('plans'));
    }

    public function definition(Portfolio $portfolio): array
    {
        if ($this->isAdminPortfolio($portfolio)) {
            $plan = config('plans.founder');
            // The local preview must not have lower quotas than the public beta.
            if (app(ProductFeatures::class)->betaProgram()) {
                $plan['property_limit'] = max($plan['property_limit'], config('plans.beta.property_limit'));
                $plan['storage_limit_bytes'] = max($plan['storage_limit_bytes'], config('plans.beta.storage_limit_bytes'));
            }

            return [...$plan, 'name' => 'Administrador local'];
        }

        return $this->catalog()[$this->effectiveCode($portfolio)] ?? $this->catalog()['free'];
    }

    public function isAdminPortfolio(Portfolio $portfolio): bool
    {
        // Only the owner's local testing portfolio; no cross-portfolio authorization bypass.
        return app()->environment('local') && $portfolio->members()->wherePivot('role', 'owner')
            ->where('users.role', 'admin')->whereNotNull('email_verified_at')->whereNotNull('two_factor_confirmed_at')->exists();
    }

    public function visibleCatalog(?User $user = null): array
    {
        if ($user?->local_admin) {
            return array_map(fn ($plan) => app(ProductFeatures::class)->plan($plan, $user), config('plans'));
        }

        return array_filter($this->catalog(), fn ($code) => app(ProductFeatures::class)->betaProgram() ? $code === 'beta' : $code !== 'beta', ARRAY_FILTER_USE_KEY);
    }

    public function effectiveCode(Portfolio $portfolio): string
    {
        if ($this->isAdminPortfolio($portfolio)) {
            return 'admin';
        }
        if (app(ProductFeatures::class)->betaProgram()) {
            return 'beta';
        }
        if ($portfolio->plan === 'beta') {
            return 'free';
        }
        if ($portfolio->trial_ends_at?->isFuture()) {
            return 'founder';
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

    public function hasFeature(Portfolio $portfolio, string $feature): bool
    {
        return in_array($feature, $this->definition($portfolio)['entitlements'] ?? [], true);
    }

    public function summary(Portfolio $portfolio, int $storageUsed): array
    {
        $plan = $this->definition($portfolio);
        $properties = $portfolio->properties()->count();
        $beta = app(ProductFeatures::class)->betaProgram();
        $admin = $this->isAdminPortfolio($portfolio);

        return [
            'code' => $this->effectiveCode($portfolio), 'name' => $plan['name'],
            'status' => $admin ? 'local_admin' : ($beta ? 'beta' : ($portfolio->trial_ends_at?->isFuture()
                ? 'trialing'
                : ($portfolio->plan === 'free' && ! $portfolio->billing_subscription_id ? 'free' : $portfolio->subscription_status))),
            'on_trial' => ! $admin && ! $beta && ($portfolio->trial_ends_at?->isFuture() ?? false),
            'trial_ends_at' => $admin || $beta ? null : $portfolio->trial_ends_at?->toIso8601String(),
            'properties' => ['used' => $properties, 'limit' => $plan['property_limit'],
                'read_only_count' => max(0, $properties - $plan['property_limit']),
                'editable_ids' => app(PropertyAccess::class)->editableIds($portfolio)],
            'storage' => ['used' => $storageUsed, 'limit' => (int) $plan['storage_limit_bytes'], 'percentage' => $plan['storage_limit_bytes'] ? round($storageUsed / $plan['storage_limit_bytes'] * 100, 1) : 0],
        ];
    }
}
