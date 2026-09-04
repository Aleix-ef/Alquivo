<?php

namespace Tests\Feature;

use App\Domain\Finance\Models\RecurringRule;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringFinanceApiTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Cartera']);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $property = $portfolio->properties()->create(['name' => 'Piso', 'type' => 'housing', 'address_line' => 'Calle 1']);

        return [$user, $portfolio, $property];
    }

    public function test_due_recurring_expense_creates_a_pending_transaction(): void
    {
        [$user, $portfolio, $property] = $this->context();

        $this->actingAs($user)->postJson('/api/v1/recurring-rules', [
            'property_id' => $property->id, 'direction' => 'expense', 'category' => 'community',
            'description' => 'Comunidad', 'amount' => 85, 'frequency' => 'monthly',
            'starts_on' => today()->toDateString(),
        ])->assertCreated();

        $this->assertDatabaseHas('transactions', [
            'portfolio_id' => $portfolio->id, 'description' => 'Comunidad', 'amount' => 85, 'status' => 'pending',
        ]);
    }

    public function test_recurring_generation_is_idempotent_and_catches_up_missed_months(): void
    {
        [, $portfolio] = $this->context();
        RecurringRule::create([
            'portfolio_id' => $portfolio->id, 'direction' => 'expense', 'category' => 'insurance',
            'description' => 'Seguro', 'amount' => 40, 'frequency' => 'monthly',
            'starts_on' => today()->subMonthsNoOverflow(2), 'next_date' => today()->subMonthsNoOverflow(2), 'active' => true,
        ]);

        $this->artisan('finance:generate-recurring')->assertSuccessful();
        $this->artisan('finance:generate-recurring')->assertSuccessful();

        $this->assertDatabaseCount('transactions', 3);
    }

    public function test_user_cannot_pause_a_rule_from_another_portfolio(): void
    {
        [$user] = $this->context();
        $other = Portfolio::create(['name' => 'Otra']);
        $rule = RecurringRule::create([
            'portfolio_id' => $other->id, 'direction' => 'expense', 'category' => 'other',
            'description' => 'Ajeno', 'amount' => 10, 'frequency' => 'monthly',
            'starts_on' => today(), 'next_date' => today(), 'active' => true,
        ]);

        $this->actingAs($user)->putJson("/api/v1/recurring-rules/{$rule->id}", ['active' => false])->assertNotFound();
    }
}
