<?php

namespace App\Domain\Assistant\Services;

use App\Domain\Finance\Models\Transaction;
use App\Domain\Portfolio\Models\Portfolio;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** Read-only calculations. The model explains these figures; it never computes them. */
final class AssistantInsights
{
    public function compare(Portfolio $portfolio, array $arguments): array
    {
        $month = CarbonImmutable::createFromFormat('!Y-m', $arguments['month'] ?? today()->format('Y-m'));
        if ($month->isFuture()) {
            throw new InvalidArgumentException('Elige un mes actual o pasado.');
        }
        $end = $month->isSameMonth(today()) ? CarbonImmutable::today() : $month->endOfMonth();
        $previous = $month->subMonth();
        // Compare equal calendar progress for an unfinished month, with month-end clamping.
        $previousEnd = $month->isSameMonth(today())
            ? $previous->day(min($end->day, $previous->daysInMonth))
            : $previous->endOfMonth();
        $current = $this->totals($this->query($portfolio, $month, $end, $arguments)->get());
        $before = $this->totals($this->query($portfolio, $previous, $previousEnd, $arguments)->get());

        return [
            'currency' => $portfolio->currency, 'app_path' => '/finance',
            'basis' => 'Movimientos pagados registrados. No es una previsión ni una estimación fiscal.',
            'partial_month' => $month->isSameMonth(today()),
            'current' => ['from' => $month->toDateString(), 'to' => $end->toDateString(), ...$current],
            'previous' => ['from' => $previous->toDateString(), 'to' => $previousEnd->toDateString(), ...$before],
            'change' => collect(['income', 'expenses', 'net'])->mapWithKeys(fn ($key) => [$key => [
                'amount' => round($current[$key] - $before[$key], 2),
                'percent' => $before[$key] > 0 ? round(($current[$key] - $before[$key]) / $before[$key] * 100, 1) : null,
            ]])->all(),
        ];
    }

    public function properties(Portfolio $portfolio, array $arguments): array
    {
        [$from, $to] = $this->period($arguments);
        $movements = $this->query($portfolio, $from, $to, [])->get()->groupBy('property_id');
        $properties = $portfolio->properties()->orderBy('name')->get()->map(function ($property) use ($movements) {
            $totals = $this->totals($movements->get($property->id, collect()));

            return [
                'property_id' => $property->id, 'name' => $property->name, ...$totals,
                'return_on_current_value_percent' => $property->current_value > 0 && $totals['recorded_paid_count'] > 0
                    ? round($totals['net'] / (float) $property->current_value * 100, 2) : null,
                'app_path' => '/properties/'.$property->id,
            ];
        })->sortByDesc('net')->values()->all();

        return [
            'currency' => $portfolio->currency, 'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'basis' => 'Flujo de caja pagado por inmueble. Porcentaje del periodo sobre el valor actual, NO anualizado. Excluye revalorización y fiscalidad. Sin registros no implica ausencia real de gastos o ingresos.',
            'properties' => $properties, 'unassigned' => $this->totals($movements->get('', collect())),
            'app_path' => '/reports',
        ];
    }

    public function movements(Portfolio $portfolio, array $arguments): array
    {
        [$from, $to] = $this->period($arguments);
        $query = $this->query($portfolio, $from, $to, $arguments);
        if (! empty($arguments['direction'])) {
            $query->where('direction', $arguments['direction']);
        }
        $count = (clone $query)->count();

        return [
            'currency' => $portfolio->currency, 'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'total_count' => $count, 'truncated' => $count > 30, 'app_path' => '/finance',
            'movements' => $query->with('property')->orderByDesc('transaction_date')->orderByDesc('id')->limit(30)->get()->map(fn ($item) => [
                'date' => $item->transaction_date->toDateString(), 'amount' => (float) $item->amount,
                'direction' => $item->direction, 'category' => $item->category,
                'property' => $item->property?->name,
            ])->all(),
        ];
    }

    private function period(array $arguments): array
    {
        $from = CarbonImmutable::parse($arguments['from'] ?? today()->startOfMonth())->startOfDay();
        $to = CarbonImmutable::parse($arguments['to'] ?? today())->startOfDay();
        if ($from->greaterThan($to) || $from->diffInDays($to) > 1096) {
            throw new InvalidArgumentException('El intervalo debe estar ordenado y no superar tres años.');
        }

        return [$from, $to];
    }

    private function query(Portfolio $portfolio, CarbonImmutable $from, CarbonImmutable $to, array $arguments)
    {
        $query = Transaction::where('portfolio_id', $portfolio->id)->where('status', 'paid')
            ->whereDate('transaction_date', '>=', $from->toDateString())
            ->whereDate('transaction_date', '<=', $to->toDateString());
        if (! empty($arguments['property_id'])) {
            $portfolio->properties()->findOrFail($arguments['property_id']);
            $query->where('property_id', $arguments['property_id']);
        }

        return $query;
    }

    private function totals($transactions): array
    {
        $income = round((float) $transactions->where('direction', 'income')->sum('amount'), 2);
        $expenses = round((float) $transactions->where('direction', 'expense')->sum('amount'), 2);

        return ['recorded_paid_count' => $transactions->count(), 'income' => $income, 'expenses' => $expenses, 'net' => round($income - $expenses, 2)];
    }
}
