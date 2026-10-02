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
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

final class TwoFactorMethodsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        config(['app.url' => 'http://localhost', 'sanctum.stateful' => ['localhost'],
            'mail.default' => 'smtp', 'mail.from.address' => 'audit@example.test',
            'mail.mailers.smtp.host' => 'disabled.example.test', 'session.secure' => true]);
        Notification::fake();
        Http::preventStrayRequests();
        $this->withHeader('Origin', 'http://localhost')->withSession([]);
    }

    private function enabled(string $method = 'email'): User
    {
        $user = User::factory()->create(['email' => bin2hex(random_bytes(8)).'@example.test',
            'password' => 'audit-password123', 'two_factor_secret' => (new Google2FA)->generateSecretKey(),
            'two_factor_confirmed_at' => now(), 'two_factor_method' => $method,
            'two_factor_recovery_codes' => [hash('sha256', 'synthetic-recovery')]]);
        $portfolio = Portfolio::create(['name' => 'Synthetic audit only']);
        $portfolio->members()->attach($user->id, ['role' => 'owner']);

        return $user;
    }

    private function login(User $user): TestResponse
    {
        Auth::guard('web')->logout();
        $this->flushSession();

        return $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'audit-password123']);
    }

    private function emailCode(): string
    {
        $sent = Notification::sent(new AnonymousNotifiable, TwoFactorCodeNotification::class)->last();
        $this->assertNotNull($sent);

        return $sent->code;
    }

    private function challenge(string $code, string $method = 'email', bool $remember = false): TestResponse
    {
        return $this->postJson('/api/v1/auth/two-factor', ['code' => $code, 'method' => $method, 'remember_device' => $remember]);
    }

    private function remember(User $user): string
    {
        $this->login($user)->assertOk()->assertJsonPath('two_factor_required', true);
        $response = $this->challenge($this->emailCode(), 'email', true)->assertOk();
        $this->assertAuthenticatedAs($user);
        $cookie = $response->getCookie(TrustedDevices::COOKIE);
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $cookie->getValue());
        $this->assertSame(now()->addDays(90)->timestamp, $cookie->getExpiresTime());

        return $cookie->getValue();
    }

    public function test_email_rejects_wrong_code_then_accepts_correct_code(): void
    {
        $user = $this->enabled();
        $this->login($user)->assertOk()->assertJsonPath('methods', ['email']);
        $correct = $this->emailCode();
        $wrong = $correct === '000000' ? '000001' : '000000';
        $this->challenge($wrong)->assertUnprocessable();
        $this->assertGuest('web');
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->challenge($correct)->assertOk()->assertJsonPath('user.id', $user->id);
        $this->assertAuthenticatedAs($user);
        Http::assertNothingSent();
    }

    public function test_email_code_expires_and_cannot_be_replayed(): void
    {
        $user = $this->enabled();
        $this->login($user)->assertOk();
        $expired = $this->emailCode();
        $this->travel(6)->minutes();
        $this->challenge($expired)->assertUnprocessable();
        $this->assertGuest('web');
        $this->login($user)->assertOk();
        $current = $this->emailCode();
        $this->challenge($current)->assertOk();
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->challenge($current)->assertUnprocessable();
        $this->assertGuest('web');
    }

    public function test_resend_invalidates_previous_code(): void
    {
        $user = $this->enabled();
        $this->login($user)->assertOk();
        $old = $this->emailCode();
        $this->postJson('/api/v1/auth/two-factor/resend')->assertOk();
        $new = $this->emailCode();
        // Avoid a probabilistic one-in-a-million test failure if fresh digits coincide.
        if ($old !== $new) {
            $this->challenge($old)->assertUnprocessable();
        }
        $this->challenge($new)->assertOk()->assertJsonPath('user.id', $user->id);
        Notification::assertSentOnDemandTimes(TwoFactorCodeNotification::class, 2);
    }

    public function test_both_requires_totp_then_email(): void
    {
        $user = $this->enabled('both');
        $this->login($user)->assertOk()->assertJsonPath('methods', ['authenticator', 'email']);
        $email = $this->emailCode();
        $totp = (new Google2FA)->oathTotp($user->two_factor_secret, intdiv(now()->timestamp, 30));
        $this->challenge($totp, 'authenticator')->assertOk()->assertJsonPath('challenge_complete', false)->assertJsonPath('methods_remaining', ['email']);
        $this->assertGuest('web');
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->challenge($email)->assertOk()->assertJsonPath('user.id', $user->id);
        $this->assertAuthenticatedAs($user);
    }

    public function test_both_requires_email_then_totp_and_cannot_reuse_email_step(): void
    {
        $user = $this->enabled('both');
        $this->login($user)->assertOk();
        $email = $this->emailCode();
        $this->challenge($email)->assertOk()->assertJsonPath('challenge_complete', false)->assertJsonPath('methods_remaining', ['authenticator']);
        $this->assertGuest('web');
        $this->challenge($email)->assertUnprocessable();
        $this->assertGuest('web');
        $totp = (new Google2FA)->oathTotp($user->two_factor_secret, intdiv(now()->timestamp, 30));
        $this->challenge($totp, 'authenticator')->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_trusted_device_only_bypasses_mfa_for_correct_account_and_expires_at_ninety_days(): void
    {
        $user = $this->enabled();
        $other = $this->enabled();
        $token = $this->remember($user);
        $this->assertDatabaseCount('trusted_devices', 1);
        $this->assertNotSame($token, TrustedDevice::firstOrFail()->token_hash);
        $this->withCookie(TrustedDevices::COOKIE, $token)->withCredentials();
        $this->login($other)->assertOk()->assertJsonPath('two_factor_required', true)->assertJsonMissingPath('user');
        $this->assertGuest('web');
        $this->travel(89)->days();
        $this->login($user)->assertOk()->assertJsonPath('trusted_device', true)->assertJsonPath('user.id', $user->id);
        $this->travel(1)->days();
        $this->login($user)->assertOk()->assertJsonPath('two_factor_required', true)->assertJsonMissingPath('user');
        $this->assertGuest('web');
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_revoking_trusted_devices_preserves_mfa_and_forces_new_challenge(): void
    {
        $user = $this->enabled();
        $token = $this->remember($user);
        $this->deleteJson('/api/v1/account/two-factor/devices')->assertNoContent();
        $this->assertDatabaseCount('trusted_devices', 0);
        $this->withCookie(TrustedDevices::COOKIE, $token)->withCredentials();
        $this->login($user)->assertOk()->assertJsonPath('two_factor_required', true);
        $this->assertGuest('web');
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_password_reset_revokes_trusted_device_but_preserves_mfa(): void
    {
        $user = $this->enabled();
        $token = $this->remember($user);
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $reset = Password::createToken($user);
        $this->postJson('/api/v1/auth/reset-password', ['email' => $user->email, 'token' => $reset,
            'password' => 'replacement-password123', 'password_confirmation' => 'replacement-password123'])->assertOk();
        $this->assertDatabaseCount('trusted_devices', 0);
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->withCookie(TrustedDevices::COOKIE, $token)->withCredentials()->postJson('/api/v1/auth/login',
            ['email' => $user->email, 'password' => 'replacement-password123'])->assertOk()->assertJsonPath('two_factor_required', true);
        $this->assertGuest('web');
    }

    public function test_trusted_device_cannot_be_used_without_the_account_password(): void
    {
        $user = $this->enabled();
        $token = $this->remember($user);
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->withCookie(TrustedDevices::COOKIE, $token)->withCredentials()->postJson('/api/v1/auth/login',
            ['email' => $user->email, 'password' => 'incorrect-synthetic'])->assertUnprocessable();
        $this->assertGuest('web');
    }

    public function test_remember_device_with_both_is_not_issued_before_both_factors_complete(): void
    {
        $user = $this->enabled('both');
        $this->login($user)->assertOk();
        $email = $this->emailCode();
        $totp = (new Google2FA)->oathTotp($user->two_factor_secret, intdiv(now()->timestamp, 30));
        $this->challenge($totp, 'authenticator', true)->assertOk()->assertJsonPath('challenge_complete', false)
            ->assertCookieMissing(TrustedDevices::COOKIE);
        $this->assertDatabaseCount('trusted_devices', 0);
        $this->assertGuest('web');
        $this->challenge($email, 'email', true)->assertOk()->assertCookie(TrustedDevices::COOKIE);
        $this->assertDatabaseCount('trusted_devices', 1);
        $this->assertAuthenticatedAs($user);
    }

    public function test_individual_revocation_cannot_delete_another_accounts_device(): void
    {
        $user = $this->enabled();
        $other = $this->enabled();
        $token = $this->remember($user);
        $device = TrustedDevice::firstOrFail();
        Auth::guard('web')->logout();
        $this->flushSession();
        $this->actingAs($other)->deleteJson('/api/v1/account/two-factor/devices/'.$device->id)->assertNoContent();
        $this->assertDatabaseHas('trusted_devices', ['id' => $device->id]);
        Auth::guard('web')->logout();
        $this->flushSession();
        $this->actingAs($user)->deleteJson('/api/v1/account/two-factor/devices/'.$device->id)->assertNoContent();
        $this->assertDatabaseMissing('trusted_devices', ['id' => $device->id]);
        $this->withCookie(TrustedDevices::COOKIE, $token)->withCredentials();
        $this->login($user)->assertOk()->assertJsonPath('two_factor_required', true);
        $this->assertGuest('web');
    }

    public function test_tampered_trusted_device_token_is_rejected(): void
    {
        $user = $this->enabled();
        $token = $this->remember($user);
        $token[0] = $token[0] === 'a' ? 'b' : 'a';
        $this->withCookie(TrustedDevices::COOKIE, $token)->withCredentials();
        $this->login($user)->assertOk()->assertJsonPath('two_factor_required', true)->assertJsonMissingPath('user');
        $this->assertGuest('web');
    }
}
