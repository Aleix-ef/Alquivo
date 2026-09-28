<?php

namespace Tests\Feature;

use App\Domain\Assistant\Services\AssistantUsageService;
use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Portfolio\Services\PlanService;
use App\Domain\Support\Services\SupportAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LocalAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance('env', 'local');
        config(['beta.program_enabled' => true, 'beta.billing_enabled' => false,
            'beta.fiscality_enabled' => false, 'beta.assistant_validated' => false,
            'assistant.enabled' => true, 'services.openai.key' => null]);
        Http::preventStrayRequests();
    }

    private function owner(bool $admin = true): array
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->forceFill(['role' => $admin ? 'admin' : 'user'])->save();
        $portfolio = Portfolio::create(['name' => 'Pruebas', 'plan' => 'free']);
        $portfolio->members()->attach($user, ['role' => 'owner']);

        return [$user, $portfolio];
    }

    public function test_local_admin_sees_hidden_modules_and_catalog_without_changing_public_beta(): void
    {
        [$admin, $portfolio] = $this->owner();
        $this->actingAs($admin)->getJson('/api/v1/auth/me')->assertJsonPath('user.local_admin', true);
        $this->getJson('/api/v1/fiscality')->assertOk();
        $this->getJson('/api/v1/support/team/conversations')->assertOk()->assertJsonPath('can_manage', true);
        $this->getJson('/api/v1/plans')->assertOk()->assertJsonPath('admin_preview', true)
            ->assertJsonCount(3, 'plans')->assertJsonPath('current.code', 'admin')
            ->assertJsonPath('current.properties.limit', 20)->assertJsonPath('plans.founder.checkout_available', false);
        $this->getJson('/api/v1/public/config')->assertJsonPath('fiscality', false)->assertJsonPath('assistant', false);
        $this->getJson('/api/v1/public/plans')->assertJsonCount(1, 'plans')->assertJsonPath('plans.0.code', 'beta');
        $this->assertSame('free', $portfolio->fresh()->plan);
        $this->assertDatabaseCount('support_agents', 0); // Local role must not grant persistent production support access.
    }

    public function test_normal_users_still_have_beta_limits_and_no_admin_access(): void
    {
        $this->owner();
        [$user, $portfolio] = $this->owner(false);
        $this->actingAs($user)->getJson('/api/v1/auth/me')->assertJsonPath('user.local_admin', false);
        $this->getJson('/api/v1/fiscality')->assertNotFound();
        $this->getJson('/api/v1/support/team/conversations')->assertForbidden();
        $this->getJson('/api/v1/plans')->assertJsonCount(1, 'plans')->assertJsonPath('current.properties.limit', 10);
        $this->assertSame('beta', app(PlanService::class)->effectiveCode($portfolio));
    }

    public function test_admin_can_use_own_premium_features_but_not_other_portfolios(): void
    {
        [$admin, $portfolio] = $this->owner();
        [, $other] = $this->owner(false);
        $property = $other->properties()->create(['name' => 'Ajeno', 'type' => 'housing', 'address_line' => 'Calle Test']);
        $this->actingAs($admin)->getJson('/api/v1/properties/'.$property->id)->assertNotFound();
        $this->assertTrue(app(PlanService::class)->hasFeature($portfolio, 'fiscal_reports'));
        for ($i = 0; $i < 10; $i++) {
            $portfolio->properties()->create(['name' => 'Prueba', 'type' => 'housing', 'address_line' => 'Calle Test']);
        }
        $this->postJson('/api/v1/properties', ['name' => 'Once', 'type' => 'housing', 'address_line' => 'Calle Test'])->assertCreated();
        $this->assertSame(50, app(AssistantUsageService::class)->summary($portfolio, $admin)['limit']);
    }

    public function test_admin_does_not_enable_payments_or_bypass_ai_configuration_and_consent(): void
    {
        [$admin] = $this->owner();
        $this->actingAs($admin);
        $this->postJson('/api/v1/billing/checkout', ['plan' => 'founder', 'period' => 'monthly'])->assertForbidden();
        $this->postJson('/api/v1/billing/portal')->assertForbidden();
        $this->getJson('/api/v1/assistant/conversations')->assertJsonPath('available', false)->assertJsonPath('usage.limit', 50);
        $this->postJson('/api/v1/assistant/conversations')->assertForbidden();
        $this->postJson('/api/v1/assistant/activation', ['notice_version' => config('assistant.notice_version'), 'accepted' => true])->assertOk();
        $id = $this->postJson('/api/v1/assistant/conversations')->assertCreated()->json('id');
        $this->postJson('/api/v1/assistant/conversations/'.$id.'/messages', ['message' => 'Resume mi patrimonio'])->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_local_role_has_no_effect_outside_local_environment(): void
    {
        [$admin, $portfolio] = $this->owner();
        foreach (['production', 'staging', 'testing'] as $environment) {
            $this->app->instance('env', $environment);
            $this->assertFalse($admin->local_admin);
            $this->assertFalse(app(SupportAccess::class)->canManage($admin));
            $this->assertSame('beta', app(PlanService::class)->effectiveCode($portfolio));
            $this->artisan('demo:admin')->assertFailed();
        }
    }

    public function test_demo_command_requires_explicit_grant_verification_and_mfa_and_can_revoke(): void
    {
        $this->artisan('demo:admin')->assertFailed();
        $user = User::factory()->unverified()->create(['email' => 'demo@alquivo.test']);
        $this->artisan('demo:admin')->assertFailed();
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->artisan('demo:admin')->assertFailed();
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $password = $user->password;
        $this->artisan('demo:admin')->assertSuccessful();
        $this->assertTrue($user->fresh()->local_admin);
        $this->assertSame($password, $user->fresh()->password);
        $this->artisan('demo:admin --revoke')->assertSuccessful();
        $this->assertFalse($user->fresh()->local_admin);
    }

    public function test_role_cannot_be_self_assigned_by_registration_or_profile_update(): void
    {
        Notification::fake();
        $this->postJson('/api/v1/auth/register', ['name' => 'Test', 'email' => 'new@example.test',
            'password' => 'password123', 'password_confirmation' => 'password123', 'terms_accepted' => true, 'terms_version' => config('legal.terms_version'),
            'role' => 'admin', 'local_admin' => true])->assertCreated()->assertJsonPath('user.local_admin', false);
        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertSame('user', $user->role);
        $this->actingAs($user)->putJson('/api/v1/account', ['name' => 'Nuevo', 'email' => $user->email, 'portfolio_name' => 'Pruebas', 'currency' => 'EUR', 'country_code' => 'ES', 'role' => 'admin', 'local_admin' => true])->assertOk();
        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_verification_mfa_and_browser_auth_remain_required_for_admin(): void
    {
        [$admin] = $this->owner();
        $token = $admin->createToken('test')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/support/team/conversations')->assertForbidden();
        $this->getJson('/api/v1/fiscality')->assertNotFound();
        $this->getJson('/api/v1/plans')->assertJsonPath('admin_preview', false)->assertJsonCount(1, 'plans');
        $admin->forceFill(['two_factor_confirmed_at' => null])->save();
        $this->assertFalse($admin->fresh()->local_admin);
        $admin->forceFill(['two_factor_confirmed_at' => now(), 'email_verified_at' => null])->save();
        $this->assertFalse($admin->fresh()->local_admin);
    }
}
