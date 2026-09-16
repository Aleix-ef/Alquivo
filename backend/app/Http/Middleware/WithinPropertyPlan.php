<?php

namespace App\Http\Middleware;

use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Services\PropertyAccess;
use App\Domain\Properties\Models\Property;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class WithinPropertyPlan
{
    public function handle(Request $request, Closure $next)
    {
        // Read/export and deletion of files or empty properties remain available.
        if ($request->isMethodSafe() || ($request->isMethod('DELETE') && ! $request->is('api/v1/rent-payments/*'))) {
            return $next($request);
        }
        $portfolio = $request->user()->portfolio();
        $access = app(PropertyAccess::class);
        $checkLease = function ($id) use ($portfolio, $access) {
            $lease = Lease::where('portfolio_id', $portfolio->id)->findOrFail($id);
            $access->assertWritable($portfolio, $lease->property_id);
        };

        // Check BOTH existing and requested associations: moving an existing record
        // to an editable property must not bypass the original property's limit.
        foreach ($request->route()->parameters() as $model) {
            if (! $model instanceof Model) {
                continue;
            }
            if ($model->getAttribute('portfolio_id') !== null) {
                abort_unless((int) $model->portfolio_id === $portfolio->id, 404);
            }
            if ($model instanceof Property) {
                $access->assertWritable($portfolio, $model->id);
            } elseif ($model->getAttribute('property_id')) {
                $access->assertWritable($portfolio, (int) $model->property_id);
            }
            if ($model->getAttribute('lease_id')) {
                $checkLease($model->lease_id);
            }
        }
        $data = $request->validate(['property_id' => ['sometimes', 'nullable', 'integer', 'min:1'], 'lease_id' => ['sometimes', 'nullable', 'integer', 'min:1']]);
        if (! empty($data['property_id'])) {
            $access->assertWritable($portfolio, (int) $data['property_id']);
        }
        if (! empty($data['lease_id'])) {
            $checkLease($data['lease_id']);
        }

        return $next($request);
    }
}
