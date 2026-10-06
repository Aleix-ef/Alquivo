<?php

namespace App\Domain\Assistant\Services;

use App\Domain\Leasing\Actions\CreateContact;
use App\Domain\Leasing\Actions\CreateLease;
use App\Domain\Leasing\Models\Contact;
use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Properties\Actions\CreateProperty;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Only typed, reviewable creation data. Never model-supplied code, roles or portfolio IDs. */
final class CreationProposalActions
{
    public const FIELDS = [
        'property_create' => ['name', 'type', 'address_line', 'city', 'purchase_price', 'current_value'],
        'contact_create' => ['name', 'kind', 'email', 'phone'],
        'lease_create' => ['property_id', 'contact_ids', 'status', 'start_date', 'end_date', 'monthly_rent', 'deposit_amount', 'payment_day'],
    ];

    public function validatedData(string $type, Portfolio $portfolio, User $user, array $input): array
    {
        app(AiCapabilities::class)->assertCreations($portfolio, $user);
        app(ProposalActionRegistry::class)->assertFields($input, self::FIELDS[$type]);
        $money = ['nullable', 'string', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/D', 'numeric', 'min:0', 'max:9999999999.99'];
        if ($type === 'property_create') {
            Validator::make($input, ['purchase_price' => $money, 'current_value' => $money])->validate();
            $data = app(CreateProperty::class)->validatedData($portfolio, $user, $input);
        } elseif ($type === 'contact_create') {
            $data = app(CreateContact::class)->validatedData($portfolio, $user, $input);
        } else {
            Validator::make($input, ['status' => ['required', Rule::in(['draft'])]])->validate();
            $data = app(CreateLease::class)->validatedData($portfolio, $user, $input);
            // Keep canonical strings in the encrypted preview and receipt hash.
            foreach (['monthly_rent', 'deposit_amount'] as $key) {
                $data[$key] = bcadd((string) $data[$key], '0', 2);
            }
        }
        if ($type !== 'lease_create' && $this->matchingIds($type, $portfolio, $data['name']) !== []) {
            throw ValidationException::withMessages(['name' => 'Ya existe un registro con ese nombre. Revisa Inmuebles o Personas antes de crear otro; no he preparado un duplicado.']);
        }
        foreach (['purchase_price', 'current_value'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = bcadd((string) $data[$key], '0', 2);
            }
        }

        return $data;
    }

    public function snapshot(string $type, Portfolio $portfolio, array $payload): array
    {
        if ($type !== 'lease_create') {
            return ['currency' => $portfolio->currency, 'matching_ids' => $this->matchingIds($type, $portfolio, $payload['name'])];
        }
        $property = $portfolio->properties()->whereKey($payload['property_id'])->lockForUpdate()->firstOrFail();
        $contacts = Contact::where('portfolio_id', $portfolio->id)->whereIn('id', $payload['contact_ids'])->orderBy('id')
            ->lockForUpdate()->get(['id', 'name', 'updated_at']);
        abort_unless($contacts->count() === count($payload['contact_ids']), 409, 'Los inquilinos han cambiado. Prepara una nueva propuesta.');
        // Lock in a deterministic order, but keep the reviewed participant order (first = primary).
        $contactsById = $contacts->keyBy('id');

        return ['currency' => $portfolio->currency,
            'property' => ['id' => $property->id, 'name' => $property->name, 'updated_at' => $property->updated_at?->toISOString()],
            'contacts' => array_map(fn ($id) => ['id' => $id, 'name' => $contactsById[$id]->name, 'updated_at' => $contactsById[$id]->updated_at?->toISOString()], $payload['contact_ids']),
        ];
    }

    public function preview(string $type, array $payload, array $snapshot): array
    {
        if ($type === 'lease_create') {
            return [...$payload, 'currency' => $snapshot['currency'], 'property' => $snapshot['property'],
                'contacts' => array_map(fn ($contact) => ['id' => $contact['id'], 'name' => $contact['name']], $snapshot['contacts'])];
        }

        return [...$payload, 'currency' => $snapshot['currency']];
    }

    public function execute(string $type, Portfolio $portfolio, User $user, array $payload): array
    {
        if ($type === 'property_create') {
            $property = app(CreateProperty::class)->execute($portfolio, $user, $payload);

            return ['property_id' => $property->id, 'path' => '/properties/'.$property->id];
        }
        if ($type === 'contact_create') {
            $contact = app(CreateContact::class)->execute($portfolio, $user, $payload);

            return ['contact_id' => $contact->id, 'path' => '/contacts'];
        }
        $lease = app(CreateLease::class)->execute($portfolio, $user, $payload);

        return ['lease_id' => $lease->id, 'property_id' => $lease->property_id, 'path' => '/leases/'.$lease->id];
    }

    private function matchingIds(string $type, Portfolio $portfolio, string $name): array
    {
        $query = $type === 'property_create' ? $portfolio->properties() : Contact::where('portfolio_id', $portfolio->id);

        return $query->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->orderBy('id')->pluck('id')->all();
    }
}
