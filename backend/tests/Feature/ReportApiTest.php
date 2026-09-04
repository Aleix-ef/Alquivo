<?php

namespace Tests\Feature;

use App\Domain\Finance\Models\Transaction;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Cartera', 'currency' => 'EUR']);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $property = $portfolio->properties()->create([
            'name' => 'Piso Centro', 'type' => 'housing', 'address_line' => 'Calle 1', 'current_value' => 200000,
        ]);

        return [$user, $portfolio, $property];
    }

    public function test_overview_returns_monthly_paid_cash_flow_and_property_performance(): void
    {
        [$user, $portfolio, $property] = $this->context();
        foreach ([['income', 900, 'paid'], ['expense', 150, 'paid'], ['income', 999, 'pending']] as [$direction, $amount, $status]) {
            Transaction::create([
                'portfolio_id' => $portfolio->id, 'property_id' => $property->id,
                'direction' => $direction, 'category' => 'other', 'description' => 'Movimiento',
                'amount' => $amount, 'transaction_date' => today(), 'status' => $status,
            ]);
        }

        $this->actingAs($user)->getJson('/api/v1/reports/overview?months=3')->assertOk()
            ->assertJsonPath('currency', 'EUR')->assertJsonPath('totals.income', 900)
            ->assertJsonPath('totals.expenses', 150)->assertJsonPath('totals.net', 750)
            ->assertJsonPath('properties.0.name', 'Piso Centro')->assertJsonCount(3, 'series');
    }

    public function test_csv_export_is_isolated_and_protects_spreadsheet_cells(): void
    {
        [$user, $portfolio] = $this->context();
        $portfolio->properties()->create(['name' => '=DANGEROUS()', 'type' => 'garage', 'address_line' => 'Calle 2']);
        $other = Portfolio::create(['name' => 'Otra']);
        $other->properties()->create(['name' => 'Propiedad ajena', 'type' => 'housing', 'address_line' => 'Otra']);

        $response = $this->actingAs($user)->get('/api/v1/exports/properties')->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();

        $this->assertStringContainsString("'=DANGEROUS()", $content);
        $this->assertStringNotContainsString('Propiedad ajena', $content);
    }
}
