<?php

namespace Tests\Feature;

use App\Domain\Finance\Models\RentCharge;
use App\Domain\Leasing\Models\Contact;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DecimalAmountsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->travelTo(now()->startOfMonth()->addDays(10));
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Synthetic decimal tests']);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $property = $portfolio->properties()->create(['name' => 'Synthetic home', 'type' => 'housing', 'address_line' => 'Synthetic street']);
        $contact = Contact::create(['portfolio_id' => $portfolio->id, 'name' => 'Synthetic tenant', 'kind' => 'person']);
        $this->actingAs($user);

        return [$portfolio, $property, $contact];
    }

    private function leaseInput($property, $contact): array
    {
        return ['property_id' => $property->id, 'contact_ids' => [$contact->id], 'status' => 'active',
            'start_date' => today()->startOfMonth()->toDateString(), 'monthly_rent' => '550.50',
            'deposit_amount' => '1101.25', 'payment_day' => 5];
    }

    public static function decimalRents(): array
    {
        return ['numeric cents' => [550.50, 1101.25, '550.50', '1101.25'],
            'decimal strings' => ['550.50', '1101.25', '550.50', '1101.25'],
            'one cent and no deposit' => ['0.01', '0.00', '0.01', '0.00']];
    }

    #[DataProvider('decimalRents')]
    public function test_create_lease_preserves_rent_and_deposit_cents($rent, $deposit, string $expectedRent, string $expectedDeposit): void
    {
        [$portfolio, $property, $contact] = $this->context();
        $response = $this->postJson('/api/v1/leases', [...$this->leaseInput($property, $contact),
            'monthly_rent' => $rent, 'deposit_amount' => $deposit])
            ->assertCreated()->assertJsonPath('monthly_rent', $expectedRent)
            ->assertJsonPath('deposit_amount', $expectedDeposit)->assertJsonPath('charges.0.amount', $expectedRent);
        $lease = Lease::findOrFail($response->json('id'));
        $this->assertSame($expectedRent, $lease->monthly_rent);
        $this->assertSame($expectedDeposit, $lease->deposit_amount);
        $this->assertSame($expectedRent, RentCharge::where('portfolio_id', $portfolio->id)->sole()->amount);
        Http::assertNothingSent();
    }

    public function test_edit_and_renew_preserve_cents_without_rewriting_an_existing_charge(): void
    {
        [, $property, $contact] = $this->context();
        $leaseId = $this->postJson('/api/v1/leases', $this->leaseInput($property, $contact))->assertCreated()->json('id');
        $this->putJson("/api/v1/leases/{$leaseId}", ['monthly_rent' => '575.35', 'deposit_amount' => '1150.70'])
            ->assertOk()->assertJsonPath('monthly_rent', '575.35')->assertJsonPath('deposit_amount', '1150.70')
            ->assertJsonPath('charges.0.amount', '550.50');
        $renewal = $this->postJson("/api/v1/leases/{$leaseId}/renew", [
            'start_date' => today()->addMonth()->startOfMonth()->toDateString(),
            'monthly_rent' => '600.15', 'deposit_amount' => '1200.30', 'payment_day' => 7,
        ])->assertCreated()->assertJsonPath('monthly_rent', '600.15')->assertJsonPath('deposit_amount', '1200.30')
            ->assertJsonPath('status', 'draft');
        $this->assertSame('600.15', Lease::findOrFail($renewal->json('id'))->monthly_rent);
        $this->assertDatabaseCount('rent_charges', 1);
    }

    public function test_partial_and_full_payments_preserve_the_exact_remaining_cents(): void
    {
        [, $property, $contact] = $this->context();
        $chargeId = $this->postJson('/api/v1/leases', [...$this->leaseInput($property, $contact), 'monthly_rent' => '380.03'])
            ->assertCreated()->json('charges.0.id');
        $first = $this->postJson("/api/v1/rent-charges/{$chargeId}/payments", [
            'amount' => '330.01', 'transaction_date' => today()->toDateString(),
        ])->assertCreated()->assertJsonPath('amount', '330.01');
        $this->assertSame('330.01', RentCharge::findOrFail($chargeId)->paid_amount);
        $this->postJson("/api/v1/rent-charges/{$chargeId}/payments", [
            'amount' => '50.02', 'transaction_date' => today()->toDateString(),
        ])->assertCreated()->assertJsonPath('amount', '50.02');
        $charge = RentCharge::findOrFail($chargeId);
        $this->assertSame('380.03', $charge->paid_amount);
        $this->assertSame('paid', $charge->status);
        $this->putJson('/api/v1/rent-payments/'.$first->json('id'), [
            'amount' => '300.25', 'transaction_date' => today()->toDateString(),
        ])->assertOk()->assertJsonPath('amount', '300.25');
        $this->assertSame('350.27', $charge->fresh()->paid_amount);
        $this->assertSame('partial', $charge->fresh()->status);
    }

    public function test_property_prices_valuations_debt_costs_and_area_keep_hundredths(): void
    {
        [, $property] = $this->context();
        $values = ['purchase_price' => '125000.35', 'current_value' => '151000.15', 'acquisition_costs' => '8750.65',
            'outstanding_debt' => '84325.72', 'area' => '72.45', 'bedrooms' => 2, 'bathrooms' => 1];
        $response = $this->putJson("/api/v1/properties/{$property->id}", $values)->assertOk();
        foreach ($values as $field => $value) {
            if ($field === 'area') {
                // SQLite returns a number and PostgreSQL a decimal string here.
                $response->assertJsonPath($field, fn ($actual) => (string) $actual === $value);
            } else {
                $response->assertJsonPath($field, $value);
            }
        }
        $this->assertDatabaseHas('property_valuations', ['property_id' => $property->id, 'amount' => '151000.15']);
    }

    public function test_movements_and_recurring_rules_preserve_cents_on_creation_and_edit(): void
    {
        [$portfolio, $property] = $this->context();
        foreach (['expense', 'income'] as $direction) {
            $transaction = $this->postJson('/api/v1/transactions', [
                'property_id' => $property->id, 'direction' => $direction, 'category' => 'other',
                'description' => 'Synthetic movement', 'amount' => '84.35', 'transaction_date' => today()->toDateString(), 'status' => 'paid',
            ])->assertCreated()->assertJsonPath('amount', '84.35');
            $this->putJson('/api/v1/transactions/'.$transaction->json('id'), ['amount' => '90.55'])
                ->assertOk()->assertJsonPath('amount', '90.55');
        }
        $rule = $this->postJson('/api/v1/recurring-rules', [
            'property_id' => $property->id, 'direction' => 'expense', 'category' => 'other',
            'description' => 'Synthetic recurring cents', 'amount' => '45.85', 'frequency' => 'monthly', 'starts_on' => today()->toDateString(),
        ])->assertCreated()->assertJsonPath('amount', '45.85');
        $this->assertDatabaseHas('transactions', ['portfolio_id' => $portfolio->id, 'recurring_rule_id' => $rule->json('id'), 'amount' => '45.85']);
        Http::assertNothingSent();
    }

    public function test_incident_estimates_and_actual_costs_accept_cents(): void
    {
        [, $property] = $this->context();
        $issue = $this->postJson('/api/v1/issues', ['property_id' => $property->id, 'title' => 'Synthetic repair',
            'priority' => 'medium', 'reported_at' => today()->toDateString(), 'estimated_cost' => '120.65'])
            ->assertCreated()->assertJsonPath('estimated_cost', '120.65');
        $this->putJson('/api/v1/issues/'.$issue->json('id'), ['actual_cost' => '115.25'])
            ->assertOk()->assertJsonPath('actual_cost', '115.25')->assertJsonPath('estimated_cost', '120.65');
    }

    public function test_integer_counts_and_payment_days_do_not_accept_fractions(): void
    {
        [, $property, $contact] = $this->context();
        $this->postJson('/api/v1/leases', [...$this->leaseInput($property, $contact), 'payment_day' => 5.5])
            ->assertUnprocessable()->assertJsonValidationErrors('payment_day');
        $this->assertDatabaseCount('leases', 0);
        $this->putJson("/api/v1/properties/{$property->id}", ['bedrooms' => 2.5, 'bathrooms' => 1.5])
            ->assertUnprocessable()->assertJsonValidationErrors(['bedrooms', 'bathrooms']);
        $this->assertNull($property->fresh()->bedrooms);
        $this->assertNull($property->fresh()->bathrooms);
    }
}
