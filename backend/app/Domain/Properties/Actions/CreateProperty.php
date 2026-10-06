<?php

namespace App\Domain\Properties\Actions;

use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Portfolio\Services\PlanService;
use App\Domain\Properties\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Shared manual/assistant boundary. Validation never creates a record. */
final class CreateProperty
{
    public const TYPES = ['housing', 'commercial', 'office', 'garage', 'storage', 'land', 'building', 'other'];

    public static function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:120'],
            'type' => [$required, Rule::in(self::TYPES)],
            'address_line' => [$required, 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:12'], 'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'], 'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'], 'acquisition_costs' => ['nullable', 'numeric', 'min:0'],
            'current_value' => ['nullable', 'numeric', 'min:0'], 'valuation_date' => ['nullable', 'date'],
            'outstanding_debt' => ['nullable', 'numeric', 'min:0'], 'area' => ['nullable', 'numeric', 'min:0'],
            'bedrooms' => ['nullable', 'integer', 'min:0'], 'bathrooms' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function validatedData(Portfolio $portfolio, User $user, array $input): array
    {
        abort_unless($portfolio->members()->whereKey($user->id)->wherePivot('role', 'owner')->exists(), 404);
        app(PlanService::class)->assertCanCreateProperty($portfolio);
        foreach (['name', 'address_line', 'city', 'province', 'postal_code'] as $key) {
            if (isset($input[$key]) && is_string($input[$key])) {
                $input[$key] = trim($input[$key]);
            }
        }

        return Validator::make($input, self::rules())->validate();
    }

    public function execute(Portfolio $portfolio, User $user, array $input): Property
    {
        return DB::transaction(function () use ($portfolio, $user, $input) {
            $portfolio = Portfolio::whereKey($portfolio->id)->lockForUpdate()->firstOrFail();
            $data = $this->validatedData($portfolio, $user, $input);
            $property = $portfolio->properties()->create($data);
            if (isset($data['current_value'])) {
                $property->valuations()->create([
                    'amount' => $data['current_value'], 'valued_at' => $data['valuation_date'] ?? today(), 'source' => 'owner',
                ]);
            }

            return $property;
        });
    }
}
