<?php

namespace Tests\Feature;

use App\Domain\Administration\Services\AdminAccess;
use App\Domain\Identity\Services\TwoFactor;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

final class BusinessAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sanctum.stateful' => ['localhost'], 'mail.default' => 'smtp', 'mail.from.address' => 'synthetic@example.test',
            'mail.mailers.smtp.host' => 'disabled.example.test', 'beta.program_enabled' => true]);
        Notification::fake();
        Http::preventStrayRequests();
        $this->withHeader('Origin', 'http://localhost')->withSession([]);
    }

    private function admin(string $method = 'authenticator'): User
    {
        $user = User::factory()->create(['email' => 'soporte@alquivo.com', 'two_factor_method' => $method,
            'two_factor_confirmed_at' => now(), 'two_factor_secret' => (new Google2FA)->generateSecretKey(),
            'two_factor_recovery_codes' => [hash('sha256', 'synthetic-recovery')]]);
        $this->artisan('admin:account soporte@alquivo.com')->assertSuccessful();

        return $user;
    }

    private function proof(User $user): array
    {
        return [AdminAccess::SESSION_KEY => ['user_id' => $user->id,
            'grant_version' => DB::table('business_admins')->where('user_id', $user->id)->value('grant_version'),
            'binding' => app(TwoFactor::class)->binding($user), 'expires_at' => now()->addMinutes(30)->timestamp]];
    }

    private function totp(User $user): string
    {
        return (new Google2FA)->oathTotp($user->two_factor_secret, intdiv(now()->timestamp, 30));
    }

    private function emailed(): string
    {
        return Notification::sent(new AnonymousNotifiable, TwoFactorCodeNotification::class)->last()->code;
    }

    public function test_only_explicit_verified_mfa_account_can_be_granted_without_changing_existing_permissions(): void
    {
        $other = User::factory()->create(['email' => 'someone@example.test', 'role' => 'admin']);
        $this->artisan('admin:account someone@example.test')->assertFailed();
        $this->artisan('admin:account soporte@alquivo.com')->assertFailed();
        $support = User::factory()->create(['email' => 'soporte@alquivo.com', 'email_verified_at' => null]);
        $this->artisan('admin:account soporte@alquivo.com')->assertFailed();
        $support->forceFill(['email_verified_at' => now(), 'two_factor_confirmed_at' => now(), 'two_factor_method' => 'email'])->save();
        $before = $support->fresh()->getAttributes();
        $this->artisan('admin:account soporte@alquivo.com')->assertSuccessful();
        $this->artisan('admin:account soporte@alquivo.com')->assertSuccessful();
        $this->assertDatabaseCount('business_admins', 1);
        $this->assertSame($before, $support->fresh()->getAttributes());
        $this->assertFalse($other->can_admin_business);
        $this->assertTrue($support->fresh()->can_admin_business);
        $this->assertDatabaseCount('support_agents', 0);
    }

    public function test_anonymous_regular_local_admin_and_support_only_cannot_read_admin_metrics(): void
    {
        foreach (['/access', '/overview', '/users', '/ai/users'] as $endpoint) {
            $this->getJson('/api/v1/admin'.$endpoint)->assertUnauthorized();
        }
        $user = User::factory()->create(['email' => 'soporte@alquivo.com', 'role' => 'admin', 'two_factor_method' => 'email', 'two_factor_confirmed_at' => now()]);
        DB::table('support_agents')->insert(['user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->getJson('/api/v1/admin/overview')->assertForbidden();
        $this->postJson('/api/v1/admin/access/verify', ['code' => '000000'])->assertForbidden();
        $this->assertDatabaseCount('business_admins', 0);
    }

    public function test_personal_access_token_cannot_read_or_verify_even_when_account_is_admin(): void
    {
        $user = $this->admin();
        Sanctum::actingAs($user, ['*']);
        $this->withSession($this->proof($user))->getJson('/api/v1/admin/overview')->assertForbidden();
        $this->getJson('/api/v1/admin/access')->assertForbidden();
        $this->postJson('/api/v1/admin/access/verify', ['code' => $this->totp($user)])->assertForbidden();
    }

    public function test_totp_gate_lock_replay_and_no_portfolio_work_without_bypass(): void
    {
        $user = $this->admin();
        $this->actingAs($user)->getJson('/api/v1/admin/access')->assertOk()->assertJsonPath('verified', false);
        $this->getJson('/api/v1/admin/overview')->assertForbidden()->assertJsonPath('code', 'admin_verification_required');
        $code = $this->totp($user);
        $this->postJson('/api/v1/admin/access/verify', ['code' => $code])->assertOk();
        $this->getJson('/api/v1/admin/overview')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('users.total_existing', 0);
        $this->deleteJson('/api/v1/admin/access')->assertNoContent();
        $this->getJson('/api/v1/admin/overview')->assertForbidden();
        $this->postJson('/api/v1/admin/access/verify', ['code' => $code])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_email_challenge_wrong_expired_and_reused_codes_fail(): void
    {
        $user = $this->admin('email');
        $this->actingAs($user)->postJson('/api/v1/admin/access/code')->assertOk();
        $code = $this->emailed();
        $wrong = $code === '000000' ? '000001' : '000000';
        $this->postJson('/api/v1/admin/access/verify', ['code' => $wrong])->assertUnprocessable();
        $this->postJson('/api/v1/admin/access/verify', ['code' => $code])->assertOk();
        $this->deleteJson('/api/v1/admin/access')->assertNoContent();
        $this->postJson('/api/v1/admin/access/verify', ['code' => $code])->assertUnprocessable();
        $this->postJson('/api/v1/admin/access/code')->assertOk();
        $this->travel(6)->minutes();
        $this->postJson('/api/v1/admin/access/verify', ['code' => $this->emailed()])->assertUnprocessable();
    }

    public function test_both_requires_both_factors_and_recovery_is_explicit_and_single_use(): void
    {
        $user = $this->admin('both');
        $this->actingAs($user)->postJson('/api/v1/admin/access/code')->assertOk();
        $code = $this->totp($user);
        $this->postJson('/api/v1/admin/access/verify', ['code' => $code])->assertUnprocessable();
        $this->getJson('/api/v1/admin/overview')->assertForbidden();
        $this->postJson('/api/v1/admin/access/verify', ['code' => $code, 'email_code' => $this->emailed()])->assertOk();
        $this->deleteJson('/api/v1/admin/access')->assertNoContent();
        $this->postJson('/api/v1/admin/access/verify', ['code' => 'synthetic-recovery'])->assertUnprocessable();
        $this->postJson('/api/v1/admin/access/verify', ['code' => 'synthetic-recovery', 'recovery' => true])->assertOk();
        $this->deleteJson('/api/v1/admin/access')->assertNoContent();
        $this->postJson('/api/v1/admin/access/verify', ['code' => 'synthetic-recovery', 'recovery' => true])->assertUnprocessable();
    }

    public function test_expiry_password_change_and_revocation_invalidate_proof_without_disabling_mfa(): void
    {
        $user = $this->admin();
        $proof = $this->proof($user);
        $this->actingAs($user)->withSession($proof)->getJson('/api/v1/admin/overview')->assertOk();
        $this->travel(31)->minutes();
        $this->getJson('/api/v1/admin/overview')->assertForbidden();
        $this->travelBack();
        $user->forceFill(['password' => 'replacement-synthetic-password'])->save();
        // Stateful AuthenticateSession may expire the entire login on a password change.
        $this->withSession($proof)->getJson('/api/v1/admin/overview')->assertUnauthorized();
        $this->actingAs($user);
        $fresh = $this->proof($user);
        $this->artisan('admin:account soporte@alquivo.com --revoke')->assertSuccessful();
        $this->withSession($fresh)->getJson('/api/v1/admin/overview')->assertForbidden();
        $this->artisan('admin:account soporte@alquivo.com')->assertSuccessful();
        $this->withSession($fresh)->getJson('/api/v1/admin/overview')->assertForbidden()->assertJsonPath('code', 'admin_verification_required');
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_changing_email_cannot_transfer_existing_grant(): void
    {
        $user = $this->admin();
        $proof = $this->proof($user);
        $user->forceFill(['email' => 'former@example.test'])->save();
        $replacement = User::factory()->create(['email' => 'soporte@alquivo.com', 'two_factor_confirmed_at' => now()]);
        $this->artisan('admin:account soporte@alquivo.com')->assertFailed();
        $this->actingAs($user)->withSession($proof)->getJson('/api/v1/admin/overview')->assertForbidden();
        $this->actingAs($replacement)->withSession($proof)->getJson('/api/v1/admin/overview')->assertForbidden();
        $this->artisan('admin:account soporte@alquivo.com --revoke')->assertSuccessful();
        $this->assertDatabaseCount('business_admins', 0);
    }

    public function test_metrics_dates_beta_snapshot_privacy_and_bound_query_count(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 6)->setTime(12, 0));
        $admin = $this->admin();
        $customer = User::factory()->create(['name' => 'Synthetic Owner', 'email' => 'owner@example.test', 'created_at' => '2026-10-24 23:30:00']);
        $portfolio = Portfolio::create(['name' => 'NEVER_EXPOSE_PORTFOLIO', 'plan' => 'free']);
        $portfolio->members()->attach($customer->id, ['role' => 'owner']);
        $property = $portfolio->properties()->create(['name' => 'NEVER_EXPOSE_PROPERTY', 'type' => 'housing', 'address_line' => 'PRIVATE_ADDRESS']);
        $portfolio->properties()->create(['name' => 'Deleted', 'type' => 'housing', 'address_line' => 'PRIVATE', 'deleted_at' => now()]);
        DB::table('leases')->insert(['portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'status' => 'active', 'start_date' => '2026-10-01', 'monthly_rent' => 1000]);
        $this->actingAs($admin)->withSession($this->proof($admin));
        DB::enableQueryLog();
        $result = $this->getJson('/api/v1/admin/overview?from=2026-10-25&to=2026-10-25')->assertOk()
            ->assertJsonPath('users.total_existing', 1)->assertJsonPath('users.registered_in_range', 1)
            ->assertJsonPath('users.timeline.0.registrations', 1)->assertJsonPath('plans.distribution.0.code', 'beta')
            ->assertJsonPath('activation.with_property', 1)->assertJsonPath('activation.with_active_lease', 1)
            ->assertJsonPath('usage.properties', 1)->assertJsonPath('usage.active_leases', 1)
            ->assertJsonPath('revenue.mrr', null)->assertJsonPath('retention.status', 'unavailable')->getContent();
        $this->assertLessThan(60, count(DB::getQueryLog()));
        DB::disableQueryLog();
        foreach (['owner@example.test', 'Synthetic Owner', 'NEVER_EXPOSE', 'PRIVATE_ADDRESS', 'password'] as $private) {
            $this->assertStringNotContainsString($private, $result);
        }
        $this->getJson('/api/v1/admin/overview?from=2026-11-07&to=2026-11-08')->assertUnprocessable();
        $this->getJson('/api/v1/admin/overview?from=2025-01-01&to=2026-11-06')->assertUnprocessable();
        $this->getJson('/api/v1/admin/overview?from=2026-02-30')->assertUnprocessable();
        $this->getJson('/api/v1/admin/users?from=2026-10-25&to=2026-10-25')->assertOk()->assertJsonPath('data.0.email', 'owner@example.test')->assertJsonMissingPath('data.0.password');
        $this->getJson('/api/v1/admin/users?q=%25')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/v1/admin/users?page=0')->assertUnprocessable();
    }

    public function test_ai_counts_do_not_duplicate_steps_costs_or_expose_content(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Synthetic']);
        $portfolio->members()->attach($customer->id, ['role' => 'owner']);
        $id = (string) Str::uuid();
        DB::table('ai_runs')->insert(['id' => $id, 'user_id' => $customer->id, 'portfolio_id' => $portfolio->id,
            'client_request_id' => Str::uuid(), 'request_hash' => 'PRIVATE_HASH', 'plan' => 'beta', 'status' => 'failed', 'routing_version' => 'synthetic', 'billing_month' => now()->startOfMonth(),
            'input_tokens' => 140, 'output_tokens' => 20, 'estimated_cost_nano_usd' => 9000, 'reserved_cost_nano_usd' => 800, 'cost_incomplete' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['gpt-6-luna', 9000, 'completed'], ['gpt-6-sol', null, 'failed']] as [$model, $cost, $status]) {
            DB::table('ai_run_steps')->insert(['run_id' => $id, 'kind' => 'provider', 'status' => $status, 'model' => $model,
                'input_tokens' => 70, 'output_tokens' => 10, 'estimated_cost_nano_usd' => $cost, 'metadata' => '{"message":"PRIVATE_PROMPT"}', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('ai_run_steps')->insert(['run_id' => $id, 'kind' => 'tool', 'status' => 'completed', 'estimated_cost_nano_usd' => 999999, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($admin)->withSession($this->proof($admin));
        $response = $this->getJson('/api/v1/admin/overview')->assertOk()->assertJsonPath('ai.runs', 1)->assertJsonPath('ai.provider_calls', 2)
            ->assertJsonPath('ai.failed', 1)->assertJsonPath('ai.failed_provider_calls', 1)
            ->assertJsonPath('ai.known_cost_nano_usd', '9000')->assertJsonPath('ai.reserved_cost_nano_usd', '800')
            ->assertJsonPath('ai.incomplete_cost_runs', 1)->assertJsonPath('ai.by_model.1.unknown_cost_calls', 1);
        $this->assertStringNotContainsString('PRIVATE_', $response->getContent());
        $this->getJson('/api/v1/admin/ai/users')->assertOk()->assertJsonPath('data.0.runs', 1)->assertJsonPath('data.0.known_cost_nano_usd', '9000');
        Http::assertNothingSent();
    }
}
