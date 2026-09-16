<?php

namespace Tests\Unit;

use App\Domain\Fiscality\Services\PropertyTaxCalculator;
use App\Domain\Fiscality\Services\TaxMoney;
use Tests\Support\FiscalFixture;
use Tests\TestCase;

class PropertyTaxCalculatorTest extends TestCase
{
    private function calculate(array $overrides = [], array $profile = ['regime' => 'common'], int $year = 2025): array
    {
        return (new PropertyTaxCalculator)->calculate($year, $profile, ['type' => 'housing', 'country_code' => 'ES'], FiscalFixture::inputs($overrides));
    }

    public function test_residential_result_is_not_cash_flow_or_personal_irpf(): void
    {
        $result = $this->calculate();
        $this->assertSame('calculated', $result['status']);
        $this->assertSame(['income' => 1200000, 'expenses' => 300000, 'depreciation' => 300000, 'net' => 600000, 'reduction' => 300000, 'reduced_net' => 300000, 'imputed' => 0], $result['figures']);
    }

    public function test_ownership_is_applied_to_income_costs_and_depreciation(): void
    {
        $figures = $this->calculate(['ownership_percent' => '50'])['figures'];
        $this->assertSame(600000, $figures['income']);
        $this->assertSame(150000, $figures['expenses']);
        $this->assertSame(150000, $figures['depreciation']);
        $this->assertSame(150000, $figures['reduced_net']);
    }

    public function test_contract_cutover_is_exact_and_negative_net_has_no_reduction(): void
    {
        $this->assertSame(60, $this->calculate(['contract_start' => '2023-05-25'])['reduction_percent']);
        $this->assertSame(50, $this->calculate(['contract_start' => '2023-05-26'])['reduction_percent']);
        $result = $this->calculate(['income' => '1000']);
        $this->assertSame(-380000, $result['figures']['net']);
        $this->assertSame(0, $result['figures']['reduction']);
        $this->assertSame(120000, $result['carryforwards'][0]['pending_cents']);
    }

    public function test_rental_days_and_vacancy_have_distinct_tax_treatment(): void
    {
        $result = $this->calculate([
            'ownership_percent' => '50', 'income' => '6000',
            'periods' => [['start' => '2025-01-01', 'end' => '2025-06-30', 'use' => 'rented'], ['start' => '2025-07-01', 'end' => '2025-12-31', 'use' => 'available']],
            'expenses' => [['category' => 'ibi', 'amount' => '730', 'allocation' => 'annual', 'description' => 'IBI']],
        ]);
        $this->assertSame(['rented' => 181, 'available' => 184, 'habitual' => 0], $result['days']);
        $this->assertSame(18100, $result['figures']['expenses']);
        $this->assertSame(74384, $result['figures']['depreciation']);
        $this->assertSame(27726, $result['figures']['imputed']);
    }

    public function test_prior_carryforwards_have_priority_and_expire_after_four_years(): void
    {
        $result = $this->calculate(['income' => '100', 'carryforwards' => [['year' => 2020, 'amount' => '500'], ['year' => 2021, 'amount' => '500'], ['year' => 2024, 'amount' => '300']]]);
        $this->assertSame(50000, $result['carryforwards'][0]['expired_cents']);
        $this->assertSame(10000, $result['carryforwards'][1]['used_cents']);
        $this->assertSame(40000, $result['carryforwards'][1]['expired_cents']);
        $this->assertSame(30000, $result['carryforwards'][2]['pending_cents']);
        $this->assertSame(220000, $result['carryforwards'][3]['pending_cents']);
    }

    public function test_depreciation_uses_greater_base_but_respects_accumulated_cap(): void
    {
        $result = $this->calculate(['cadastral_building' => '120000', 'prior_depreciation' => '99900']);
        $this->assertSame(10000, $result['figures']['depreciation']);
        $this->assertSame(360000, $this->calculate(['cadastral_building' => '120000'])['figures']['depreciation']);
    }

    public function test_incomplete_or_uncovered_cases_do_not_become_zero(): void
    {
        foreach ([['income' => null], ['prior_depreciation' => null], ['ordinary_case' => false], ['reduction_case' => 'special'], ['periods' => []]] as $overrides) {
            $this->assertNull($this->calculate($overrides)['figures']);
        }
        $this->assertNull($this->calculate([], ['regime' => 'foral'])['figures']);
        $this->assertNull($this->calculate([], ['regime' => 'common'], 2026)['figures']);
    }

    public function test_habitual_home_has_no_imputed_income_and_missing_cadastre_blocks_vacancy(): void
    {
        $input = ['income' => '0', 'expenses' => [], 'periods' => [['start' => '2025-01-01', 'end' => '2025-12-31', 'use' => 'habitual']]];
        $this->assertSame(0, $this->calculate($input)['figures']['imputed']);
        $input['periods'][0]['use'] = 'available';
        $this->assertSame(110000, $this->calculate($input)['figures']['imputed']);
        $input['cadastral_revision'] = 'before_2012';
        $this->assertSame(200000, $this->calculate($input)['figures']['imputed']);
        $input['cadastral_revision'] = 'unknown';
        $this->assertNull($this->calculate($input)['figures']);
    }

    public function test_large_values_and_fractional_shares_do_not_overflow_or_use_floats(): void
    {
        $this->assertSame(300000000, TaxMoney::ratio(10000000000, 300 * 365 * 10000, 10000 * 365 * 10000));
        $this->assertSame(1, TaxMoney::ratio(1, 50, 100));
        $this->assertSame(-1, TaxMoney::ratio(-1, 50, 100));
        $this->assertSame(123456, TaxMoney::cents('1234.56'));
    }
}
