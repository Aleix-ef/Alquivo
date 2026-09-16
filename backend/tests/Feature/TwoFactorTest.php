<?php

namespace Tests\Feature;

use App\Domain\Identity\Services\TwoFactor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sanctum.stateful' => [parse_url(config('app.url'), PHP_URL_HOST).(parse_url(config('app.url'), PHP_URL_PORT) ? ':'.parse_url(config('app.url'), PHP_URL_PORT) : '')]]);
    }

    private function enabled(): User
    {
        return User::factory()->create(['password' => 'password123', 'two_factor_secret' => (new Google2FA)->generateSecretKey(), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => [hash('sha256', 'recovery-for-test')]]);
    }

    private function pending(User $user): array
    {
        return ['two_factor_login' => ['user_id' => $user->id, 'binding' => app(TwoFactor::class)->binding($user), 'expires' => now()->addMinutes(5)->timestamp]];
    }

    public function test_password_alone_does_not_authenticate_a_two_factor_account(): void
    {
        $user = $this->enabled();
        $this->withHeader('Origin', config('app.url'))->withSession([])->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertOk()->assertJsonPath('two_factor_required', true)->assertJsonMissingPath('user');
        $this->assertGuest('web');
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_recovery_code_is_single_use_and_secrets_are_not_exposed(): void
    {
        $user = $this->enabled();
        $this->withHeader('Origin', config('app.url'))->withSession($this->pending($user))
            ->postJson('/api/v1/auth/two-factor', ['code' => 'recovery-for-test'])->assertOk()
            ->assertJsonMissingPath('user.two_factor_secret')->assertJsonMissingPath('user.two_factor_recovery_codes');
        $this->assertAuthenticatedAs($user);
        $this->assertSame([], $user->fresh()->two_factor_recovery_codes);
        Auth::guard('web')->logout();
        $this->withSession($this->pending($user))->postJson('/api/v1/auth/two-factor', ['code' => 'recovery-for-test'])->assertUnprocessable();
        $this->assertGuest('web');
        $this->assertNotSame($user->two_factor_secret, DB::table('users')->where('id', $user->id)->value('two_factor_secret'));
    }

    public function test_totp_is_single_use_even_with_a_new_login_challenge(): void
    {
        $user = $this->enabled();
        $code = (new Google2FA)->oathTotp($user->two_factor_secret, intdiv(now()->timestamp, 30));
        $this->withHeader('Origin', config('app.url'))->withSession($this->pending($user))->postJson('/api/v1/auth/two-factor', ['code' => $code])->assertOk();
        Auth::guard('web')->logout();
        $this->withSession($this->pending($user->fresh()))->postJson('/api/v1/auth/two-factor', ['code' => $code])->assertUnprocessable();
    }

    public function test_expired_or_password_changed_challenge_is_rejected(): void
    {
        $user = $this->enabled();
        $pending = $this->pending($user);
        $user->update(['password' => 'changed123']);
        $this->withHeader('Origin', config('app.url'))->withSession($pending)->postJson('/api/v1/auth/two-factor', ['code' => 'recovery-for-test'])->assertUnprocessable();
        $pending = $this->pending($user->fresh());
        $pending['two_factor_login']['expires'] = now()->subMinute()->timestamp;
        $this->withSession($pending)->postJson('/api/v1/auth/two-factor', ['code' => 'recovery-for-test'])->assertUnprocessable();
        $this->assertGuest('web');
    }

    public function test_setup_requires_password_and_confirmation_before_enabling(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $this->withHeader('Origin', config('app.url'))->actingAs($user)->postJson('/api/v1/account/two-factor/setup', ['current_password' => 'wrong'])->assertUnprocessable();
        $secret = $this->postJson('/api/v1/account/two-factor/setup', ['current_password' => 'password123'])->assertOk()->json('secret');
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        $this->postJson('/api/v1/account/two-factor/confirm', ['code' => 'invalid'])->assertUnprocessable();
        $codes = $this->postJson('/api/v1/account/two-factor/confirm', ['code' => (new Google2FA)->oathTotp($secret, intdiv(now()->timestamp, 30))])->assertOk()->json('recovery_codes');
        $this->assertCount(8, $codes);
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->assertNotSame($codes, $user->fresh()->two_factor_recovery_codes);
        $this->travel(1)->minutes();
        $this->actingAs($user->fresh())->deleteJson('/api/v1/account/two-factor', ['current_password' => 'password123', 'code' => 'wrong'])->assertUnprocessable();
        $this->deleteJson('/api/v1/account/two-factor', ['current_password' => 'password123', 'code' => $codes[0]])->assertNoContent();
        $this->assertNull($user->fresh()->two_factor_secret);
    }
}
