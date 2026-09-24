<?php

namespace App\Domain\Assistant\Services;

use App\Domain\Finance\Queries\RentChargeBalances;
use App\Domain\Leasing\Models\Contact;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/** Allowlisted projections only: no tax IDs, emails, stored phones, notes or documents. */
final class AssistantLeasingQueries
{
    public function definitions(): array
    {
        $property = ['property_id' => ['type' => ['integer', 'null'], 'description' => 'ID del inmueble autorizado, o null para toda la cartera.']];

        return [
            $this->definition('search_contacts', 'Busca contactos por nombre e inmueble. No devuelve teléfonos guardados, correos ni DNI. Una coincidencia única es obligatoria antes de proponer un cambio de teléfono.', [
                'query' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 120], ...$property,
            ]),
            $this->definition('list_rent_charges', 'Consulta mensualidades existentes y su saldo registrado. No crea cargos. Antes de proponer un cobro, filtra hasta obtener una única mensualidad pendiente. No sumes la muestra: usa summary.', [
                ...$property, 'lease_id' => ['type' => ['integer', 'null']],
                'period' => ['type' => ['string', 'null'], 'description' => 'Mes YYYY-MM o null para todos. No inventes el periodo si hay varias mensualidades.'],
                'status' => ['type' => 'string', 'enum' => ['pending', 'paid', 'all']],
            ]),
            $this->definition('list_leases', 'Lista contratos y vencimientos con un total completo. Las fechas ausentes no se estiman.', [
                ...$property, 'status' => ['type' => 'string', 'enum' => ['active', 'draft', 'ended', 'cancelled', 'all']],
                'expires_within_days' => ['type' => ['integer', 'null'], 'minimum' => 1, 'maximum' => 365, 'description' => 'Días desde hoy para filtrar vencimientos, o null para todos.'],
            ]),
            $this->definition('get_lease_details', 'Lee fechas, renta, fianza, participantes y saldo de un contrato registrado. No lee cláusulas, documentos ni notas privadas.', [
                'lease_id' => ['type' => 'integer', 'minimum' => 1],
            ]),
        ];
    }

    public function execute(Portfolio $portfolio, string $name, array $arguments): array
    {
        $property = ['property_id' => ['nullable', 'integer', 'min:1']];
        $rules = match ($name) {
            'search_contacts' => ['query' => ['required', 'string', 'min:2', 'max:120'], ...$property],
            'list_rent_charges' => [...$property, 'lease_id' => ['nullable', 'integer', 'min:1'], 'period' => ['nullable', 'date_format:Y-m'], 'status' => ['required', Rule::in(['pending', 'paid', 'all'])]],
            'list_leases' => [...$property, 'status' => ['required', Rule::in(['active', 'draft', 'ended', 'cancelled', 'all'])], 'expires_within_days' => ['nullable', 'integer', 'between:1,365']],
            'get_lease_details' => ['lease_id' => ['required', 'integer', 'min:1']],
            default => throw new InvalidArgumentException('Herramienta no permitida.'),
        };
        if (array_diff(array_keys($arguments), array_keys($rules))) {
            throw new InvalidArgumentException('Parámetros no permitidos.');
        }
        $data = Validator::make($arguments, $rules)->validate();
        if (! empty($data['property_id'])) {
            $portfolio->properties()->findOrFail($data['property_id']);
        }

        return match ($name) {
            'search_contacts' => $this->contacts($portfolio, $data),
            'list_rent_charges' => $this->charges($portfolio, $data),
            'list_leases' => $this->leases($portfolio, $data),
            'get_lease_details' => $this->lease($portfolio, $data['lease_id']),
        };
    }

    private function contacts(Portfolio $portfolio, array $data): array
    {
        $needle = mb_strtolower(Str::ascii(trim($data['query'])));
        if (mb_strlen($needle) < 2) {
            throw new InvalidArgumentException('Concreta el nombre del contacto.');
        }
        $query = Contact::where('portfolio_id', $portfolio->id);
        if (! empty($data['property_id'])) {
            $query->whereHas('leases', fn ($q) => $q->where('portfolio_id', $portfolio->id)->where('property_id', $data['property_id']));
        }
        // Bounded accent-independent matching. A truncated scan can never resolve a write target.
        $rows = $query->orderBy('id')->limit(1001)->get(['id', 'name', 'kind']);
        $scanTruncated = $rows->count() > 1000;
        $words = preg_split('/\s+/', $needle);
        $matches = $rows->take(1000)->filter(fn ($contact) => collect($words)->every(fn ($word) => str_contains(mb_strtolower(Str::ascii($contact->name)), $word)))->values();
        // Do not prefer an exact name over other people sharing that name plus a surname.
        $visible = $matches->take(20);
        $visible->load(['leases' => fn ($q) => $q->where('portfolio_id', $portfolio->id)->whereHas('property', fn ($p) => $p->where('portfolio_id', $portfolio->id))->with('property:id,name')]);

        return ['contacts' => $visible->map(fn ($contact) => [
            'id' => $contact->id, 'name' => $contact->name, 'kind' => $contact->kind,
            'properties' => $contact->leases->map(fn ($lease) => ['id' => $lease->property->id, 'name' => $lease->property->name])->unique('id')->values()->take(10)->all(),
        ])->all(), 'count' => $scanTruncated ? null : $matches->count(), 'truncated' => $scanTruncated || $matches->count() > 20,
            'needs_clarification' => $scanTruncated || $matches->count() !== 1, 'app_path' => '/contacts'];
    }

    private function leaseQuery(Portfolio $portfolio)
    {
        return Lease::where('portfolio_id', $portfolio->id)
            ->whereHas('property', fn ($query) => $query->where('portfolio_id', $portfolio->id));
    }

    private function leases(Portfolio $portfolio, array $data): array
    {
        $query = $this->leaseQuery($portfolio)->with('property:id,name');
        if (! empty($data['property_id'])) {
            $query->where('property_id', $data['property_id']);
        }
        if ($data['status'] !== 'all') {
            $query->where('status', $data['status']);
        }
        if (! empty($data['expires_within_days'])) {
            $query->whereDate('end_date', '>=', today()->toDateString())
                ->whereDate('end_date', '<=', today()->addDays($data['expires_within_days'])->toDateString());
        }
        $count = (clone $query)->count();

        return ['leases' => $query->orderBy('end_date')->orderBy('id')->limit(20)->get()->map(fn ($lease) => $this->leaseProjection($lease))->all(),
            'count' => $count, 'truncated' => $count > 20, 'currency' => $portfolio->currency, 'as_of' => today()->toDateString(), 'app_path' => '/leases'];
    }

    private function lease(Portfolio $portfolio, int $id): array
    {
        $lease = $this->leaseQuery($portfolio)->with(['property:id,name', 'participants' => fn ($q) => $q->where('portfolio_id', $portfolio->id)])->findOrFail($id);

        return ['lease' => [...$this->leaseProjection($lease), 'deposit_amount' => $lease->deposit_amount, 'payment_day' => $lease->payment_day,
            'participants' => $lease->participants->take(20)->map(fn ($person) => ['id' => $person->id, 'name' => $person->name, 'role' => $person->pivot->role])->all(),
            'participant_count' => $lease->participants->count(), 'participants_truncated' => $lease->participants->count() > 20],
            'rent_summary' => $this->charges($portfolio, ['lease_id' => $id, 'status' => 'all'])['summary'],
            'currency' => $portfolio->currency, 'app_path' => '/leases/'.$id];
    }

    private function leaseProjection(Lease $lease): array
    {
        return ['id' => $lease->id, 'property' => ['id' => $lease->property_id, 'name' => $lease->property->name],
            'status' => $lease->status, 'start_date' => $lease->start_date?->toDateString(),
            'end_date' => $lease->end_date?->toDateString(), 'monthly_rent' => $lease->monthly_rent];
    }

    private function charges(Portfolio $portfolio, array $data): array
    {
        if (! empty($data['lease_id'])) {
            $this->leaseQuery($portfolio)->findOrFail($data['lease_id']);
        }
        $balances = app(RentChargeBalances::class);
        $paid = $balances->paid($portfolio);
        $query = $balances->charges($portfolio, $data['property_id'] ?? null);
        if (! empty($data['lease_id'])) {
            $query->where('lease_id', $data['lease_id']);
        }
        if (! empty($data['period'])) {
            $query->where('period', $data['period']);
        }
        if ($data['status'] === 'pending') {
            $query->where('status', '!=', 'cancelled')->where('amount', '>', clone $paid);
        } elseif ($data['status'] === 'paid') {
            $query->where('status', '!=', 'cancelled')->where('amount', '<=', clone $paid);
        } else {
            $query->where('status', '!=', 'cancelled');
        }
        $totals = DB::query()->fromSub((clone $query)->select(['amount', 'due_date'])->selectSub(clone $paid, 'recorded_paid'), 'matched')
            ->selectRaw('COUNT(*) AS total_count, COALESCE(SUM(amount), 0) AS total_amount, COALESCE(SUM(recorded_paid), 0) AS paid_amount, COALESCE(SUM(CASE WHEN amount > recorded_paid THEN amount - recorded_paid ELSE 0 END), 0) AS remaining_amount, COALESCE(SUM(CASE WHEN amount > recorded_paid AND due_date < ? THEN 1 ELSE 0 END), 0) AS overdue_count', [today()->toDateString()])->first();
        $charges = $query->select('rent_charges.*')->selectSub($paid, 'recorded_paid')->with('lease.property')->orderBy('due_date')->orderBy('id')->limit(20)->get();

        return ['charges' => $charges->map(function ($charge) {
            $remaining = bcsub($charge->amount, (string) $charge->recorded_paid, 2);

            return ['id' => $charge->id, 'lease_id' => $charge->lease_id, 'property' => ['id' => $charge->lease->property_id, 'name' => $charge->lease->property->name],
                'period' => $charge->period, 'due_date' => $charge->due_date->toDateString(), 'amount' => $charge->amount,
                'paid_amount' => bcadd((string) $charge->recorded_paid, '0', 2), 'remaining_amount' => bccomp($remaining, '0', 2) > 0 ? $remaining : '0.00',
                'status' => bccomp($remaining, '0', 2) <= 0 ? 'paid' : ($charge->due_date->lt(today()) ? 'overdue' : (bccomp((string) $charge->recorded_paid, '0', 2) > 0 ? 'partial' : 'pending'))];
        })->all(), 'count' => (int) $totals->total_count, 'truncated' => (int) $totals->total_count > 20, 'currency' => $portfolio->currency,
            'summary' => ['count' => (int) $totals->total_count, 'overdue_count' => (int) $totals->overdue_count, 'amount' => bcadd((string) $totals->total_amount, '0', 2),
                'paid_amount' => bcadd((string) $totals->paid_amount, '0', 2), 'remaining_amount' => bcadd((string) $totals->remaining_amount, '0', 2)],
            'app_path' => ! empty($data['lease_id']) ? '/leases/'.$data['lease_id'] : '/finance'];
    }

    private function definition(string $name, string $description, array $properties): array
    {
        return ['type' => 'function', 'name' => $name, 'description' => $description, 'strict' => true,
            'parameters' => ['type' => 'object', 'properties' => $properties, 'required' => array_keys($properties), 'additionalProperties' => false]];
    }
}
