<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finance\Services\RentChargeService;
use App\Domain\Leasing\Actions\CreateLease;
use App\Domain\Leasing\Models\Contact;
use App\Domain\Leasing\Models\Lease;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeaseController extends Controller
{
    public function __construct(private readonly RentChargeService $charges) {}

    public function index(Request $request)
    {
        return Lease::where('portfolio_id', $request->user()->portfolio()->id)
            ->with(['property', 'participants', 'charges', 'renewal'])->latest()->paginate(30);
    }

    public function show(Request $request, Lease $lease)
    {
        $this->ensureOwned($request, $lease);

        return $lease->load([
            'property', 'participants', 'charges.transactions', 'documents', 'renewedFrom', 'renewal',
        ])->loadCount('charges');
    }

    public function store(Request $request)
    {
        $lease = app(CreateLease::class)->execute($request->user()->portfolio(), $request->user(), $request->all());

        return response()->json($lease->load(['property', 'participants', 'charges']), 201);
    }

    public function update(Request $request, Lease $lease)
    {
        $portfolio = $request->user()->portfolio();
        $this->ensureOwned($request, $lease);
        $data = $request->validate([
            'contact_ids' => ['sometimes', 'array', 'max:20'], 'contact_ids.*' => ['integer', 'distinct'],
            'new_contacts' => ['sometimes', 'array', 'max:20'],
            'new_contacts.*.name' => ['required', 'string', 'max:120'],
            'new_contacts.*.email' => ['nullable', 'email', 'max:255'],
            'new_contacts.*.phone' => ['nullable', 'string', 'max:30'],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'ended', 'cancelled'])],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:'.($request->input('start_date') ?: $lease->start_date->toDateString())],
            'monthly_rent' => ['sometimes', 'numeric', 'gt:0'],
            'deposit_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'payment_day' => ['sometimes', 'integer', 'between:1,28'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);
        if (($data['status'] ?? $lease->status) === 'active' && Lease::where('property_id', $lease->property_id)
            ->where('status', 'active')->whereKeyNot($lease->id)->exists()) {
            throw ValidationException::withMessages(['status' => ['La propiedad ya tiene otro arrendamiento activo.']]);
        }
        if (($data['status'] ?? null) === 'ended' && empty($data['end_date'])) {
            $data['end_date'] = today();
        }
        $contacts = $data['contact_ids'] ?? null;
        $newContacts = $data['new_contacts'] ?? [];
        unset($data['contact_ids'], $data['new_contacts']);
        if ($contacts !== null && (count($contacts) + count($newContacts) < 1 || count($contacts) + count($newContacts) > 20)) {
            throw ValidationException::withMessages(['contact_ids' => ['Selecciona entre 1 y 20 inquilinos.']]);
        }
        if ($contacts === null && count($newContacts) + $lease->participants()->count() > 20) {
            throw ValidationException::withMessages(['new_contacts' => ['Un contrato admite hasta 20 inquilinos.']]);
        }
        DB::transaction(function () use ($lease, $portfolio, $data, $contacts, $newContacts) {
            $ids = $contacts ?? ($newContacts ? $lease->participants()->pluck('contacts.id')->all() : null);
            if ($ids !== null) {
                // Lock contact rows, not an aggregate (unsupported by PostgreSQL).
                $validContacts = Contact::where('portfolio_id', $portfolio->id)->whereIn('id', $ids)->lockForUpdate()->get(['id']);
                if ($validContacts->count() !== count($ids)) {
                    throw ValidationException::withMessages(['contact_ids' => ['Algún inquilino no pertenece a esta cartera.']]);
                }
            }
            foreach ($newContacts as $contact) {
                $ids[] = Contact::create([
                    'portfolio_id' => $portfolio->id,
                    'kind' => 'person',
                    'name' => $contact['name'],
                    'email' => $contact['email'] ?? null,
                    'phone' => $contact['phone'] ?? null,
                ])->id;
            }
            $lease->update($data);
            if ($ids !== null) {
                $lease->participants()->sync(collect($ids)->mapWithKeys(
                    fn ($id, $index) => [$id => ['role' => 'tenant', 'is_primary' => $index === 0]],
                ));
            }
        });
        $this->charges->ensureCurrentCharge($lease);

        return $lease->fresh()->load(['property', 'participants', 'charges', 'renewal']);
    }

    public function renew(Request $request, Lease $lease)
    {
        $this->ensureOwned($request, $lease);
        abort_if($lease->renewal()->exists(), 422, 'Este contrato ya tiene una renovación preparada.');
        $data = $request->validate([
            'start_date' => ['required', 'date', 'after:'.$lease->start_date->toDateString()],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'monthly_rent' => ['required', 'numeric', 'gt:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_day' => ['required', 'integer', 'between:1,28'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
        $renewal = DB::transaction(function () use ($lease, $data) {
            $renewal = Lease::create([
                ...$data, 'portfolio_id' => $lease->portfolio_id, 'property_id' => $lease->property_id,
                'renewed_from_id' => $lease->id, 'status' => 'draft',
            ]);
            $renewal->participants()->attach($lease->participants->mapWithKeys(fn ($contact) => [
                $contact->id => ['role' => $contact->pivot->role, 'is_primary' => $contact->pivot->is_primary],
            ]));

            return $renewal;
        });

        return response()->json($renewal->load(['property', 'participants']), 201);
    }

    private function ensureOwned(Request $request, Lease $lease): void
    {
        abort_unless($lease->portfolio_id === $request->user()->portfolio()->id, 404);
    }
}
