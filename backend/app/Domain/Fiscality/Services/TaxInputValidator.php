<?php

namespace App\Domain\Fiscality\Services;

use App\Domain\Properties\Models\Property;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class TaxInputValidator
{
    public function validate(array $data, int $year, Property $property): array
    {
        $money = ['nullable', 'string', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/D'];
        $rules = [
            'revision' => ['required', 'integer', 'min:0'],
            'inputs' => ['required', 'array:ownership_percent,owned_from,owned_to,ordinary_case,records_reviewed,reduction_case,contract_start,income,building_cost,cadastral_building,cadastral_total,cadastral_revision,prior_depreciation,depreciation_rate,periods,expenses,carryforwards,notes'],
            'inputs.ownership_percent' => [...$money, 'numeric', 'between:0.01,100'],
            'inputs.owned_from' => ['nullable', 'date_format:Y-m-d', "after_or_equal:{$year}-01-01", "before_or_equal:{$year}-12-31"],
            'inputs.owned_to' => ['nullable', 'date_format:Y-m-d', "after_or_equal:{$year}-01-01", "before_or_equal:{$year}-12-31"],
            'inputs.contract_start' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', "before_or_equal:{$year}-12-31"],
            'inputs.ordinary_case' => ['sometimes', 'boolean'],
            'inputs.records_reviewed' => ['sometimes', 'boolean'],
            'inputs.reduction_case' => ['nullable', Rule::in(['standard', 'special', 'unknown'])],
            'inputs.cadastral_revision' => ['nullable', Rule::in(['since_2012', 'before_2012', 'unknown'])],
            'inputs.depreciation_rate' => [...$money, 'numeric', 'between:1,3'],
            'inputs.notes' => ['nullable', 'string', 'max:1000'],
            'inputs.periods' => ['present', 'array', 'max:24'],
            'inputs.periods.*' => ['array:start,end,use'],
            'inputs.periods.*.start' => ['required', 'date_format:Y-m-d', "after_or_equal:{$year}-01-01", "before_or_equal:{$year}-12-31"],
            'inputs.periods.*.end' => ['required', 'date_format:Y-m-d', "after_or_equal:{$year}-01-01", "before_or_equal:{$year}-12-31"],
            'inputs.periods.*.use' => ['required', Rule::in(['rented', 'available', 'habitual'])],
            'inputs.expenses' => ['present', 'array', 'max:60'],
            'inputs.expenses.*' => ['array:category,amount,allocation,description,document_id'],
            'inputs.expenses.*.category' => ['required', Rule::in(array_keys(config('fiscality.expense_categories')))],
            'inputs.expenses.*.amount' => ['required', 'string', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/D'],
            'inputs.expenses.*.allocation' => ['required', Rule::in(['annual', 'rental'])],
            'inputs.expenses.*.description' => ['required', 'string', 'max:200'],
            'inputs.expenses.*.document_id' => ['nullable', 'integer', Rule::exists('documents', 'id')->where('portfolio_id', $property->portfolio_id)->where('property_id', $property->id)],
            'inputs.carryforwards' => ['present', 'array', 'max:5'],
            'inputs.carryforwards.*' => ['array:year,amount'],
            'inputs.carryforwards.*.year' => ['required', 'integer', 'distinct', 'between:'.($year - 5).','.($year - 1)],
            'inputs.carryforwards.*.amount' => ['required', 'string', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/D'],
        ];
        foreach (['income', 'building_cost', 'cadastral_building', 'cadastral_total', 'prior_depreciation'] as $field) {
            $rules['inputs.'.$field] = $money;
        }
        $validator = Validator::make($data, $rules, [
            'regex' => 'Usa un importe positivo con un máximo de dos decimales y punto decimal.',
            'exists' => 'El justificante debe pertenecer a este inmueble y a tu cartera.',
            'date_format' => 'Indica una fecha válida.',
        ]);
        $validator->after(function ($validator) use ($data, $property) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $input = $data['inputs'];
            $from = $input['owned_from'] ?? null;
            $to = $input['owned_to'] ?? null;
            if ($from && $to && $from > $to) {
                $validator->errors()->add('inputs.owned_to', 'El fin debe ser igual o posterior al inicio.');
            }
            if ($from && $property->purchase_date && $from < $property->purchase_date->toDateString()) {
                $validator->errors()->add('inputs.owned_from', 'La titularidad no puede comenzar antes de la fecha de compra registrada.');
            }
            $periods = $input['periods'];
            usort($periods, fn ($a, $b) => strcmp($a['start'], $b['start']));
            $previousEnd = null;
            foreach ($periods as $period) {
                if ($period['end'] < $period['start'] || ($previousEnd && $period['start'] <= $previousEnd) || ($from && $period['start'] < $from) || ($to && $period['end'] > $to)) {
                    $validator->errors()->add('inputs.periods', 'Los periodos deben estar ordenados, no solaparse y quedar dentro de la titularidad.');
                }
                $previousEnd = $period['end'];
            }
            if (isset($input['prior_depreciation'], $input['building_cost']) && TaxMoney::cents($input['prior_depreciation']) > TaxMoney::cents($input['building_cost'])) {
                $validator->errors()->add('inputs.prior_depreciation', 'La amortización acumulada supera el coste de construcción indicado. Revisa el historial.');
            }
            if (isset($input['cadastral_building'], $input['cadastral_total']) && TaxMoney::cents($input['cadastral_building']) > TaxMoney::cents($input['cadastral_total'])) {
                $validator->errors()->add('inputs.cadastral_building', 'La construcción no puede superar el valor catastral total.');
            }
        });

        return $validator->validate();
    }
}
