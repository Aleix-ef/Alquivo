<?php

namespace Tests\Feature;

use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Portfolio\Services\StripePlanSynchronizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;
use Tests\TestCase;

class PlanApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_catalog_exposes_current_usage_and_commercial_limits(): void
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Cartera']);
        $portfolio->members()->attach($user, ['role' => 'owner']);

        $this->actingAs($user)->getJson('/api/v1/plans')->assertOk()
            ->assertJsonPath('current.code', 'free')->assertJsonPath('current.properties.limit', 1)
            ->assertJsonPath('plans.founder.property_limit', 20)
            ->assertJsonPath('plans.free.property_limit', 1)
            ->assertJsonPath('plans.founder.price_monthly', 6.99)
            ->assertJsonMissingPath('plans.investor');
    }

    public function test_free_plan_cannot_create_more_than_one_property(): void
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Cartera']);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $portfolio->properties()->create(['name' => 'Piso 1', 'type' => 'housing', 'address_line' => 'Calle 1']);

        $this->actingAs($user)->postJson('/api/v1/properties', [
            'name' => 'Segundo piso', 'type' => 'housing', 'address_line' => 'Calle 2',
        ])->assertUnprocessable()->assertJsonValidationErrors('plan');
        $this->assertDatabaseCount('properties', 1);
    }

    public function test_active_product_trial_always_uses_founder_limits(): void
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::create([
            'name' => 'Cartera', 'plan' => 'free', 'subscription_status' => 'trialing',
            'trial_ends_at' => now()->addDays(14), 'storage_limit_bytes' => 52428800,
        ]);
        $portfolio->members()->attach($user, ['role' => 'owner']);

        $this->actingAs($user)->getJson('/api/v1/plans')->assertOk()
            ->assertJsonPath('current.code', 'founder')
            ->assertJsonPath('current.on_trial', true)
            ->assertJsonPath('current.properties.limit', 20)
            ->assertJsonPath('current.storage.limit', 2147483648)
            ->assertJsonPath('plans.free.property_limit', 1);
    }

    public function test_expired_product_trial_falls_back_to_free_plan(): void
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::create([
            'name' => 'Cartera', 'plan' => 'free', 'subscription_status' => 'trialing',
            'trial_ends_at' => now()->subMinute(), 'storage_limit_bytes' => 52428800,
        ]);
        $portfolio->members()->attach($user, ['role' => 'owner']);

        $this->actingAs($user)->getJson('/api/v1/plans')->assertOk()
            ->assertJsonPath('current.code', 'free')
            ->assertJsonPath('current.status', 'free')
            ->assertJsonPath('current.on_trial', false)
            ->assertJsonPath('current.properties.limit', 1)
            ->assertJsonPath('current.storage.limit', 52428800);
    }

    public function test_stripe_price_synchronizes_the_internal_plan_and_limits(): void
    {
        config(['plans.founder.prices.monthly' => 'price_founder_test']);
        $user = User::factory()->create(['stripe_id' => 'cus_test']);
        $portfolio = Portfolio::create(['name' => 'Cartera']);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        Subscription::create([
            'user_id' => $user->id, 'type' => 'default', 'stripe_id' => 'sub_test',
            'stripe_status' => 'active', 'stripe_price' => 'price_founder_test', 'quantity' => 1,
        ]);

        app(StripePlanSynchronizer::class)->sync($user);

        $this->assertDatabaseHas('portfolios', [
            'id' => $portfolio->id, 'plan' => 'founder', 'subscription_status' => 'active',
            'storage_limit_bytes' => 2147483648, 'billing_customer_id' => 'cus_test',
            'billing_subscription_id' => 'sub_test',
        ]);
    }

    public function test_missing_subscription_synchronizes_to_free_plan(): void
    {
        $user = User::factory()->create(['stripe_id' => 'cus_test']);
        $portfolio = Portfolio::create(['name' => 'Cartera', 'plan' => 'founder']);
        $portfolio->members()->attach($user, ['role' => 'owner']);

        app(StripePlanSynchronizer::class)->sync($user);

        $this->assertDatabaseHas('portfolios', [
            'id' => $portfolio->id, 'plan' => 'free', 'subscription_status' => 'cancelled',
            'storage_limit_bytes' => 52428800, 'billing_subscription_id' => null,
        ]);
    }

    public function test_beta_checkout_only_accepts_the_monthly_founder_plan(): void
    {
        config(['beta.billing_enabled' => true]);
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Cartera']);
        $portfolio->members()->attach($user, ['role' => 'owner']);

        $this->actingAs($user)->postJson('/api/v1/billing/checkout', [
            'plan' => 'free', 'period' => 'monthly',
        ])->assertUnprocessable()->assertJsonValidationErrors('plan');

        $this->actingAs($user)->postJson('/api/v1/billing/checkout', [
            'plan' => 'founder', 'period' => 'yearly',
        ])->assertUnprocessable()->assertJsonValidationErrors('period');

        $this->actingAs($user)->postJson('/api/v1/billing/change-plan', [
            'plan' => 'founder', 'period' => 'monthly',
        ])->assertNotFound();
    }
}
