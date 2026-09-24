<?php

namespace Tests\Feature;

use App\Domain\Assistant\Services\PortfolioAssistantTools;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Portfolio\Models\Portfolio;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AssistantInsightsTest extends TestCase
{
    use RefreshDatabase;

    private function movement(Portfolio $portfolio, array $data = []): Transaction
    {
        return Transaction::create([...[
            'portfolio_id' => $portfolio->id, 'direction' => 'income', 'category' => 'other',
            'description' => 'Información privada no destinada al modelo', 'amount' => 100,
            'transaction_date' => today(), 'status' => 'paid', 'notes' => 'Nota privada',
        ], ...$data]);
    }

    public function test_comparison_uses_equal_month_progress_and_only_paid_owned_movements(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 12)->startOfDay());
        $portfolio = Portfolio::create(['name' => 'Mi cartera']);
        $other = Portfolio::create(['name' => 'Otra cartera']);
        $this->movement($portfolio, ['amount' => 200, 'transaction_date' => '2026-09-01']);
        $this->movement($portfolio, ['amount' => 100, 'transaction_date' => '2026-08-12']);
        $this->movement($portfolio, ['amount' => 900, 'transaction_date' => '2026-08-13']);
        $this->movement($portfolio, ['status' => 'pending', 'amount' => 900]);
        $this->movement($portfolio, ['status' => 'cancelled', 'amount' => 900]);
        $this->movement($other, ['amount' => 900]);

        $result = app(PortfolioAssistantTools::class)->execute($portfolio, 'compare_months', []);
        $this->assertTrue($result['partial_month']);
        $this->assertSame('2026-08-12', $result['previous']['to']);
        $this->assertSame(200.0, $result['current']['income']);
        $this->assertSame(100.0, $result['previous']['income']);
        $this->assertSame(100.0, $result['change']['income']['percent']);
        $this->assertNull($result['change']['expenses']['percent']);
        $this->travelBack();
    }

    public function test_completed_months_include_the_whole_previous_month_and_leap_day(): void
    {
        $this->travelTo(now()->setDate(2024, 4, 12));
        $portfolio = Portfolio::create(['name' => 'Mi cartera']);
        $result = app(PortfolioAssistantTools::class)->execute($portfolio, 'compare_months', ['month' => '2024-03']);
        $this->assertFalse($result['partial_month']);
        $this->assertSame('2024-03-31', $result['current']['to']);
        $this->assertSame('2024-02-29', $result['previous']['to']);
        $this->assertNull($result['change']['net']['percent']);
        $this->travelBack();
    }

    public function test_property_comparison_distinguishes_unassigned_and_missing_data(): void
    {
        $portfolio = Portfolio::create(['name' => 'Mi cartera']);
        $property = $portfolio->properties()->create(['name' => 'Centro', 'type' => 'housing', 'address_line' => 'Dirección privada', 'current_value' => 10000]);
        $portfolio->properties()->create(['name' => 'Sin datos', 'type' => 'housing', 'address_line' => 'Otra dirección']);
        $this->movement($portfolio, ['property_id' => $property->id, 'amount' => 100.25]);
        $this->movement($portfolio, ['property_id' => $property->id, 'amount' => 20.10, 'direction' => 'expense']);
        $this->movement($portfolio, ['amount' => 30, 'direction' => 'expense']);
        $other = Portfolio::create(['name' => 'Otra cartera']);
        $other->properties()->create(['name' => 'Ajeno', 'type' => 'housing', 'address_line' => 'Secreto']);

        $result = app(PortfolioAssistantTools::class)->execute($portfolio, 'compare_properties', []);
        $this->assertCount(2, $result['properties']);
        $this->assertSame(80.15, $result['properties'][0]['net']);
        $this->assertSame(0.8, $result['properties'][0]['return_on_current_value_percent']);
        $this->assertNull($result['properties'][1]['return_on_current_value_percent']);
        $this->assertSame(-30.0, $result['unassigned']['net']);
        $this->assertStringNotContainsString('Dirección privada', json_encode($result));
        $this->assertStringNotContainsString('Ajeno', json_encode($result));
    }

    public function test_movements_are_limited_and_do_not_expose_notes_or_descriptions(): void
    {
        $portfolio = Portfolio::create(['name' => 'Mi cartera']);
        for ($i = 0; $i < 31; $i++) {
            $this->movement($portfolio, ['direction' => 'expense']);
        }
        $this->movement($portfolio, ['amount' => 900]);
        $result = app(PortfolioAssistantTools::class)->execute($portfolio, 'list_movements', ['direction' => 'expense']);
        $this->assertCount(30, $result['movements']);
        $this->assertSame(31, $result['total_count']);
        $this->assertTrue($result['truncated']);
        $this->assertArrayNotHasKey('notes', $result['movements'][0]);
        $this->assertArrayNotHasKey('description', $result['movements'][0]);
    }

    public function test_property_filters_reject_another_portfolio(): void
    {
        $portfolio = Portfolio::create(['name' => 'Mi cartera']);
        $other = Portfolio::create(['name' => 'Otra cartera']);
        $property = $other->properties()->create(['name' => 'Ajeno', 'type' => 'housing', 'address_line' => 'Secreto']);
        foreach (['compare_months', 'list_movements'] as $tool) {
            try {
                app(PortfolioAssistantTools::class)->execute($portfolio, $tool, ['property_id' => $property->id]);
                $this->fail('No se debe aceptar un inmueble ajeno.');
            } catch (ModelNotFoundException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_financial_categories_keep_income_and_expenses_separate_and_include_first_day(): void
    {
        $portfolio = Portfolio::create(['name' => 'Mi cartera']);
        $this->movement($portfolio, ['amount' => 100.25, 'transaction_date' => today()->startOfMonth()]);
        $this->movement($portfolio, ['amount' => 20.10, 'direction' => 'expense']);
        $this->movement($portfolio, ['amount' => 50, 'direction' => 'expense', 'status' => 'pending']);
        $result = app(PortfolioAssistantTools::class)->execute($portfolio, 'get_financial_summary', []);
        $this->assertSame(100.25, $result['income_by_category']['other']);
        $this->assertSame(20.1, $result['expenses_by_category']['other']);
        $this->assertSame(80.15, $result['net']);
        $this->assertSame(50.0, $result['pending_expenses']);
    }

    public function test_pending_rent_totals_are_not_limited_to_the_twenty_displayed_items(): void
    {
        $portfolio = Portfolio::create(['name' => 'Mi cartera']);
        $property = $portfolio->properties()->create(['name' => 'Centro', 'type' => 'housing', 'address_line' => 'Calle 1']);
        $lease = $property->leases()->create(['portfolio_id' => $portfolio->id, 'status' => 'active', 'start_date' => '2020-01-01', 'monthly_rent' => 100]);
        for ($i = 1; $i <= 21; $i++) {
            $charge = $lease->charges()->create(['portfolio_id' => $portfolio->id, 'period' => today()->subMonths($i)->format('Y-m'), 'due_date' => today()->subMonths($i), 'amount' => 100, 'paid_amount' => 40, 'status' => 'partial']);
            $this->movement($portfolio, ['property_id' => $property->id, 'lease_id' => $lease->id, 'rent_charge_id' => $charge->id, 'category' => 'rent', 'amount' => 40]);
        }
        $result = app(PortfolioAssistantTools::class)->execute($portfolio, 'get_pending_items', ['kind' => 'rents']);
        $this->assertCount(20, $result['rents']);
        $this->assertSame(21, $result['rents_summary']['total_count']);
        $this->assertSame('1260.00', $result['rents_summary']['pending_amount']);
        $this->assertSame(21, $result['rents_summary']['overdue_count']);
    }

    public function test_invalid_periods_and_unexpected_parameters_are_rejected(): void
    {
        $portfolio = Portfolio::create(['name' => 'Mi cartera']);
        foreach ([
            ['compare_months', ['month' => '2026-13']],
            ['compare_months', ['month' => today()->addYear()->format('Y-m')]],
            ['compare_properties', ['from' => '2026-03-01', 'to' => '2026-02-01']],
            ['list_movements', ['from' => '2020-01-01', 'to' => '2026-01-01']],
            ['list_movements', ['portfolio_id' => 123]],
        ] as [$tool, $arguments]) {
            try {
                app(PortfolioAssistantTools::class)->execute($portfolio, $tool, $arguments);
                $this->fail('Los parámetros inválidos deben rechazarse.');
            } catch (\InvalidArgumentException|ValidationException) {
                $this->assertTrue(true);
            }
        }
    }
}
