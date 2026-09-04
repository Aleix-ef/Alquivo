<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finance\Services\RentChargeService;
use App\Domain\Leasing\Models\Contact;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Properties\Models\Property;
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
        $portfolio = $request->user()->portfolio();
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'contact_ids' => ['required', 'array', 'min:1'], 'contact_ids.*' => ['integer', 'distinct'],
            'status' => ['required', Rule::in(['draft', 'active'])],
            'start_date' => ['required', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'monthly_rent' => ['required', 'numeric', 'gt:0'], 'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_day' => ['required', 'integer', 'between:1,28'], 'notes' => ['nullable', 'string'],
        ]);

        $property = Property::where('portfolio_id', $portfolio->id)->findOrFail($data['property_id']);
        $contacts = Contact::where('portfolio_id', $portfolio->id)->whereIn('id', $data['contact_ids'])->get();
        if ($contacts->count() !== count($data['contact_ids'])) {
            throw ValidationException::withMessages(['contact_ids' => ['Algún inquilino no pertenece a esta cartera.']]);
        }
        if ($data['status'] === 'active' && $property->leases()->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['property_id' => ['La propiedad ya tiene un arrendamiento activo.']]);
        }

        $lease = DB::transaction(function () use ($data, $portfolio) {
            $contacts = $data['contact_ids'];
            unset($data['contact_ids']);
            $lease = Lease::create([...$data, 'portfolio_id' => $portfolio->id]);
            $lease->participants()->attach(collect($contacts)->mapWithKeys(
                fn ($id, $index) => [$id => ['role' => 'tenant', 'is_primary' => $index === 0]],
            ));
            $this->charges->ensureCurrentCharge($lease);

            return $lease;
        });

        return response()->json($lease->load(['property', 'participants', 'charges']), 201);
    }

    public function update(Request $request, Lease $lease)
    {
        $portfolio = $request->user()->portfolio();
        $this->ensureOwned($request, $lease);
        $data = $request->validate([
            'contact_ids' => ['sometimes', 'array', 'min:1'], 'contact_ids.*' => ['integer', 'distinct'],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'ended', 'cancelled'])],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:'.($request->input('start_date') ?: $lease->start_date->toDateString())],
            'monthly_rent' => ['sometimes', 'numeric', 'gt:0'],
            'deposit_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'payment_day' => ['sometimes', 'integer', 'between:1,28'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);
        if (($data['status'] ?? $lease->status) === 'active' && Lease::where('property_id', $lease->property_id)
            ->where('status', 'active')->whereKeyNot($lease->id)->exists()) {
            throw ValidationException::withMessages(['status' => ['La propiedad ya tiene otro arrendamiento activo.']]);
        }
        if (($data['status'] ?? null) === 'ended' && empty($data['end_date'])) {
            $data['end_date'] = today();
        }
        $contacts = $data['contact_ids'] ?? null;
        unset($data['contact_ids']);
        if ($contacts) {
            $validContacts = Contact::where('portfolio_id', $portfolio->id)->whereIn('id', $contacts)->count();
            if ($validContacts !== count($contacts)) {
                throw ValidationException::withMessages(['contact_ids' => ['Algún inquilino no pertenece a esta cartera.']]);
            }
        }
        DB::transaction(function () use ($lease, $data, $contacts) {
            $lease->update($data);
            if ($contacts) {
                $lease->participants()->sync(collect($contacts)->mapWithKeys(
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
            'notes' => ['nullable', 'string'],
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
