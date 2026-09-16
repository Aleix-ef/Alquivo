<?php

namespace App\Domain\Fiscality\Services;

use App\Domain\Documents\Models\Document;
use App\Domain\Finance\Models\RentCharge;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Fiscality\Models\TaxYear;
use App\Domain\Properties\Models\Property;
use App\Models\User;

final class TaxDossierService
{
    public function __construct(private readonly PropertyTaxCalculator $calculator) {}

    public function build(User $user, int $year, ?int $propertyId = null): array
    {
        $portfolio = $user->portfolio();
        $taxYear = TaxYear::where('portfolio_id', $portfolio->id)->where('user_id', $user->id)->where('year', $year)->with('records')->first();
        $profile = $taxYear?->profile ?? ['name' => $user->name, 'regime' => 'unknown'];
        $records = $taxYear?->records->keyBy('property_id') ?? collect();
        $properties = $portfolio->properties()->when($propertyId, fn ($q) => $q->whereKey($propertyId))->orderBy('name')->get();
        $items = $properties->map(function (Property $property) use ($year, $profile, $records, $portfolio) {
            $record = $records->get($property->id);
            $input = $record?->inputs ?? [];
            $source = $this->source($property, $year);
            $result = $this->calculator->calculate($year, $profile, $property->toArray(), $input);
            if ($portfolio->currency !== 'EUR') {
                $result = ['status' => 'incomplete', 'issues' => ['El dossier solo admite importes en euros. Revisa la moneda de la cartera.'], 'figures' => null, 'days' => $result['days'], 'lines' => [], 'carryforwards' => []];
            }

            return [
                'property' => $property->only(['id', 'name', 'type', 'address_line', 'city', 'country_code']),
                'revision' => $record?->revision ?? 0, 'inputs' => $input, 'source' => $source, 'result' => $result,
            ];
        })->values()->all();
        $calculated = array_filter($items, fn ($item) => $item['result']['figures'] !== null);
        $totals = [];
        foreach (PropertyTaxCalculator::FIGURES as $key => $label) {
            $totals[$key] = count($calculated) ? array_sum(array_map(fn ($item) => $item['result']['figures'][$key], $calculated)) : null;
        }

        return [
            'year' => $year, 'currency' => 'EUR', 'rules_version' => config('fiscality.rules_version'),
            'review_status' => config('fiscality.review_status'), 'renderer_version' => 1,
            'profile' => $profile, 'profile_revision' => $taxYear?->revision ?? 0,
            'properties' => $items, 'totals' => $totals, 'figure_labels' => PropertyTaxCalculator::FIGURES,
            'calculated_count' => count($calculated), 'property_count' => count($items),
            'partial' => count($calculated) !== count($items) || count($items) === 0,
            'sources' => config('fiscality.sources'),
            'notice' => 'Borrador fiscal para revisión. Incluye únicamente los inmuebles y supuestos cubiertos. No calcula la cuota personal de IRPF ni presenta una declaración.',
        ];
    }

    private function source(Property $property, int $year): array
    {
        $charges = RentCharge::whereHas('lease', fn ($q) => $q->where('portfolio_id', $property->portfolio_id)->where('property_id', $property->id))
            ->whereBetween('due_date', ["{$year}-01-01", "{$year}-12-31"])->orderBy('due_date')->get();
        $transactions = Transaction::where('portfolio_id', $property->portfolio_id)->where('property_id', $property->id)
            ->whereBetween('transaction_date', ["{$year}-01-01", "{$year}-12-31"])->where('status', 'paid')->orderBy('transaction_date')->get();
        $sum = fn ($rows) => $rows->sum(fn ($row) => TaxMoney::cents((string) $row->amount));

        return [
            'charge_income_cents' => $sum($charges), 'paid_income_cents' => $sum($transactions->where('direction', 'income')),
            'paid_expenses_cents' => $sum($transactions->where('direction', 'expense')),
            'charge_count' => $charges->count(),
            'charges' => $charges->map(fn ($charge) => ['id' => $charge->id, 'date' => $charge->due_date->toDateString(), 'amount' => $charge->amount])->all(),
            'transactions' => $transactions->map(fn ($t) => ['id' => $t->id, 'date' => $t->transaction_date->toDateString(), 'direction' => $t->direction, 'description' => $t->description, 'amount' => $t->amount, 'rent_charge_id' => $t->rent_charge_id])->all(),
            'documents' => Document::where('portfolio_id', $property->portfolio_id)->where('property_id', $property->id)->orderBy('name')->get(['id', 'name'])->toArray(),
        ];
    }
}
