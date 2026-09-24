<?php

namespace App\Domain\Leasing\Actions;

use App\Domain\Finance\Services\RentChargeService;
use App\Domain\Leasing\Models\Contact;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Portfolio\Services\PropertyAccess;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Shared manual/import boundary. Import callers create draft leases, never implicit charges. */
final class CreateLease
{
    public function execute(Portfolio $portfolio, User $user, array $input): Lease
    {
        return DB::transaction(function () use ($portfolio, $user, $input) {
            $portfolio = Portfolio::whereKey($portfolio->id)->lockForUpdate()->firstOrFail();
            abort_unless($portfolio->members()->whereKey($user->id)->wherePivot('role', 'owner')->exists(), 404);
            $money = ['regex:/^\d{1,10}(?:\.\d{1,2})?$/D', 'numeric', 'max:9999999999.99'];
            $data = Validator::make($input, [
                'property_id' => ['required', 'integer'],
                'contact_ids' => ['required', 'array', 'min:1', 'max:20'], 'contact_ids.*' => ['integer', 'distinct'],
                'status' => ['required', Rule::in(['draft', 'active'])],
                'start_date' => ['required', 'date_format:Y-m-d'],
                'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
                'monthly_rent' => ['required', ...$money, 'gt:0'],
                'deposit_amount' => ['nullable', ...$money, 'min:0'],
                'payment_day' => ['required', 'integer', 'between:1,28'],
                'notes' => ['nullable', 'string', 'max:10000'],
            ])->validate();
            app(PropertyAccess::class)->assertWritable($portfolio, (int) $data['property_id']);
            $property = $portfolio->properties()->whereKey($data['property_id'])->lockForUpdate()->firstOrFail();
            $contacts = Contact::where('portfolio_id', $portfolio->id)->whereIn('id', $data['contact_ids'])->lockForUpdate()->get();
            if ($contacts->count() !== count($data['contact_ids'])) {
                throw ValidationException::withMessages(['contact_ids' => 'Selecciona inquilinos de esta cartera.']);
            }
            if ($data['status'] === 'active' && $property->leases()->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['property_id' => 'La propiedad ya tiene un arrendamiento activo.']);
            }
            $ids = $data['contact_ids'];
            unset($data['contact_ids']);
            $data['deposit_amount'] ??= '0.00';
            $lease = Lease::create([...$data, 'portfolio_id' => $portfolio->id]);
            $lease->participants()->attach(collect($ids)->mapWithKeys(fn ($id, $i) => [$id => ['role' => 'tenant', 'is_primary' => $i === 0]]));
            app(RentChargeService::class)->ensureCurrentCharge($lease);

            return $lease;
        });
    }
}
