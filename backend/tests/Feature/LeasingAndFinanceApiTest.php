<?php

namespace Tests\Feature;

use App\Domain\Finance\Models\RentCharge;
use App\Domain\Leasing\Models\Contact;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LeasingAndFinanceApiTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): array
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Mi patrimonio']);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $property = $portfolio->properties()->create(['name' => 'Piso Centro', 'type' => 'housing', 'address_line' => 'Calle Mayor 1']);

        return [$user, $portfolio, $property];
    }

    public function test_creating_an_active_lease_generates_the_current_rent_charge(): void
    {
        [$user, $portfolio, $property] = $this->owner();
        $contact = $portfolio->hasMany(Contact::class)->create(['name' => 'Luis Pérez', 'kind' => 'person']);

        $this->actingAs($user)->postJson('/api/v1/leases', [
            'property_id' => $property->id, 'contact_ids' => [$contact->id], 'status' => 'active',
            'start_date' => now()->startOfMonth()->toDateString(), 'monthly_rent' => 900,
            'deposit_amount' => 900, 'payment_day' => 5,
        ])->assertCreated()->assertJsonCount(1, 'charges');

        $this->assertDatabaseHas('rent_charges', ['portfolio_id' => $portfolio->id, 'amount' => 900]);
    }

    public function test_partial_rent_payments_update_the_charge_without_losing_history(): void
    {
        [$user, $portfolio, $property] = $this->owner();
        $lease = Lease::create([
            'portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'status' => 'active',
            'start_date' => now(), 'monthly_rent' => 900, 'payment_day' => 5,
        ]);
        $charge = RentCharge::create([
            'portfolio_id' => $portfolio->id, 'lease_id' => $lease->id, 'period' => now()->format('Y-m'),
            'due_date' => now(), 'amount' => 900, 'paid_amount' => 0, 'status' => 'pending',
        ]);

        $paymentId = $this->actingAs($user)->postJson("/api/v1/rent-charges/{$charge->id}/payments", [
            'amount' => 400, 'transaction_date' => now()->toDateString(),
        ])->assertCreated()->assertJsonPath('category', 'rent')->json('id');

        $this->assertDatabaseHas('rent_charges', ['id' => $charge->id, 'paid_amount' => 400, 'status' => 'partial']);
        $this->assertDatabaseHas('transactions', ['rent_charge_id' => $charge->id, 'amount' => 400]);

        $this->putJson("/api/v1/transactions/{$paymentId}", ['amount' => 300])->assertUnprocessable();
        $this->putJson("/api/v1/rent-payments/{$paymentId}", [
            'amount' => 250, 'transaction_date' => now()->toDateString(), 'payment_method' => 'transfer',
        ])->assertOk()->assertJsonPath('amount', '250.00');
        $this->assertDatabaseHas('rent_charges', ['id' => $charge->id, 'paid_amount' => 250, 'status' => 'partial']);

        $this->deleteJson("/api/v1/rent-payments/{$paymentId}")->assertNoContent();
        $this->assertDatabaseMissing('transactions', ['id' => $paymentId]);
        $this->assertDatabaseHas('rent_charges', ['id' => $charge->id, 'paid_amount' => 0]);
    }

    public function test_cannot_use_a_contact_from_another_portfolio_in_a_lease(): void
    {
        [$user, , $property] = $this->owner();
        $other = Portfolio::create(['name' => 'Otra']);
        $contact = $other->hasMany(Contact::class)->create(['name' => 'Ajeno', 'kind' => 'person']);

        $this->actingAs($user)->postJson('/api/v1/leases', [
            'property_id' => $property->id, 'contact_ids' => [$contact->id], 'status' => 'active',
            'start_date' => now()->toDateString(), 'monthly_rent' => 800, 'payment_day' => 1,
        ])->assertUnprocessable();
    }

    public function test_monthly_charge_generation_is_idempotent(): void
    {
        [, $portfolio, $property] = $this->owner();
        Lease::create([
            'portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'status' => 'active',
            'start_date' => today()->subMonth(), 'monthly_rent' => 725, 'payment_day' => 3,
        ]);

        $this->artisan('rent:generate')->assertSuccessful();
        $this->artisan('rent:generate')->assertSuccessful();

        $this->assertDatabaseCount('rent_charges', 1);
        $this->assertDatabaseHas('rent_charges', ['amount' => 725, 'period' => today()->format('Y-m')]);
    }

    public function test_owner_can_end_an_active_lease(): void
    {
        [$user, $portfolio, $property] = $this->owner();
        $lease = Lease::create([
            'portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'status' => 'active',
            'start_date' => today()->subMonth(), 'monthly_rent' => 725, 'payment_day' => 3,
        ]);

        $this->actingAs($user)->putJson("/api/v1/leases/{$lease->id}", ['status' => 'ended'])
            ->assertOk()->assertJsonPath('status', 'ended');

        $this->assertDatabaseHas('leases', ['id' => $lease->id, 'status' => 'ended', 'end_date' => today()->startOfDay()]);
    }

    public function test_owner_can_edit_contract_terms_without_rewriting_an_existing_charge(): void
    {
        [$user, $portfolio, $property] = $this->owner();
        $contact = Contact::create(['portfolio_id' => $portfolio->id, 'name' => 'María', 'kind' => 'person']);
        $lease = Lease::create([
            'portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'status' => 'active',
            'start_date' => today()->subMonth(), 'monthly_rent' => 700, 'deposit_amount' => 700, 'payment_day' => 3,
        ]);
        $lease->participants()->attach($contact, ['role' => 'tenant', 'is_primary' => true]);
        $charge = RentCharge::create([
            'portfolio_id' => $portfolio->id, 'lease_id' => $lease->id, 'period' => today()->format('Y-m'),
            'due_date' => today(), 'amount' => 700, 'paid_amount' => 0, 'status' => 'pending',
        ]);

        $this->actingAs($user)->putJson("/api/v1/leases/{$lease->id}", [
            'monthly_rent' => 750, 'payment_day' => 7, 'notes' => 'IPC actualizado',
        ])->assertOk()->assertJsonPath('monthly_rent', '750.00')->assertJsonPath('payment_day', 7);

        $this->assertDatabaseHas('rent_charges', ['id' => $charge->id, 'amount' => 700]);
    }

    public function test_renewal_is_created_as_a_draft_with_the_same_participants(): void
    {
        [$user, $portfolio, $property] = $this->owner();
        $contact = Contact::create(['portfolio_id' => $portfolio->id, 'name' => 'María', 'kind' => 'person']);
        $lease = Lease::create([
            'portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'status' => 'active',
            'start_date' => today()->subYear(), 'end_date' => today()->addMonth(),
            'monthly_rent' => 700, 'deposit_amount' => 700, 'payment_day' => 3,
        ]);
        $lease->participants()->attach($contact, ['role' => 'tenant', 'is_primary' => true]);

        $response = $this->actingAs($user)->postJson("/api/v1/leases/{$lease->id}/renew", [
            'start_date' => today()->addMonth()->addDay()->toDateString(),
            'end_date' => today()->addYear()->toDateString(),
            'monthly_rent' => 735, 'deposit_amount' => 700, 'payment_day' => 3,
        ])->assertCreated()->assertJsonPath('status', 'draft')->assertJsonPath('renewed_from_id', $lease->id)
            ->assertJsonCount(1, 'participants');

        $this->actingAs($user)->postJson("/api/v1/leases/{$lease->id}/renew", [
            'start_date' => today()->addMonth()->addDay()->toDateString(),
            'monthly_rent' => 735, 'payment_day' => 3,
        ])->assertUnprocessable();

        $this->assertDatabaseHas('lease_participants', ['lease_id' => $response->json('id'), 'contact_id' => $contact->id]);
    }

    public function test_lease_can_combine_existing_and_new_tenants_without_orphan_contacts(): void
    {
        [$user, $portfolio, $property] = $this->owner();
        $existing = Contact::create(['portfolio_id' => $portfolio->id, 'name' => 'María', 'kind' => 'person']);
        $payload = [
            'property_id' => $property->id, 'contact_ids' => [$existing->id],
            'new_contacts' => [['name' => 'Luis', 'email' => 'luis@example.com']],
            'status' => 'active', 'start_date' => today()->toDateString(),
            'monthly_rent' => 800, 'payment_day' => 5,
        ];
        $response = $this->actingAs($user)->postJson('/api/v1/leases', $payload)
            ->assertCreated()->assertJsonCount(2, 'participants');
        $this->assertDatabaseHas('contacts', ['portfolio_id' => $portfolio->id, 'name' => 'Luis']);
        $this->assertDatabaseHas('lease_participants', ['lease_id' => $response->json('id'), 'contact_id' => $existing->id, 'is_primary' => true]);

        $this->postJson('/api/v1/leases', [
            ...$payload, 'new_contacts' => [['name' => 'No debe crearse']],
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('contacts', ['name' => 'No debe crearse']);
    }

    public function test_lease_can_start_with_only_a_new_tenant(): void
    {
        [$user, $portfolio, $property] = $this->owner();
        $response = $this->actingAs($user)->postJson('/api/v1/leases', [
            'property_id' => $property->id,
            'new_contacts' => [['name' => 'Inquilino nuevo', 'phone' => '600123123']],
            'status' => 'active', 'start_date' => today()->toDateString(),
            'monthly_rent' => 650, 'payment_day' => 5,
        ])->assertCreated()->assertJsonCount(1, 'participants');
        $this->assertDatabaseHas('contacts', ['portfolio_id' => $portfolio->id, 'name' => 'Inquilino nuevo']);
        $this->assertDatabaseHas('lease_participants', ['lease_id' => $response->json('id'), 'is_primary' => true]);
    }

    public function test_existing_lease_can_add_a_new_tenant_inline(): void
    {
        [$user, $portfolio, $property] = $this->owner();
        $existing = Contact::create(['portfolio_id' => $portfolio->id, 'name' => 'María', 'kind' => 'person']);
        $lease = Lease::create([
            'portfolio_id' => $portfolio->id, 'property_id' => $property->id,
            'status' => 'active', 'start_date' => today(), 'monthly_rent' => 800, 'payment_day' => 5,
        ]);
        $lease->participants()->attach($existing, ['role' => 'tenant', 'is_primary' => true]);

        $this->actingAs($user)->putJson("/api/v1/leases/{$lease->id}", [
            'contact_ids' => [$existing->id], 'new_contacts' => [['name' => 'Ana', 'phone' => '600123123']],
        ])->assertOk()->assertJsonCount(2, 'participants');
        $new = Contact::where('portfolio_id', $portfolio->id)->where('name', 'Ana')->firstOrFail();
        $this->assertDatabaseHas('lease_participants', ['lease_id' => $lease->id, 'contact_id' => $new->id]);
    }

    public function test_owner_can_edit_dates_with_existing_tenants_without_rewriting_payments(): void
    {
        [$user, $portfolio, $property] = $this->owner();
        $primary = Contact::create(['portfolio_id' => $portfolio->id, 'name' => 'María', 'kind' => 'person']);
        $secondary = Contact::create(['portfolio_id' => $portfolio->id, 'name' => 'Luis', 'kind' => 'person']);
        $lease = Lease::create([
            'portfolio_id' => $portfolio->id, 'property_id' => $property->id,
            'status' => 'active', 'start_date' => today()->subMonths(2), 'end_date' => today()->addMonth(),
            'monthly_rent' => 800, 'payment_day' => 5,
        ]);
        $lease->participants()->attach([
            $primary->id => ['role' => 'tenant', 'is_primary' => true],
            $secondary->id => ['role' => 'tenant', 'is_primary' => false],
        ]);
        $charge = RentCharge::create([
            'portfolio_id' => $portfolio->id, 'lease_id' => $lease->id, 'period' => today()->format('Y-m'),
            'due_date' => today(), 'amount' => 800, 'paid_amount' => 300, 'status' => 'partial',
        ]);
        $start = today()->subMonth()->toDateString();
        $end = today()->addMonths(2)->toDateString();

        $this->actingAs($user)->putJson("/api/v1/leases/{$lease->id}", [
            'contact_ids' => [$primary->id, $secondary->id], 'new_contacts' => [],
            'start_date' => $start, 'end_date' => $end,
        ])->assertOk()->assertJsonCount(2, 'participants')->assertJsonCount(1, 'charges');

        $this->assertSame($start, $lease->fresh()->start_date->toDateString());
        $this->assertSame($end, $lease->fresh()->end_date->toDateString());
        $this->assertDatabaseHas('lease_participants', ['lease_id' => $lease->id, 'contact_id' => $primary->id, 'is_primary' => true]);
        $this->assertDatabaseHas('lease_participants', ['lease_id' => $lease->id, 'contact_id' => $secondary->id, 'is_primary' => false]);
        $this->assertDatabaseHas('rent_charges', ['id' => $charge->id, 'amount' => 800, 'paid_amount' => 300, 'status' => 'partial']);
        $this->assertDatabaseCount('rent_charges', 1);
    }

    #[DataProvider('unavailableTenantCases')]
    public function test_editing_lease_rejects_unavailable_tenants_without_saving_changes(string $case): void
    {
        [$user, $portfolio, $property] = $this->owner();
        $existing = Contact::create(['portfolio_id' => $portfolio->id, 'name' => 'María', 'kind' => 'person']);
        $lease = Lease::create([
            'portfolio_id' => $portfolio->id, 'property_id' => $property->id,
            'status' => 'active', 'start_date' => today()->subMonth(), 'monthly_rent' => 800, 'payment_day' => 5,
        ]);
        $lease->participants()->attach($existing, ['role' => 'tenant', 'is_primary' => true]);
        $originalStart = $lease->start_date->toDateString();
        $invalidId = $existing->id + 1;
        if ($case !== 'missing') {
            $contactPortfolio = $case === 'foreign' ? Portfolio::create(['name' => 'Otra cartera']) : $portfolio;
            $contact = Contact::create(['portfolio_id' => $contactPortfolio->id, 'name' => 'No disponible', 'kind' => 'person']);
            $invalidId = $contact->id;
            if ($case === 'deleted') {
                $contact->delete();
            }
        }

        $this->actingAs($user)->putJson("/api/v1/leases/{$lease->id}", [
            'contact_ids' => [$existing->id, $invalidId],
            'new_contacts' => [['name' => 'No debe crearse']],
            'start_date' => today()->toDateString(), 'end_date' => today()->addYear()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('contact_ids');

        $this->assertSame($originalStart, $lease->fresh()->start_date->toDateString());
        $this->assertNull($lease->fresh()->end_date);
        $this->assertSame([$existing->id], $lease->participants()->pluck('contacts.id')->all());
        $this->assertDatabaseHas('lease_participants', ['lease_id' => $lease->id, 'contact_id' => $existing->id, 'is_primary' => true]);
        $this->assertDatabaseMissing('contacts', ['name' => 'No debe crearse']);
        $this->assertDatabaseCount('rent_charges', 0);
    }

    public static function unavailableTenantCases(): array
    {
        return ['another portfolio' => ['foreign'], 'nonexistent' => ['missing'], 'archived' => ['deleted']];
    }

    public function test_adding_a_tenant_without_resubmitting_contact_ids_preserves_existing_participants(): void
    {
        [$user, $portfolio, $property] = $this->owner();
        $existing = Contact::create(['portfolio_id' => $portfolio->id, 'name' => 'María', 'kind' => 'person']);
        $lease = Lease::create([
            'portfolio_id' => $portfolio->id, 'property_id' => $property->id,
            'status' => 'active', 'start_date' => today(), 'monthly_rent' => 800, 'payment_day' => 5,
        ]);
        $lease->participants()->attach($existing, ['role' => 'tenant', 'is_primary' => true]);

        $this->actingAs($user)->putJson("/api/v1/leases/{$lease->id}", [
            'new_contacts' => [['name' => 'Ana', 'phone' => '600123123']],
        ])->assertOk()->assertJsonCount(2, 'participants');

        $new = Contact::where('portfolio_id', $portfolio->id)->where('name', 'Ana')->firstOrFail();
        $this->assertDatabaseHas('lease_participants', ['lease_id' => $lease->id, 'contact_id' => $existing->id, 'is_primary' => true]);
        $this->assertDatabaseHas('lease_participants', ['lease_id' => $lease->id, 'contact_id' => $new->id, 'is_primary' => false]);
    }
}
