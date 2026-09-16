<?php

namespace App\Domain\Fiscality\Services;

use Carbon\CarbonImmutable;

final class PropertyTaxCalculator
{
    public const FIGURES = [
        'income' => 'Ingresos íntegros exigibles',
        'expenses' => 'Gastos deducibles (sin amortización)',
        'depreciation' => 'Amortización de la construcción',
        'net' => 'Rendimiento neto',
        'reduction' => 'Reducción por vivienda',
        'reduced_net' => 'Rendimiento neto reducido',
        'imputed' => 'Renta inmobiliaria imputada',
    ];

    public function calculate(int $year, array $profile, array $property, array $input): array
    {
        $issues = [];
        if ($year !== 2025) {
            $issues[] = 'Este ejercicio todavía no tiene reglas habilitadas.';
        }
        if (($profile['regime'] ?? '') !== 'common') {
            $issues[] = 'Completa el perfil: solo está cubierto el IRPF de personas físicas residentes en régimen común.';
        }
        if (($property['type'] ?? '') !== 'housing' || ($property['country_code'] ?? '') !== 'ES') {
            $issues[] = 'El cálculo disponible cubre viviendas situadas en España.';
        }
        foreach ([
            'ordinary_case' => 'Confirma la adquisición por compra, plena propiedad y ausencia de casos especiales.',
            'records_reviewed' => 'Revisa y confirma los ingresos, gastos, historial y justificantes.',
        ] as $key => $message) {
            if (! ($input[$key] ?? false)) {
                $issues[] = $message;
            }
        }
        $required = [
            'ownership_percent' => 'porcentaje de titularidad', 'owned_from' => 'inicio de titularidad en el ejercicio',
            'owned_to' => 'fin de titularidad en el ejercicio', 'income' => 'ingresos exigibles (también si son cero)',
        ];
        foreach ($required as $key => $label) {
            if (! isset($input[$key]) || $input[$key] === '') {
                $issues[] = 'Falta: '.$label.'.';
            }
        }
        $days = ['rented' => 0, 'available' => 0, 'habitual' => 0];
        $periods = $input['periods'] ?? [];
        usort($periods, fn ($a, $b) => strcmp($a['start'], $b['start']));
        $next = $input['owned_from'] ?? null;
        foreach ($periods as $period) {
            if ($period['start'] !== $next) {
                $issues[] = 'El calendario debe cubrir la titularidad sin huecos ni solapamientos.';
            }
            $start = CarbonImmutable::parse($period['start']);
            $end = CarbonImmutable::parse($period['end']);
            $days[$period['use']] += (int) $start->diffInDays($end) + 1;
            $next = $end->addDay()->toDateString();
        }
        if (! $periods || ! isset($input['owned_to']) || $next !== CarbonImmutable::parse($input['owned_to'])->addDay()->toDateString()) {
            $issues[] = 'Completa el calendario de uso hasta el último día de titularidad.';
        }
        $rented = $days['rented'];
        if ($rented > 0) {
            if (($input['reduction_case'] ?? '') !== 'standard') {
                $issues[] = 'Confirma el alquiler habitual con reducción ordinaria; las reducciones especiales requieren revisión adicional.';
            }
            foreach (['contract_start' => 'fecha fiscal del contrato', 'building_cost' => 'coste de construcción adquirido, sin suelo', 'cadastral_building' => 'valor catastral de construcción', 'prior_depreciation' => 'amortización acumulada anterior', 'depreciation_rate' => 'porcentaje de amortización'] as $key => $label) {
                if (! isset($input[$key]) || $input[$key] === '') {
                    $issues[] = 'Falta: '.$label.'.';
                }
            }
            if (isset($input['contract_start'])) {
                foreach ($periods as $period) {
                    if ($period['use'] === 'rented' && $period['start'] < $input['contract_start']) {
                        $issues[] = 'El contrato no puede comenzar después del periodo alquilado.';
                    }
                }
            }
            if (isset($input['building_cost'], $input['cadastral_building']) && (TaxMoney::cents($input['building_cost']) === 0 || TaxMoney::cents($input['cadastral_building']) === 0)) {
                $issues[] = 'Revisa las bases de amortización: la construcción adquirida y su valor catastral deben ser mayores que cero.';
            }
        } elseif (TaxMoney::cents($input['income'] ?? '0') > 0 || count($input['expenses'] ?? []) > 0) {
            $issues[] = 'Hay ingresos o gastos sin días alquilados; los casos previos al alquiler necesitan revisión específica.';
        }
        if ($days['available'] > 0 && (! isset($input['cadastral_total']) || ! in_array($input['cadastral_revision'] ?? '', ['since_2012', 'before_2012'], true))) {
            $issues[] = 'Para los días disponibles, indica el valor catastral notificado y si la valoración colectiva tiene efectos desde 2012.';
        }
        if ($days['available'] > 0 && isset($input['cadastral_total']) && TaxMoney::cents($input['cadastral_total']) === 0) {
            $issues[] = 'El valor catastral notificado no puede ser cero para calcular la renta imputada.';
        }
        if ($issues) {
            return ['status' => 'incomplete', 'issues' => array_values(array_unique($issues)), 'days' => $days, 'figures' => null, 'lines' => [], 'carryforwards' => []];
        }

        $yearDays = CarbonImmutable::create($year, 1, 1)->daysInYear;
        $share = TaxMoney::cents($input['ownership_percent']);
        $own = fn (int $amount) => TaxMoney::ratio($amount, $share, 10000);
        $income = $own(TaxMoney::cents($input['income']));
        $lines = [];
        $limited = 0;
        $other = 0;
        foreach ($input['expenses'] ?? [] as $expense) {
            $amount = TaxMoney::cents($expense['amount']);
            $eligible = $expense['allocation'] === 'annual' ? TaxMoney::ratio($amount, $rented * $share, $yearDays * 10000) : $own($amount);
            $isLimited = in_array($expense['category'], ['interest', 'repairs'], true);
            $isLimited ? $limited += $eligible : $other += $eligible;
            $lines[] = [...$expense, 'attributable_cents' => $eligible, 'limited' => $isLimited];
        }

        // AEAT 2025: prior-year amounts have priority over current-year expenses.
        $remainingLimit = $income;
        $carriedUsed = 0;
        $carryforwards = [];
        $prior = $input['carryforwards'] ?? [];
        usort($prior, fn ($a, $b) => $a['year'] <=> $b['year']);
        foreach ($prior as $carry) {
            $amount = $own(TaxMoney::cents($carry['amount']));
            $expired = $year > $carry['year'] + 4;
            $used = $expired ? 0 : min($remainingLimit, $amount);
            $remainingLimit -= $used;
            $carriedUsed += $used;
            $balance = $amount - $used;
            $carryforwards[] = ['year' => $carry['year'], 'origin_cents' => $amount, 'used_cents' => $used, 'pending_cents' => $year >= $carry['year'] + 4 ? 0 : $balance, 'expired_cents' => $year >= $carry['year'] + 4 ? $balance : 0, 'last_year' => $carry['year'] + 4];
        }
        $currentUsed = min($remainingLimit, $limited);
        $carryforwards[] = ['year' => $year, 'origin_cents' => $limited, 'used_cents' => $currentUsed, 'pending_cents' => $limited - $currentUsed, 'expired_cents' => 0, 'last_year' => $year + 4];
        $depreciation = 0;
        if ($rented > 0) {
            $cost = TaxMoney::cents($input['building_cost']);
            $base = max($cost, TaxMoney::cents($input['cadastral_building']));
            $annual = TaxMoney::ratio($base, TaxMoney::cents($input['depreciation_rate']) * $rented * $share, 10000 * $yearDays * 10000);
            $depreciation = min($annual, $own(max(0, $cost - TaxMoney::cents($input['prior_depreciation']))));
        }
        $expenses = $carriedUsed + $currentUsed + $other;
        $net = $income - $expenses - $depreciation;
        $reductionRate = $rented > 0 ? ($input['contract_start'] < '2023-05-26' ? 60 : 50) : 0;
        $reduction = TaxMoney::ratio(max(0, $net), $reductionRate, 100);
        $imputed = $days['available'] > 0
            ? TaxMoney::ratio(TaxMoney::cents($input['cadastral_total']), ($input['cadastral_revision'] === 'since_2012' ? 110 : 200) * $days['available'] * $share, 10000 * $yearDays * 10000)
            : 0;

        return [
            'status' => 'calculated', 'issues' => [], 'days' => $days,
            'figures' => ['income' => $income, 'expenses' => $expenses, 'depreciation' => $depreciation, 'net' => $net, 'reduction' => $reduction, 'reduced_net' => $net - $reduction, 'imputed' => $imputed],
            'reduction_percent' => $reductionRate, 'lines' => $lines, 'carryforwards' => $carryforwards,
            'limited_expenses' => ['current_eligible_cents' => $limited, 'current_used_cents' => $currentUsed, 'prior_used_cents' => $carriedUsed],
        ];
    }
}
