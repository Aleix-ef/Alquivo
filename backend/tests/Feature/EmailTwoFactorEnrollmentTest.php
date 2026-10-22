<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\TrustedDevice;
use App\Domain\Identity\Services\TrustedDevices;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

final class EmailTwoFactorEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        config(['app.url' => 'http://localhost', 'sanctum.stateful' => ['localhost'], 'session.secure' => true,
            'mail.default' => 'smtp', 'mail.from.address' => 'synthetic@example.test', 'mail.mailers.smtp.host' => 'disabled.example.test']);
        Notification::fake();
        Http::preventStrayRequests();
        $this->withHeader('Origin', 'http://localhost')->withSession([]);
    }

    private function owner(): User
    {
        $user = User::factory()->create(['password' => 'synthetic-password123']);
        $portfolio = Portfolio::create(['name' => 'Synthetic portfolio', 'currency' => 'EUR', 'country_code' => 'ES']);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $this->actingAs($user);

        return $user;
    }

    private function emailCode(): string
    {
        $sent = Notification::sent(new AnonymousNotifiable, TwoFactorCodeNotification::class)->last();
        $this->assertNotNull($sent);

        return $sent->code;
    }

    private function enroll(User $user): array
    {
        $this->postJson('/api/v1/account/two-factor/setup', ['current_password' => 'synthetic-password123'])->assertOk()->assertJsonPath('method', 'email')->assertJsonPath('secret', null);

        return $this->postJson('/api/v1/account/two-factor/confirm', ['code' => $this->emailCode()])->assertOk()->json('recovery_codes');
    }

    private function securityCode(string $purpose, array $extra = []): string
    {
        $this->postJson('/api/v1/account/two-factor/security-code', ['current_password' => 'synthetic-password123', 'purpose' => $purpose, ...$extra])->assertOk();

        return $this->emailCode();
    }

    private function login(User $user)
    {
        Auth::guard('web')->logout();
        $this->flushSession();

        return $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'synthetic-password123']);
    }

    public function test_email_is_the_default_enrollment_and_requires_confirmation_before_activation(): void
    {
        $user = $this->owner();
        $this->getJson('/api/v1/account/two-factor')->assertOk()->assertJsonPath('method', 'email');
        $this->postJson('/api/v1/account/two-factor/setup', ['current_password' => 'wrong'])->assertUnprocessable();
        Notification::assertNothingSent();
        $this->postJson('/api/v1/account/two-factor/setup', ['current_password' => 'synthetic-password123'])->assertOk()->assertJsonPath('secret', null);
        $code = $this->emailCode();
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        $this->assertNotSame($code, session('two_factor_security_code.hash'));
        $wrong = $code === '000000' ? '000001' : '000000';
        $this->postJson('/api/v1/account/two-factor/confirm', ['code' => $wrong])->assertUnprocessable();
        $response = $this->postJson('/api/v1/account/two-factor/confirm', ['code' => $code, 'remember_device' => true])->assertOk();
        $this->assertCount(8, $response->json('recovery_codes'));
        $this->assertNotSame($response->json('recovery_codes'), $user->fresh()->two_factor_recovery_codes);
        $this->assertSame('email', $user->fresh()->two_factor_method);
        $this->assertNull($user->fresh()->two_factor_secret);
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $cookie = $response->getCookie(TrustedDevices::COOKIE);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame(now()->addDays(90)->timestamp, $cookie->getExpiresTime());
        $this->postJson('/api/v1/account/two-factor/confirm', ['code' => $code])->assertUnprocessable();
    }

    public function test_unverified_or_unconfigured_email_cannot_enable_email_mfa(): void
    {
        $user = $this->owner();
        $user->forceFill(['email_verified_at' => null])->save();
        $this->postJson('/api/v1/account/two-factor/setup', ['current_password' => 'synthetic-password123'])->assertUnprocessable();
        $user->forceFill(['email_verified_at' => now()])->save();
        config(['mail.default' => 'array']);
        $this->postJson('/api/v1/account/two-factor/setup', ['current_password' => 'synthetic-password123'])->assertStatus(503);
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        Notification::assertNothingSent();
    }

    public function test_email_only_login_completes_the_frontend_contract_and_remembers_the_browser(): void
    {
        $user = $this->owner();
        $this->enroll($user);
        $this->login($user)->assertOk()->assertJsonPath('methods', ['email'])->assertJsonMissingPath('user');
        $this->assertGuest('web');
        $response = $this->postJson('/api/v1/auth/two-factor', ['code' => $this->emailCode(), 'method' => 'email', 'remember_device' => true])->assertOk()
            ->assertJsonPath('challenge_complete', true)->assertJsonPath('user.id', $user->id);
        $this->withCookie(TrustedDevices::COOKIE, $response->getCookie(TrustedDevices::COOKIE)->getValue())->withCredentials();
        $this->login($user)->assertOk()->assertJsonPath('trusted_device', true)->assertJsonPath('user.id', $user->id);
        $this->assertSame(1, TrustedDevice::count());
    }

    public function test_email_setup_expiry_password_binding_and_cancellation_are_enforced(): void
    {
        $user = $this->owner();
        $this->postJson('/api/v1/account/two-factor/setup', ['current_password' => 'synthetic-password123'])->assertOk();
        $code = $this->emailCode();
        $this->travel(6)->minutes();
        $this->postJson('/api/v1/account/two-factor/confirm', ['code' => $code])->assertUnprocessable();
        $this->postJson('/api/v1/account/two-factor/setup/resend')->assertOk();
        $code = $this->emailCode();
        $this->deleteJson('/api/v1/account/two-factor/setup')->assertNoContent();
        $this->postJson('/api/v1/account/two-factor/confirm', ['code' => $code])->assertUnprocessable();
        $this->postJson('/api/v1/account/two-factor/setup', ['current_password' => 'synthetic-password123'])->assertOk();
        $code = $this->emailCode();
        $user->update(['password' => 'changed-password123']);
        $this->postJson('/api/v1/account/two-factor/confirm', ['code' => $code])->assertUnauthorized();
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_security_email_is_action_bound_and_can_disable_an_email_only_factor(): void
    {
        $user = $this->owner();
        $this->enroll($user);
        $code = $this->securityCode('change_method', ['method' => 'authenticator']);
        $this->deleteJson('/api/v1/account/two-factor', ['current_password' => 'synthetic-password123', 'code' => $code])->assertUnprocessable();
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->travel(1)->minutes();
        $code = $this->securityCode('disable');
        $this->deleteJson('/api/v1/account/two-factor', ['current_password' => 'synthetic-password123', 'code' => $code])->assertNoContent();
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        $this->assertSame(0, TrustedDevice::count());
    }

    public function test_email_only_user_can_add_an_authenticator_without_disabling_existing_mfa(): void
    {
        $user = $this->owner();
        $this->enroll($user);
        $code = $this->securityCode('change_method', ['method' => 'authenticator']);
        $secret = $this->postJson('/api/v1/account/two-factor/setup', ['current_password' => 'synthetic-password123', 'method' => 'authenticator', 'code' => $code])
            ->assertOk()->json('secret');
        $this->assertSame('email', $user->fresh()->two_factor_method);
        $this->assertNull($user->fresh()->two_factor_secret);
        $totp = (new Google2FA)->oathTotp($secret, intdiv(now()->timestamp, 30));
        $this->postJson('/api/v1/account/two-factor/confirm', ['code' => $totp])->assertOk()->assertJsonPath('method', 'authenticator');
        $this->assertNotNull($user->fresh()->two_factor_secret);
        $this->assertSame('authenticator', $user->fresh()->two_factor_method);
    }

    public function test_both_enrollment_requires_email_and_authenticator_and_never_accepts_recovery_as_new_totp(): void
    {
        $user = $this->owner();
        $secret = $this->postJson('/api/v1/account/two-factor/setup', ['current_password' => 'synthetic-password123', 'method' => 'both'])->assertOk()->json('secret');
        $email = $this->emailCode();
        $totp = (new Google2FA)->oathTotp($secret, intdiv(now()->timestamp, 30));
        $this->postJson('/api/v1/account/two-factor/confirm', ['code' => $totp])->assertUnprocessable();
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        $this->postJson('/api/v1/account/two-factor/confirm', ['code' => $totp, 'email_code' => $email])->assertOk();
        $this->assertSame('both', $user->fresh()->two_factor_method);
        $this->travel(1)->minutes();
        $this->login($user)->assertOk();
        $email = $this->emailCode();
        $totp = (new Google2FA)->oathTotp($user->fresh()->two_factor_secret, intdiv(now()->timestamp, 30));
        $this->postJson('/api/v1/auth/two-factor', ['code' => $totp, 'method' => 'recovery'])->assertUnprocessable();
        $this->assertGuest('web');
        $this->postJson('/api/v1/auth/two-factor', ['code' => $totp, 'method' => 'authenticator'])->assertOk()->assertJsonPath('challenge_complete', false);
        $this->assertGuest('web');
        $this->postJson('/api/v1/auth/two-factor', ['code' => $email, 'method' => 'email'])->assertOk()->assertJsonPath('challenge_complete', true);
    }

    public function test_email_change_requires_the_old_factor_and_preserves_a_recovery_path(): void
    {
        $user = $this->owner();
        $recovery = $this->enroll($user);
        $payload = ['name' => 'Synthetic owner', 'email' => 'new-synthetic@example.test', 'portfolio_name' => 'Synthetic', 'currency' => 'EUR', 'country_code' => 'ES', 'current_password' => 'synthetic-password123'];
        $this->putJson('/api/v1/account', $payload)->assertUnprocessable();
        $code = $this->securityCode('change_email', ['new_email' => $payload['email']]);
        $this->putJson('/api/v1/account', [...$payload, 'two_factor_code' => $code])->assertOk()->assertJsonPath('user.email_verified_at', null);
        $this->assertSame('email', $user->fresh()->two_factor_method);
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->login($user->fresh())->assertOk()->assertJsonPath('methods', ['recovery']);
        $this->postJson('/api/v1/auth/two-factor', ['code' => $recovery[0], 'method' => 'recovery'])->assertOk()->assertJsonPath('challenge_complete', true);
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_new_security_code_resend_replaces_the_previous_code_and_failed_attempts_are_limited(): void
    {
        $user = $this->owner();
        $this->enroll($user);
        $old = $this->securityCode('disable');
        $this->travel(6)->minutes();
        $new = $this->securityCode('disable');
        if ($old !== $new) {
            $this->deleteJson('/api/v1/account/two-factor', ['current_password' => 'synthetic-password123', 'code' => $old])->assertUnprocessable();
        }
        $wrong = $new === '000000' ? '000001' : '000000';
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::clear('two-factor:'.$user->id);
            $this->travel(1)->minutes();
            $this->deleteJson('/api/v1/account/two-factor', ['current_password' => 'synthetic-password123', 'code' => $wrong])->assertStatus($i === 4 && $old !== $new ? 429 : 422);
        }
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_existing_authenticator_users_keep_their_choice_and_can_switch_only_with_a_valid_existing_factor(): void
    {
        $user = $this->owner();
        $secret = (new Google2FA)->generateSecretKey();
        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_secret' => $secret, 'two_factor_method' => 'authenticator'])->save();
        $this->getJson('/api/v1/account/two-factor')->assertOk()->assertJsonPath('method', 'authenticator');
        $this->postJson('/api/v1/account/two-factor/setup', ['current_password' => 'synthetic-password123'])->assertConflict();
        $this->putJson('/api/v1/account/two-factor/method', ['method' => 'email', 'current_password' => 'synthetic-password123', 'code' => 'invalid'])->assertUnprocessable();
        $this->assertSame('authenticator', $user->fresh()->two_factor_method);
        $totp = (new Google2FA)->oathTotp($secret, intdiv(now()->timestamp, 30));
        $this->putJson('/api/v1/account/two-factor/method', ['method' => 'email', 'current_password' => 'synthetic-password123', 'code' => $totp])->assertOk()->assertJsonPath('method', 'email');
        $this->login($user->fresh())->assertOk()->assertJsonPath('methods', ['email']);
        $this->postJson('/api/v1/auth/two-factor', ['code' => $this->emailCode(), 'method' => 'email'])->assertOk()->assertJsonPath('challenge_complete', true);
    }

    public function test_a_recovery_only_challenge_cannot_accept_a_dormant_totp_even_without_a_method_parameter(): void
    {
        $user = $this->owner();
        $secret = (new Google2FA)->generateSecretKey();
        $user->forceFill(['email_verified_at' => null, 'two_factor_confirmed_at' => now(), 'two_factor_secret' => $secret,
            'two_factor_method' => 'email', 'two_factor_recovery_codes' => [hash('sha256', 'synthetic-recovery-only')]])->save();
        $this->login($user)->assertOk()->assertJsonPath('methods', ['recovery']);
        $totp = (new Google2FA)->oathTotp($secret, intdiv(now()->timestamp, 30));
        $this->postJson('/api/v1/auth/two-factor', ['code' => $totp])->assertUnprocessable();
        $this->assertGuest('web');
        $this->postJson('/api/v1/auth/two-factor', ['code' => 'synthetic-recovery-only'])->assertOk()->assertJsonPath('challenge_complete', true);
    }
}
