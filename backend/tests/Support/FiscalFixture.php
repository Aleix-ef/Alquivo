<?php

namespace Tests\Support;

final class FiscalFixture
{
    public static function inputs(array $overrides = []): array
    {
        return array_replace([
            'ownership_percent' => '100', 'owned_from' => '2025-01-01', 'owned_to' => '2025-12-31',
            'ordinary_case' => true, 'records_reviewed' => true, 'reduction_case' => 'standard',
            'contract_start' => '2024-01-01', 'income' => '12000', 'building_cost' => '100000',
            'cadastral_building' => '60000', 'prior_depreciation' => '3000', 'depreciation_rate' => '3',
            'cadastral_total' => '100000', 'cadastral_revision' => 'since_2012',
            'periods' => [['start' => '2025-01-01', 'end' => '2025-12-31', 'use' => 'rented']],
            'expenses' => [
                ['category' => 'interest', 'amount' => '1200', 'allocation' => 'annual', 'description' => 'Intereses hipotecarios', 'document_id' => null],
                ['category' => 'repairs', 'amount' => '1000', 'allocation' => 'rental', 'description' => 'Reparación de tubería', 'document_id' => null],
                ['category' => 'ibi', 'amount' => '600', 'allocation' => 'annual', 'description' => 'IBI anual', 'document_id' => null],
                ['category' => 'insurance', 'amount' => '200', 'allocation' => 'annual', 'description' => 'Seguro anual', 'document_id' => null],
            ],
            'carryforwards' => [], 'notes' => 'Ejemplo ficticio para pruebas.',
        ], $overrides);
    }
}
