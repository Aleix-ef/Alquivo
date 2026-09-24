<?php

namespace Tests\Feature;

use App\Domain\Assistant\Services\AssistantUsageService;
use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Portfolio\Services\CheckoutService;
use App\Domain\Portfolio\Services\PlanService;
use App\Domain\Portfolio\Services\StorageUsageService;
use App\Domain\Portfolio\Services\StripeCheckoutGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BetaProgramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['beta.program_enabled' => true]);
    }

    private function owner(): array
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Cartera', 'plan' => 'founder', 'trial_ends_at' => now()->addDays(14)]);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $this->actingAs($user);

        return [$user, $portfolio];
    }

    public function test_only_beta_is_exposed_and_old_billing_cannot_be_reached(): void
    {
        config(['beta.billing_enabled' => true, 'cashier.secret' => 'sk_test_fake', 'plans.founder.prices.monthly' => 'price_fake']);
        $this->owner();
        $this->mock(StripeCheckoutGateway::class)->shouldNotReceive('customer', 'create');
        $this->getJson('/api/v1/public/plans')->assertOk()->assertJsonCount(1, 'plans')
            ->assertJsonPath('plans.0.code', 'beta')->assertJsonPath('plans.0.price_monthly', 0)
            ->assertJsonMissingPath('plans.0.prices');
        $this->getJson('/api/v1/public/config')->assertJsonPath('beta_program', true)->assertJsonPath('billing_enabled', false);
        $response = $this->getJson('/api/v1/plans')->assertOk()->assertJsonCount(1, 'plans')
            ->assertJsonPath('current.code', 'beta')->assertJsonPath('current.on_trial', false)->assertJsonPath('current.trial_ends_at', null)
            ->assertJsonPath('plans.beta.checkout_available', false)->assertJsonMissingPath('plans.founder')->assertJsonMissingPath('plans.free');
        $this->assertStringNotContainsString('founder', $response->getContent());
        $this->getJson('/api/v1/auth/me')->assertJsonMissingPath('portfolio.plan')->assertJsonMissingPath('portfolio.pending_plan');
        foreach (['founder', 'free', 'beta'] as $plan) {
            $this->postJson('/api/v1/billing/checkout', ['plan' => $plan, 'period' => 'monthly'])->assertForbidden();
        }
        $this->postJson('/api/v1/billing/portal')->assertForbidden();
        $this->postJson('/api/v1/billing/change-plan', ['plan' => 'founder'])->assertNotFound();
        $this->getJson('/api/v1/plans/founder')->assertNotFound();
        $this->assertDatabaseCount('billing_checkout_attempts', 0);
    }

    public function test_checkout_service_cannot_bypass_beta_even_if_billing_flag_is_on(): void
    {
        config(['beta.billing_enabled' => true]);
        [$user] = $this->owner();
        $this->expectException(HttpException::class);
        app(CheckoutService::class)->start($user, 'http://localhost');
    }

    public function test_existing_and_new_accounts_have_beta_without_fourteen_day_expiry(): void
    {
        [$user, $portfolio] = $this->owner();
        $this->travel(60)->days();
        $this->getJson('/api/v1/account/usage')->assertJsonPath('code', 'beta')->assertJsonPath('properties.limit', 10)->assertJsonPath('storage.limit', 1073741824);
        $this->assertSame('founder', $portfolio->fresh()->plan); // No destructive rewrite of paid history.
        Notification::fake();
        $this->postJson('/api/v1/auth/register', ['name' => 'Nuevo', 'email' => 'new@beta.test', 'password' => 'secret1234', 'password_confirmation' => 'secret1234', 'terms_accepted' => true])->assertCreated();
        $new = User::where('email', 'new@beta.test')->firstOrFail()->portfolio();
        $this->assertNull($new->trial_ends_at);
        $this->assertSame('beta', app(PlanService::class)->effectiveCode($new));
    }

    public function test_tenth_property_is_allowed_eleventh_denied_and_excess_data_preserved(): void
    {
        [$user, $portfolio] = $this->owner();
        for ($i = 1; $i <= 9; $i++) {
            $portfolio->properties()->create(['name' => "Piso {$i}", 'type' => 'housing', 'address_line' => 'Calle Test']);
        }
        $payload = ['name' => 'Décimo', 'type' => 'housing', 'address_line' => 'Calle Test'];
        $this->postJson('/api/v1/properties', $payload)->assertCreated();
        $this->postJson('/api/v1/properties', $payload)->assertUnprocessable()->assertJsonValidationErrors('plan');
        $extra = $portfolio->properties()->create($payload);
        $this->getJson('/api/v1/properties/'.$extra->id)->assertOk();
        $this->putJson('/api/v1/properties/'.$extra->id, ['name' => 'Changed'])->assertUnprocessable();
        $this->assertDatabaseCount('properties', 11);
    }

    public function test_beta_storage_and_ai_quotas_apply_even_to_old_trial_accounts(): void
    {
        [$user, $portfolio] = $this->owner();
        $usage = app(AssistantUsageService::class);
        $this->assertSame(20, $usage->summary($portfolio, $user)['limit']);
        for ($i = 0; $i < 20; $i++) {
            $usage->reserve($portfolio, $user);
        }
        $this->assertSame(0, $usage->summary($portfolio, $user)['remaining']);
        try {
            $usage->reserve($portfolio, $user);
            $this->fail('Quota should be enforced');
        } catch (ValidationException) {
            $this->assertSame(20, $usage->summary($portfolio, $user)['used']);
        }
        $this->expectException(ValidationException::class);
        app(StorageUsageService::class)->assertCanStore($portfolio, 1073741825);
    }

    public function test_commercial_catalog_can_be_restored_without_charging_or_losing_beta_data(): void
    {
        [$user, $portfolio] = $this->owner();
        config(['beta.program_enabled' => false, 'beta.billing_enabled' => false]);
        $this->getJson('/api/v1/public/plans')->assertJsonCount(2, 'plans')->assertJsonPath('plans.1.code', 'founder');
        $this->getJson('/api/v1/plans')->assertJsonMissingPath('plans.beta')->assertJsonPath('billing_enabled', false);
        $this->assertSame('founder', $portfolio->fresh()->plan);
    }
}
