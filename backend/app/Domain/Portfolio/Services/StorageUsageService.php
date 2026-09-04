<?php

namespace App\Domain\Portfolio\Services;

use App\Domain\Documents\Models\Document;
use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Properties\Models\PropertyPhoto;
use Illuminate\Validation\ValidationException;

class StorageUsageService
{
    public function __construct(private readonly PlanService $plans) {}

    public function used(Portfolio $portfolio): int
    {
        return (int) Document::where('portfolio_id', $portfolio->id)->sum('size')
            + (int) PropertyPhoto::whereHas('property', fn ($query) => $query->where('portfolio_id', $portfolio->id))->sum('size');
    }

    public function assertCanStore(Portfolio $portfolio, int $bytes): void
    {
        if ($this->used($portfolio) + $bytes > $this->plans->definition($portfolio)['storage_limit_bytes']) {
            throw ValidationException::withMessages(['file' => ['Has alcanzado el límite de almacenamiento de tu plan.']]);
        }
    }
}
