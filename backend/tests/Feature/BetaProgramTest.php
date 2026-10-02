<?php

namespace Tests\Feature;

use App\Domain\Assistant\Services\AssistantUsageService;
use App\Domain\Documents\Models\Document;
use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Portfolio\Services\CheckoutService;
use App\Domain\Portfolio\Services\PlanService;
use App\Domain\Portfolio\Services\StorageUsageService;
use App\Domain\Portfolio\Services\StripeCheckoutGateway;
use App\Domain\Properties\Models\PropertyPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
            ->assertJsonPath('plans.0.property_limit', 50)->assertJsonPath('plans.0.storage_limit_bytes', 5368709120)
            ->assertJsonMissingPath('plans.0.prices');
        $this->getJson('/api/v1/public/config')->assertJsonPath('beta_program', true)->assertJsonPath('billing_enabled', false);
        $response = $this->getJson('/api/v1/plans')->assertOk()->assertJsonCount(1, 'plans')
            ->assertJsonPath('current.code', 'beta')->assertJsonPath('current.on_trial', false)->assertJsonPath('current.trial_ends_at', null)
            ->assertJsonPath('current.properties.limit', 50)->assertJsonPath('current.storage.limit', 5368709120)
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

    public function test_larger_beta_does_not_enable_or_advertise_features_under_review(): void
    {
        config(['beta.fiscality_enabled' => false, 'beta.assistant_validated' => false,
            'assistant.enabled' => true, 'services.openai.key' => 'synthetic-test-key']);
        Http::preventStrayRequests();
        [, $portfolio] = $this->owner();
        $this->getJson('/api/v1/public/config')->assertOk()
            ->assertJsonPath('beta_program', true)->assertJsonPath('billing_enabled', false)
            ->assertJsonPath('assistant', false)->assertJsonPath('fiscality', false);
        foreach (['/api/v1/public/plans', '/api/v1/plans'] as $endpoint) {
            $catalog = $this->getJson($endpoint)->assertOk()->getContent();
            $this->assertStringContainsString('Hasta 50 inmuebles', $catalog);
            $this->assertStringContainsString('5 GB de documentos y fotos', $catalog);
            $this->assertStringNotContainsString('Fiscalidad', $catalog);
            $this->assertStringNotContainsString('Asistente', $catalog);
        }
        $this->assertFalse(app(PlanService::class)->hasFeature($portfolio, 'fiscal_reports'));
        $this->getJson('/api/v1/fiscality')->assertNotFound();
        $this->postJson('/api/v1/assistant/activation')->assertNotFound();
        $this->postJson('/api/v1/assistant/conversations')->assertNotFound();
        Http::assertNothingSent();
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
        $this->getJson('/api/v1/account/usage')->assertJsonPath('code', 'beta')->assertJsonPath('properties.limit', 50)->assertJsonPath('storage.limit', 5368709120);
        $this->assertSame('founder', $portfolio->fresh()->plan); // No destructive rewrite of paid history.
        Notification::fake();
        $this->postJson('/api/v1/auth/register', ['name' => 'Nuevo', 'email' => 'new@beta.test', 'password' => 'secret1234', 'password_confirmation' => 'secret1234', 'terms_accepted' => true, 'terms_version' => config('legal.terms_version')])->assertCreated();
        $newUser = User::where('email', 'new@beta.test')->firstOrFail();
        $new = $newUser->portfolio();
        $this->assertNull($newUser->email_verified_at);
        $this->assertNull($new->trial_ends_at);
        $this->assertSame('beta', app(PlanService::class)->effectiveCode($new));
        $this->postJson('/api/v1/properties', ['name' => 'Primer piso', 'type' => 'housing', 'address_line' => 'Calle Beta'])->assertCreated();
        $this->actingAs($newUser)->getJson('/api/v1/account/usage')->assertJsonPath('code', 'beta')
            ->assertJsonPath('properties.limit', 50)->assertJsonPath('storage.limit', 5368709120);
    }

    public function test_fiftieth_property_is_allowed_fifty_first_denied_and_excess_data_preserved(): void
    {
        [$user, $portfolio] = $this->owner();
        for ($i = 1; $i <= 49; $i++) {
            $portfolio->properties()->create(['name' => "Piso {$i}", 'type' => 'housing', 'address_line' => 'Calle Test']);
        }
        $payload = ['name' => 'Inmueble 50', 'type' => 'housing', 'address_line' => 'Calle Test'];
        $this->postJson('/api/v1/properties', $payload)->assertCreated();
        $this->postJson('/api/v1/properties', $payload)->assertUnprocessable()->assertJsonValidationErrors('plan');
        $extra = $portfolio->properties()->create($payload);
        $this->getJson('/api/v1/account/usage')->assertJsonPath('properties.used', 51)
            ->assertJsonPath('properties.read_only_count', 1)->assertJsonCount(50, 'properties.editable_ids');
        $this->getJson('/api/v1/properties/'.$extra->id)->assertOk();
        $this->putJson('/api/v1/properties/'.$extra->id, ['name' => 'Changed'])->assertUnprocessable();
        $this->assertDatabaseCount('properties', 51);
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
        $storage = app(StorageUsageService::class);
        $storage->assertCanStore($portfolio, 5368709120);
        $this->expectException(ValidationException::class);
        $storage->assertCanStore($portfolio, 5368709121);
    }

    public function test_beta_storage_counts_documents_and_photos_only_in_own_portfolio(): void
    {
        [$user, $portfolio] = $this->owner();
        $property = $portfolio->properties()->create(['name' => 'Sintético', 'type' => 'housing', 'address_line' => 'Calle Test']);
        // Synthetic metadata only: no actual large files are created or uploaded.
        Document::create(['portfolio_id' => $portfolio->id, 'uploaded_by' => $user->id,
            'name' => 'Sintético', 'category' => 'other', 'storage_key' => 'synthetic/document',
            'original_filename' => 'synthetic.pdf', 'mime_type' => 'application/pdf', 'size' => 3221225472]);
        PropertyPhoto::create(['property_id' => $property->id, 'storage_key' => 'synthetic/photo',
            'mime_type' => 'image/jpeg', 'size' => 2147483647]);
        $other = Portfolio::create(['name' => 'Ajena', 'plan' => 'free']);
        $otherProperty = $other->properties()->create(['name' => 'Ajeno', 'type' => 'housing', 'address_line' => 'Calle Test']);
        Document::create(['portfolio_id' => $other->id, 'uploaded_by' => $user->id,
            'name' => 'Ajeno', 'category' => 'other', 'storage_key' => 'synthetic/other-document',
            'original_filename' => 'synthetic.pdf', 'mime_type' => 'application/pdf', 'size' => 5368709120]);
        PropertyPhoto::create(['property_id' => $otherProperty->id, 'storage_key' => 'synthetic/other-photo',
            'mime_type' => 'image/jpeg', 'size' => 5368709120]);
        $storage = app(StorageUsageService::class);
        $this->assertSame(5368709119, $storage->used($portfolio));
        $this->getJson('/api/v1/account/usage')->assertJsonPath('storage.used', 5368709119)
            ->assertJsonPath('storage.limit', 5368709120);
        $storage->assertCanStore($portfolio, 1);
        $this->expectException(ValidationException::class);
        $storage->assertCanStore($portfolio, 2);
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
