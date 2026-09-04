<?php

namespace App\Http\Controllers\Api\V1;

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
        $request->validate(['photo' => ['required', 'image', 'max:6144']]);
        $file = $request->file('photo');
        $this->storageUsage->assertCanStore($request->user()->portfolio(), $file->getSize());
        $key = $file->store("portfolios/{$property->portfolio_id}/properties/{$property->id}", 'local');

        $photo = DB::transaction(function () use ($property, $file, $key) {
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

        return Storage::disk('local')->response($propertyPhoto->storage_key, null, ['Content-Type' => $propertyPhoto->mime_type]);
    }
}
