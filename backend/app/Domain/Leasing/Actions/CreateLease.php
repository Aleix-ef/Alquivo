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
    /** Also used to validate AI previews; no contacts, charges or leases are created here. */
    public function validatedData(Portfolio $portfolio, User $user, array $input): array
    {
        abort_unless($portfolio->members()->whereKey($user->id)->wherePivot('role', 'owner')->exists(), 404);
        $money = ['regex:/^\d{1,10}(?:\.\d{1,2})?$/D', 'numeric', 'max:9999999999.99'];
        $data = Validator::make($input, [
            'property_id' => ['required', 'integer'],
            'contact_ids' => ['sometimes', 'array', 'max:20'], 'contact_ids.*' => ['integer', 'distinct'],
            'new_contacts' => ['sometimes', 'array', 'max:20'],
            'new_contacts.*.name' => ['required', 'string', 'max:120'],
            'new_contacts.*.email' => ['nullable', 'email', 'max:255'],
            'new_contacts.*.phone' => ['nullable', 'string', 'max:30'],
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
        $ids = $data['contact_ids'] ?? [];
        $newContacts = $data['new_contacts'] ?? [];
        if (count($ids) + count($newContacts) < 1 || count($ids) + count($newContacts) > 20) {
            throw ValidationException::withMessages(['contact_ids' => 'Selecciona entre 1 y 20 inquilinos.']);
        }
        $contacts = Contact::where('portfolio_id', $portfolio->id)->whereIn('id', $ids)->lockForUpdate()->get();
        if ($contacts->count() !== count($ids)) {
            throw ValidationException::withMessages(['contact_ids' => 'Selecciona inquilinos de esta cartera.']);
        }
        if ($data['status'] === 'active' && $property->leases()->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['property_id' => 'La propiedad ya tiene un arrendamiento activo.']);
        }
        $data['deposit_amount'] ??= '0.00';

        return $data;
    }

    public function execute(Portfolio $portfolio, User $user, array $input): Lease
    {
        return DB::transaction(function () use ($portfolio, $user, $input) {
            $portfolio = Portfolio::whereKey($portfolio->id)->lockForUpdate()->firstOrFail();
            $data = $this->validatedData($portfolio, $user, $input);
            $ids = $data['contact_ids'] ?? [];
            $newContacts = $data['new_contacts'] ?? [];
            foreach ($newContacts as $contact) {
                $ids[] = Contact::create([
                    'portfolio_id' => $portfolio->id,
                    'kind' => 'person',
                    'name' => $contact['name'],
                    'email' => $contact['email'] ?? null,
                    'phone' => $contact['phone'] ?? null,
                ])->id;
            }
            unset($data['contact_ids'], $data['new_contacts']);
            $data['deposit_amount'] ??= '0.00';
            $lease = Lease::create([...$data, 'portfolio_id' => $portfolio->id]);
            $lease->participants()->attach(collect($ids)->mapWithKeys(fn ($id, $i) => [$id => ['role' => 'tenant', 'is_primary' => $i === 0]]));
            app(RentChargeService::class)->ensureCurrentCharge($lease);

            return $lease;
        });
    }
}
