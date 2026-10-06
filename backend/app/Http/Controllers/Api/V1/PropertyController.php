<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Attention\Models\Issue;
use App\Domain\Attention\Models\Reminder;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Services\PrivateFileDeletion;
use App\Domain\Finance\Models\RecurringRule;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Properties\Actions\CreateProperty;
use App\Domain\Properties\Models\Property;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyController extends Controller
{
    private function portfolio(Request $request)
    {
        return $request->user()->portfolio();
    }

    public function index(Request $request)
    {
        return $this->portfolio($request)->properties()->with(['leases.participants', 'photos'])->latest()->paginate(18);
    }

    public function store(Request $request)
    {
        $property = app(CreateProperty::class)->execute($this->portfolio($request), $request->user(), $request->all());

        return response()->json($property, 201);
    }

    public function show(Request $request, Property $property)
    {
        abort_unless($property->portfolio_id === $this->portfolio($request)->id, 404);

        return $property->load(['photos', 'valuations', 'leases.participants', 'leases.charges', 'transactions', 'issues', 'documents']);
    }

    public function update(Request $request, Property $property)
    {
        abort_unless($property->portfolio_id === $this->portfolio($request)->id, 404);
        $data = $request->validate($this->rules(true));
        DB::transaction(function () use ($property, $data) {
            $valueChanged = array_key_exists('current_value', $data) && (float) $data['current_value'] !== (float) $property->current_value;
            $property->update($data);
            if ($valueChanged && $data['current_value'] !== null) {
                $property->valuations()->create([
                    'amount' => $data['current_value'], 'valued_at' => $data['valuation_date'] ?? today(), 'source' => 'owner',
                ]);
            }
        });

        return $property->fresh();
    }

    public function destroy(Request $request, Property $property)
    {
        abort_unless($property->portfolio_id === $this->portfolio($request)->id, 404);

        $cleanupIds = DB::transaction(function () use ($property) {
            $property = Property::whereKey($property->id)->lockForUpdate()->firstOrFail();
            $hasHistory = Lease::where('property_id', $property->id)->exists()
                || Transaction::where('property_id', $property->id)->exists()
                || RecurringRule::where('property_id', $property->id)->exists()
                || Issue::where('property_id', $property->id)->exists()
                || Document::where('property_id', $property->id)->exists()
                || Reminder::where('property_id', $property->id)->exists();
            abort_if($hasHistory, 422, 'Esta propiedad tiene historial. Conserva sus datos y finaliza primero cualquier gestión vinculada.');

            $deletions = app(PrivateFileDeletion::class);
            $ids = $property->photos->map(fn ($photo) => $deletions->schedule($photo->storage_key))->all();
            $property->photos->each->delete();
            $property->delete();

            return $ids;
        });
        foreach ($cleanupIds as $cleanupId) {
            app(PrivateFileDeletion::class)->process($cleanupId);
        }

        return response()->noContent();
    }

    private function rules(bool $partial = false): array
    {
        return CreateProperty::rules($partial);
    }
}
