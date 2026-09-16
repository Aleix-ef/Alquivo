<?php

namespace App\Domain\Portfolio\Services;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Services\UploadScanner;
use App\Domain\Documents\Services\PrivateFileVault;
use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Properties\Models\PropertyPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    public function store(Portfolio $portfolio, UploadedFile $file, string $directory, callable $create)
    {
        app(UploadScanner::class)->scan($file);
        $key = null;
        try {
            return DB::transaction(function () use ($portfolio, $file, $directory, $create, &$key) {
                $portfolio = Portfolio::whereKey($portfolio->id)->lockForUpdate()->firstOrFail();
                $this->assertCanStore($portfolio, $file->getSize());
                $key = app(PrivateFileVault::class)->store($file, $directory);
                if (! $key) {
                    throw new \RuntimeException('Private storage unavailable');
                }

                return $create($key);
            });
        } catch (\Throwable $exception) {
            if ($key) {
                Storage::disk('local')->delete($key);
            }
            throw $exception;
        }
    }
}
