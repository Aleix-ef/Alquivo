<?php

namespace Tests\Feature;

use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Portfolio\Services\StripeCheckoutGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Subscription;
use Mockery;
use Mockery\MockInterface;
use Stripe\Exception\ApiConnectionException;
use Tests\TestCase;

class BillingCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        config(['beta.billing_enabled' => true, 'plans.founder.prices.monthly' => 'price_beta', 'cashier.secret' => 'sk_test_fake']);
        $user = User::factory()->create(['stripe_id' => 'cus_fake']);
        $portfolio = Portfolio::create(['name' => 'Mi cartera']);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $this->actingAs($user);

        return $user;
    }

    private function gateway(): MockInterface
    {
        $gateway = Mockery::mock(StripeCheckoutGateway::class);
        $gateway->shouldReceive('customer')->andReturn('cus_fake');
        $gateway->shouldReceive('hasOpenSubscription')->andReturn(false)->byDefault();
        $this->app->instance(StripeCheckoutGateway::class, $gateway);

        return $gateway;
    }

    private function checkout()
    {
        return $this->postJson('/api/v1/billing/checkout', ['plan' => 'founder', 'period' => 'monthly']);
    }

    public function test_unpaid_subscription_keeps_portal_available_and_blocks_duplicate_checkout(): void
    {
        $user = $this->owner();
        $this->gateway()->shouldNotReceive('create');
        Subscription::create(['user_id' => $user->id, 'type' => 'default', 'stripe_id' => 'sub_fake', 'stripe_status' => 'past_due', 'stripe_price' => 'price_beta', 'quantity' => 1]);
        $this->getJson('/api/v1/plans')->assertOk()->assertJsonPath('current.can_manage_billing', true)->assertJsonPath('current.payment_pending', true)->assertJsonPath('plans.founder.checkout_available', false);
        $this->checkout()->assertUnprocessable();
    }

    public function test_remote_subscription_blocks_checkout_before_the_webhook_arrives(): void
    {
        $this->owner();
        $gateway = $this->gateway();
        $gateway->shouldReceive('hasOpenSubscription')->once()->andReturn(true);
        $gateway->shouldNotReceive('create');
        $this->checkout()->assertUnprocessable();
    }

    public function test_wrong_stripe_price_does_not_start_a_payment(): void
    {
        $this->owner();
        $gateway = $this->gateway();
        $gateway->shouldReceive('price')->once()->andReturn(['active' => true, 'currency' => 'eur', 'unit_amount' => 2000, 'recurring' => ['interval' => 'month', 'interval_count' => 1]]);
        $gateway->shouldNotReceive('create');
        $this->checkout()->assertStatus(503);
        $this->assertDatabaseCount('billing_checkout_attempts', 0);
    }

    public function test_timeout_reuses_persisted_idempotency_parameters_and_open_session(): void
    {
        $user = $this->owner();
        $gateway = $this->gateway();
        $gateway->shouldReceive('price')->once()->andReturn(['active' => true, 'currency' => 'eur', 'unit_amount' => 699, 'recurring' => ['interval' => 'month', 'interval_count' => 1]]);
        $firstParameters = null;
        $firstKey = null;
        $calls = 0;
        $gateway->shouldReceive('create')->twice()->andReturnUsing(function ($owner, $parameters, $key) use (&$calls, &$firstParameters, &$firstKey) {
            if (++$calls === 1) {
                $firstParameters = $parameters;
                $firstKey = $key;
                throw ApiConnectionException::factory('Simulated timeout');
            }
            $this->assertSame($firstParameters, $parameters);
            $this->assertSame($firstKey, $key);

            return ['id' => 'cs_fake', 'url' => 'https://checkout.stripe.com/test'];
        });
        $this->checkout()->assertStatus(503);
        $this->assertDatabaseCount('billing_checkout_attempts', 1);
        $this->travel(2)->minutes();
        $this->checkout()->assertOk()->assertJsonPath('url', 'https://checkout.stripe.com/test');
        $gateway->shouldReceive('session')->once()->with(Mockery::type(User::class), 'cs_fake')->andReturn(['status' => 'open', 'url' => 'https://checkout.stripe.com/test']);
        $this->checkout()->assertOk();
        $this->assertSame('cs_fake', DB::table('billing_checkout_attempts')->where('user_id', $user->id)->value('stripe_session_id'));
        $this->assertSame('free', $user->portfolio()->plan);
    }
}
