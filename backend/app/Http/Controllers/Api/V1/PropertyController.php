<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Attention\Models\Issue;
use App\Domain\Attention\Models\Reminder;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Services\PrivateFileDeletion;
use App\Domain\Finance\Models\RecurringRule;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Portfolio\Services\PlanService;
use App\Domain\Properties\Models\Property;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PropertyController extends Controller
{
    public function __construct(private readonly PlanService $plans) {}

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
        $data = $request->validate($this->rules());
        $property = DB::transaction(function () use ($request, $data) {
            $portfolio = Portfolio::whereKey($this->portfolio($request)->id)->lockForUpdate()->firstOrFail();
            $this->plans->assertCanCreateProperty($portfolio);
            $property = $portfolio->properties()->create($data);
            if (isset($data['current_value'])) {
                $property->valuations()->create([
                    'amount' => $data['current_value'], 'valued_at' => $data['valuation_date'] ?? today(), 'source' => 'owner',
                ]);
            }

            return $property;
        });

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
        $required = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:120'],
            'type' => [$required, Rule::in(['housing', 'commercial', 'office', 'garage', 'storage', 'land', 'building', 'other'])],
            'address_line' => [$required, 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:12'], 'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'], 'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'], 'acquisition_costs' => ['nullable', 'numeric', 'min:0'],
            'current_value' => ['nullable', 'numeric', 'min:0'], 'valuation_date' => ['nullable', 'date'],
            'outstanding_debt' => ['nullable', 'numeric', 'min:0'], 'area' => ['nullable', 'numeric', 'min:0'],
            'bedrooms' => ['nullable', 'integer', 'min:0'], 'bathrooms' => ['nullable', 'integer', 'min:0'], 'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
