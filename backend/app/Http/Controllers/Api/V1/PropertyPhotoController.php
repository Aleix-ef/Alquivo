<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Documents\Services\PrivateFileDeletion;
use App\Domain\Documents\Services\PrivateFileVault;
use App\Domain\Portfolio\Services\StorageUsageService;
use App\Domain\Properties\Models\Property;
use App\Domain\Properties\Models\PropertyPhoto;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PropertyPhotoController extends Controller
{
    public function __construct(private readonly StorageUsageService $storageUsage) {}

    public function store(Request $request, Property $property)
    {
        abort_unless($property->portfolio_id === $request->user()->portfolio()->id, 404);
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144', 'dimensions:max_width=6000,max_height=6000']]);
        $file = $request->file('photo');

        $photo = $this->storageUsage->store($request->user()->portfolio(), $file, "portfolios/{$property->portfolio_id}/properties/{$property->id}", function ($key) use ($property, $file) {
            $isFirst = ! $property->photos()->exists();

            return $property->photos()->create([
                'storage_key' => $key, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
                'sort_order' => $property->photos()->count(), 'is_cover' => $isFirst,
            ]);
        });

        return response()->json($photo, 201);
    }

    public function show(Request $request, PropertyPhoto $propertyPhoto)
    {
        abort_unless($propertyPhoto->property->portfolio_id === $request->user()->portfolio()->id, 404);

        return response(app(PrivateFileVault::class)->read($propertyPhoto->storage_key), 200, ['Content-Type' => $propertyPhoto->mime_type, 'Cache-Control' => 'private, no-store']);
    }

    public function cover(Request $request, PropertyPhoto $propertyPhoto)
    {
        $this->ensureOwned($request, $propertyPhoto);
        DB::transaction(function () use ($propertyPhoto) {
            PropertyPhoto::where('property_id', $propertyPhoto->property_id)->update(['is_cover' => false]);
            $propertyPhoto->update(['is_cover' => true]);
        });

        return $propertyPhoto->fresh();
    }

    public function destroy(Request $request, PropertyPhoto $propertyPhoto)
    {
        $this->ensureOwned($request, $propertyPhoto);
        $cleanupId = DB::transaction(function () use ($propertyPhoto) {
            $cleanupId = app(PrivateFileDeletion::class)->schedule($propertyPhoto->storage_key);
            $propertyId = $propertyPhoto->property_id;
            $wasCover = $propertyPhoto->is_cover;
            $propertyPhoto->delete();
            if ($wasCover) {
                PropertyPhoto::where('property_id', $propertyId)->orderBy('sort_order')->first()?->update(['is_cover' => true]);
            }

            return $cleanupId;
        });
        app(PrivateFileDeletion::class)->process($cleanupId);

        return response()->noContent();
    }

    private function ensureOwned(Request $request, PropertyPhoto $propertyPhoto): void
    {
        abort_unless($propertyPhoto->property->portfolio_id === $request->user()->portfolio()->id, 404);
    }
}
