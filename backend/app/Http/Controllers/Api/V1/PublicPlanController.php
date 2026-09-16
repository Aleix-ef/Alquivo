<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ProductFeatures;

class PublicPlanController extends Controller
{
    /**
     * The public site only needs the commercial promise of each plan. Stripe
     * price identifiers and account-specific subscription information remain
     * behind the authenticated plans endpoint.
     */
    public function __invoke(): array
    {
        return [
            'plans' => collect(config('plans'))->map(function (array $plan, string $code): array {
                $plan = app(ProductFeatures::class)->plan($plan);

                return [
                    'code' => $code,
                    'name' => $plan['name'],
                    'price_monthly' => $plan['price_monthly'],
                    'price_yearly' => $plan['price_yearly'],
                    'property_limit' => $plan['property_limit'],
                    'storage_limit_bytes' => $plan['storage_limit_bytes'],
                    'features' => $plan['features'],
                    'commercial' => $plan['commercial'],
                ];
            })->values(),
        ];
    }
}
