<?php

namespace App\Domain\Assistant\Services;

use App\Domain\Attention\Models\Issue;
use App\Domain\Attention\Services\PortfolioAttention;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Properties\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class PortfolioAssistantTools
{
    public function definitions(): array
    {
        $period = [
            'from' => ['type' => ['string', 'null'], 'description' => 'Fecha inicial YYYY-MM-DD; null para inicio de mes.'],
            'to' => ['type' => ['string', 'null'], 'description' => 'Fecha final YYYY-MM-DD; null para hoy.'],
        ];
        $property = ['property_id' => ['type' => ['integer', 'null'], 'description' => 'Inmueble o null para toda la cartera.']];

        return [
            $this->tool('get_attention_items', 'Lee Qué necesita mi atención: mismos hechos, orden y reglas que el dashboard. No recalcules prioridad, fechas ni saldos. Incluye totales completos y una página de 5 avisos; consulta la siguiente si hace falta. No crea acciones.', [
                ...$property, 'page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10000],
            ], ['property_id', 'page']),
            $this->tool('compare_months', 'Compara cobros, gastos y neto de un mes con el anterior. Calcula diferencias, sin inventar porcentajes con base cero.', [
                'month' => ['type' => ['string', 'null'], 'description' => 'YYYY-MM; null para el mes actual.'], ...$property,
            ], ['month', 'property_id']),
            $this->tool('compare_properties', 'Compara flujo de caja pagado de los inmuebles y rendimiento del periodo, no anualizado.', $period, ['from', 'to']),
            $this->tool('list_movements', 'Muestra hasta 30 movimientos pagados recientes del intervalo. No incluye notas ni datos personales.', [
                ...$period, ...$property,
                'direction' => ['type' => ['string', 'null'], 'enum' => ['income', 'expense', null]],
            ], ['from', 'to', 'property_id', 'direction']),
            $this->tool('get_portfolio_overview', 'Obtiene los principales indicadores actuales de la cartera.', []),
            $this->tool('list_properties', 'Lista los inmuebles de la cartera con valor, deuda, ocupación y renta.', []),
            $this->tool('get_property_details', 'Obtiene la ficha de un inmueble concreto, sus contratos, inquilinos e incidencias.', [
                'property_id' => ['type' => 'integer', 'description' => 'Identificador del inmueble.'],
            ], ['property_id']),
            $this->tool('get_financial_summary', 'Consulta cuánto se ha cobrado, gastado y ganado (neto pagado), en un periodo o en todo el histórico registrado. Es lectura, no prepara un cobro ni exige una mensualidad. Separa las mensualidades pendientes de los ingresos realmente cobrados.', [
                'time_scope' => ['type' => 'string', 'enum' => ['period', 'all_time'], 'description' => 'all_time para el total histórico/en general/desde el principio o totales sin periodo indicado; period para un intervalo solicitado.'],
                'from' => ['type' => ['string', 'null'], 'description' => 'Con period: inicio YYYY-MM-DD o null para inicio del mes actual. Con all_time: null, Laravel obtiene el inicio del histórico.'],
                'to' => ['type' => ['string', 'null'], 'description' => 'Con period: fin YYYY-MM-DD o null para hoy. Con all_time: null, incluye el histórico hasta hoy.'],
                'property_id' => ['type' => ['integer', 'null'], 'description' => 'Identificador de inmueble o null para toda la cartera.'],
            ], ['time_scope', 'from', 'to', 'property_id']),
            $this->tool('get_pending_items', 'Lista asuntos dentro del horizonte de atención del dashboard. En alquileres incluye saldo y nombres de inquilinos del contrato; no representa todos los periodos si quedan fuera de la ventana. Para todos los alquileres pendientes usa list_rent_charges.', [
                'kind' => ['type' => 'string', 'enum' => ['all', 'rents', 'leases', 'issues', 'documents', 'reminders']],
            ], ['kind']),
        ];
    }

    public function execute(Portfolio $portfolio, string $name, array $arguments): array
    {
        $rules = match ($name) {
            'get_attention_items' => ['property_id' => ['nullable', 'integer', 'min:1'], 'page' => ['required', 'integer', 'min:1', 'max:10000']],
            'compare_months' => ['month' => ['nullable', 'date_format:Y-m'], 'property_id' => ['nullable', 'integer', 'min:1']],
            'compare_properties' => ['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d']],
            'list_movements' => ['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'], 'property_id' => ['nullable', 'integer', 'min:1'], 'direction' => ['nullable', Rule::in(['income', 'expense'])]],
            'get_portfolio_overview', 'list_properties' => [],
            'get_property_details' => ['property_id' => ['required', 'integer', 'min:1']],
            'get_financial_summary' => ['time_scope' => ['sometimes', Rule::in(['period', 'all_time'])], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'], 'property_id' => ['nullable', 'integer', 'min:1']],
            'get_pending_items' => ['kind' => ['required', Rule::in(['all', 'rents', 'leases', 'issues', 'documents', 'reminders'])]],
            default => throw new InvalidArgumentException('Herramienta no permitida.'),
        };
        if (array_diff(array_keys($arguments), array_keys($rules))) {
            throw new InvalidArgumentException('Parámetros no permitidos.');
        }
        $arguments = Validator::make($arguments, $rules)->validate();

        return match ($name) {
            'get_attention_items' => $this->attention($portfolio, $arguments),
            'compare_months' => app(AssistantInsights::class)->compare($portfolio, $arguments),
            'compare_properties' => app(AssistantInsights::class)->properties($portfolio, $arguments),
            'list_movements' => app(AssistantInsights::class)->movements($portfolio, $arguments),
            'get_portfolio_overview' => $this->overview($portfolio),
            'list_properties' => $this->properties($portfolio),
            'get_property_details' => $this->propertyDetails($portfolio, (int) ($arguments['property_id'] ?? 0)),
            'get_financial_summary' => $this->finances($portfolio, $arguments),
            'get_pending_items' => $this->pending($portfolio, (string) ($arguments['kind'] ?? 'all')),
            default => throw new InvalidArgumentException('Herramienta no permitida.'),
        };
    }

    private function tool(string $name, string $description, array $properties, array $required = []): array
    {
        return [
            'type' => 'function',
            'name' => $name,
            'description' => $description,
            'parameters' => [
                'type' => 'object',
                'properties' => (object) $properties,
                'required' => $required,
                'additionalProperties' => false,
            ],
            'strict' => true,
        ];
    }

    private function overview(Portfolio $portfolio): array
    {
        $properties = $portfolio->properties()->with(['leases' => fn ($query) => $query->where('status', 'active')])->get();
        $transactions = Transaction::query()->where('portfolio_id', $portfolio->id)
            ->whereDate('transaction_date', '>=', now()->startOfMonth()->toDateString())
            ->whereDate('transaction_date', '<=', today()->toDateString())->get();
        $income = (float) $transactions->where('direction', 'income')->where('status', 'paid')->sum('amount');
        $expenses = (float) $transactions->where('direction', 'expense')->where('status', 'paid')->sum('amount');
        $value = (float) $properties->sum('current_value');
        $debt = (float) $properties->sum('outstanding_debt');
        $monthlyRent = (float) $properties->flatMap->leases->sum('monthly_rent');
        $occupied = $properties->filter(fn (Property $property) => $property->leases->isNotEmpty())->count();

        return [
            'as_of' => today()->toDateString(),
            'currency' => $portfolio->currency,
            'property_count' => $properties->count(),
            'properties_without_valuation' => $properties->whereNull('current_value')->count(),
            'occupied_properties' => $occupied,
            'portfolio_value' => $value,
            'outstanding_debt' => $debt,
            'net_equity' => $value - $debt,
            'current_month' => ['from' => now()->startOfMonth()->toDateString(), 'to' => today()->toDateString(), 'recorded_transaction_count' => $transactions->count(), 'income' => $income, 'expenses' => $expenses, 'net' => round($income - $expenses, 2)],
            'current_month_basis' => 'Solo movimientos pagados registrados del mes; no incluye mensualidades pendientes.',
            'rent_charges_pending_all_periods' => $this->pendingRentSummary($portfolio),
            'contracted_monthly_rent' => $monthlyRent,
            'gross_yield_percent' => $value > 0 ? round($monthlyRent * 12 / $value * 100, 2) : null,
            'occupancy_percent' => $properties->count() ? round($occupied / $properties->count() * 100, 1) : null,
        ];
    }

    private function properties(Portfolio $portfolio): array
    {
        return [
            'currency' => $portfolio->currency,
            'properties' => $portfolio->properties()->with(['leases' => fn ($query) => $query->where('status', 'active')])
                ->orderBy('name')->get()->map(fn (Property $property) => [
                    'id' => $property->id,
                    'name' => $property->name,
                    'type' => $property->type,
                    'city' => $property->city,
                    'current_value' => $property->current_value !== null ? (float) $property->current_value : null,
                    'outstanding_debt' => (float) $property->outstanding_debt,
                    'occupied' => $property->leases->isNotEmpty(),
                    'monthly_rent' => (float) $property->leases->sum('monthly_rent'),
                ])->all(),
        ];
    }

    private function propertyDetails(Portfolio $portfolio, int $propertyId): array
    {
        $property = Property::query()->where('portfolio_id', $portfolio->id)->whereKey($propertyId)
            ->with(['leases.participants', 'issues' => fn ($query) => $query->whereNotIn('status', ['resolved', 'cancelled'])])
            ->first();
        if (! $property) {
            return ['found' => false, 'message' => 'No existe ese inmueble en la cartera del usuario.'];
        }

        return [
            'found' => true,
            'currency' => $portfolio->currency,
            'property' => [
                'id' => $property->id, 'name' => $property->name, 'type' => $property->type,
                'city' => $property->city,
                'purchase_date' => $property->purchase_date?->toDateString(),
                'purchase_price' => $property->purchase_price !== null ? (float) $property->purchase_price : null,
                'current_value' => $property->current_value !== null ? (float) $property->current_value : null,
                'outstanding_debt' => (float) $property->outstanding_debt,
                'area' => $property->area !== null ? (float) $property->area : null,
                'leases' => $property->leases->map(fn (Lease $lease) => [
                    'status' => $lease->status, 'start_date' => $lease->start_date->toDateString(),
                    'end_date' => $lease->end_date?->toDateString(), 'monthly_rent' => (float) $lease->monthly_rent,
                    'tenant_count' => $lease->participants->count(),
                ])->all(),
                'open_issues' => $property->issues->map(fn (Issue $issue) => [
                    'title' => $issue->title, 'status' => $issue->status, 'priority' => $issue->priority,
                    'due_date' => $issue->due_date?->toDateString(),
                ])->all(),
            ],
            'app_path' => '/properties/'.$property->id,
        ];
    }

    private function finances(Portfolio $portfolio, array $arguments): array
    {
        $scope = $arguments['time_scope'] ?? 'period';
        $query = Transaction::query()->where('portfolio_id', $portfolio->id);
        $propertyId = isset($arguments['property_id']) ? (int) $arguments['property_id'] : null;
        if ($propertyId) {
            if (! $portfolio->properties()->whereKey($propertyId)->exists()) {
                return ['found' => false, 'message' => 'No existe ese inmueble en la cartera del usuario.'];
            }
            $query->where('property_id', $propertyId);
        }
        if ($scope === 'all_time') {
            if (isset($arguments['from']) || isset($arguments['to'])) {
                throw new InvalidArgumentException('El total histórico obtiene sus fechas de los registros; no admite un intervalo adicional.');
            }
            $from = null;
            $to = CarbonImmutable::today();
        } else {
            $from = $this->date($arguments['from'] ?? null, now()->startOfMonth()->toDateString());
            $to = $this->date($arguments['to'] ?? null, today()->toDateString());
            if ($from->greaterThan($to) || $from->diffInDays($to) > 1096) {
                throw new InvalidArgumentException('El intervalo debe estar ordenado y no superar tres años.');
            }
            $query->whereDate('transaction_date', '>=', $from->toDateString());
        }
        $query->whereDate('transaction_date', '<=', $to->toDateString());
        // Aggregate in the database: historical totals must not load or expose individual records.
        $groups = $query->select(['direction', 'status', 'category'])
            ->selectRaw('COUNT(*) AS recorded_count, SUM(amount) AS total_amount, MIN(transaction_date) AS first_date')
            ->groupBy('direction', 'status', 'category')->get();
        if ($scope === 'all_time' && $groups->isNotEmpty()) {
            $from = CarbonImmutable::parse($groups->min('first_date'));
        }
        $paid = $groups->where('status', 'paid');
        $income = (float) $paid->where('direction', 'income')->sum('total_amount');
        $expenses = (float) $paid->where('direction', 'expense')->sum('total_amount');

        return [
            'found' => true, 'currency' => $portfolio->currency,
            'time_scope' => $scope, 'from' => $from?->toDateString(), 'to' => $to->toDateString(),
            'basis' => 'income: ingresos cobrados; expenses: gastos pagados; net: income menos expenses, no beneficio fiscal ni revalorización. Solo movimientos pagados registrados del alcance indicado (all_time: todo el histórico hasta hoy, no solo el mes actual). Sin registros no implica ausencia de cobros o gastos reales. other_unpaid_transactions: movimientos sin pagar de ese alcance, NO mensualidades pendientes. rent_charges_pending_all_periods: saldo de mensualidades registradas no canceladas de todos los periodos, no ingreso cobrado ni parte del neto.',
            'recorded_transaction_count' => (int) $groups->sum('recorded_count'),
            'recorded_paid_count' => (int) $paid->sum('recorded_count'),
            'income' => round($income, 2), 'expenses' => round($expenses, 2), 'net' => round($income - $expenses, 2),
            'other_unpaid_transactions' => [
                'income' => (float) $groups->where('direction', 'income')->whereNotIn('status', ['paid', 'cancelled'])->sum('total_amount'),
                'expenses' => (float) $groups->where('direction', 'expense')->whereNotIn('status', ['paid', 'cancelled'])->sum('total_amount'),
            ],
            'rent_charges_pending_all_periods' => $this->pendingRentSummary($portfolio, $propertyId),
            'income_by_category' => $paid->where('direction', 'income')->groupBy('category')->map(fn ($items) => round((float) $items->sum('total_amount'), 2))->all(),
            'expenses_by_category' => $paid->where('direction', 'expense')->groupBy('category')->map(fn ($items) => round((float) $items->sum('total_amount'), 2))->all(),
            'app_path' => '/finance',
        ];
    }

    private function pendingRentSummary(Portfolio $portfolio, ?int $propertyId = null): array
    {
        return app(AssistantLeasingQueries::class)->execute($portfolio, 'list_rent_charges', [
            'property_id' => $propertyId, 'lease_id' => null, 'period' => null, 'status' => 'pending',
        ])['summary'];
    }

    private function pending(Portfolio $portfolio, string $kind): array
    {
        // Compatibility adapter; never a second set of attention rules.
        $snapshot = app(PortfolioAttention::class)->snapshot($portfolio);
        $groups = $kind === 'all' ? array_keys($snapshot['counts']) : [$kind];
        $all = [];
        foreach ($groups as $group) {
            $items = collect($snapshot['items'])->where('group', $group)->take(20);
            $all[$group] = $group === 'rents' ? $items->map(fn ($item) => [
                'property' => $item['property']['name'], 'due_date' => $item['date'], 'period' => $item['evidence']['period'],
                'pending_amount' => $item['amount'], 'tenants' => $item['evidence']['tenants'],
                'tenant_count' => $item['evidence']['tenant_count'], 'tenants_truncated' => $item['evidence']['tenants_truncated'],
                'status' => $item['type'] === 'rent_overdue' ? 'overdue' : $item['evidence']['payment_state'],
            ])->values()->all() : $items->values()->all();
        }

        return [...array_diff_key($snapshot, ['items' => true]), 'list_limit' => 20, ...$all];
    }

    private function attention(Portfolio $portfolio, array $arguments): array
    {
        $snapshot = app(PortfolioAttention::class)->snapshot($portfolio, $arguments['property_id'] ?? null);
        $page = $arguments['page'];
        $snapshot['items'] = array_slice($snapshot['items'], ($page - 1) * 5, 5);

        return [...$snapshot, 'page' => $page, 'page_size' => 5, 'has_more' => $snapshot['total'] > $page * 5];
    }

    private function date(?string $value, string $fallback): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value ?: $fallback)->startOfDay();
        } catch (\Throwable) {
            throw new InvalidArgumentException('La fecha debe tener el formato YYYY-MM-DD.');
        }
    }
}
