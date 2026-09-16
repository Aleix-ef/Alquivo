<?php

namespace App\Domain\Portfolio\Services;

use App\Domain\Portfolio\Models\Portfolio;
use Illuminate\Validation\ValidationException;

/** Entitlements affect writes, never ownership or access to historical data. */
final class PropertyAccess
{
    public function editableIds(Portfolio $portfolio): array
    {
        return $portfolio->properties()->orderBy('id')
            ->limit(app(PlanService::class)->definition($portfolio)['property_limit'])
            ->pluck('id')->all();
    }

    public function canWrite(Portfolio $portfolio, int $propertyId): bool
    {
        return in_array($propertyId, $this->editableIds($portfolio), true);
    }

    public function assertWritable(Portfolio $portfolio, int $propertyId): void
    {
        abort_unless($portfolio->properties()->whereKey($propertyId)->exists(), 404);
        if (! $this->canWrite($portfolio, $propertyId)) {
            throw ValidationException::withMessages(['plan' => ['Este inmueble supera el límite de tu plan y está en modo consulta. Puedes consultar y exportar sus datos. Revisa Planes para volver a gestionarlo.']]);
        }
    }
}
