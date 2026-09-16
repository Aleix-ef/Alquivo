<?php

namespace Tests\Feature;

use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Portfolio\Services\CheckoutService;
use App\Domain\Portfolio\Services\StripeCheckoutGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ValidationBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_exposes_prices_but_blocks_all_billing_entry_points(): void
    {
        config(['beta.billing_enabled' => false, 'cashier.secret' => 'sk_test_fake', 'plans.founder.prices.monthly' => 'price_fake']);
        $user = User::factory()->create(['stripe_id' => 'cus_fake']);
        $portfolio = Portfolio::create(['name' => 'Cartera']);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $this->mock(StripeCheckoutGateway::class)->shouldNotReceive('customer', 'create', 'session');
        $this->getJson('/api/v1/public/config')->assertJsonPath('billing_enabled', false);
        $this->getJson('/api/v1/public/plans')->assertOk()->assertJsonFragment(['price_monthly' => 6.99]);
        $this->actingAs($user)->getJson('/api/v1/plans')->assertOk()
            ->assertJsonPath('billing_enabled', false)->assertJsonPath('plans.founder.checkout_available', false)
            ->assertJsonPath('current.can_manage_billing', false)->assertJsonPath('current.has_billing_history', true);
        $this->postJson('/api/v1/billing/checkout', ['plan' => 'founder', 'period' => 'monthly'])->assertForbidden();
        $this->postJson('/api/v1/billing/portal')->assertForbidden();
        $this->assertDatabaseCount('billing_checkout_attempts', 0);
    }

    public function test_service_cannot_bypass_validation_gate(): void
    {
        config(['beta.billing_enabled' => false]);
        $this->expectException(HttpException::class);
        app(CheckoutService::class)->start(User::factory()->create(), 'http://localhost');
    }
}
