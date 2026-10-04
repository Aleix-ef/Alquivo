<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SupportTicketNotification;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

// Synthetic notifications only. Never sends mail or reads a customer's account.
class ProductionMailLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        config(['app.url' => 'https://app.alquivo.com', 'services.frontend_url' => 'https://app.alquivo.com/']);
        URL::forceRootUrl('https://app.alquivo.com');
        URL::forceScheme('https');
        Http::preventStrayRequests();
        Notification::fake();
    }

    public function test_reset_link_uses_the_public_app_and_preserves_encoded_token_and_email(): void
    {
        $user = User::factory()->create(['email' => 'synthetic+reset@example.test']);
        $mail = (new ResetPassword('synthetic-token-only'))->toMail($user);
        $this->assertSame('Recupera tu acceso a Alquivo', $mail->subject);
        $this->assertSame('https://app.alquivo.com/reset-password', strtok($mail->actionUrl, '?'));
        parse_str(parse_url($mail->actionUrl, PHP_URL_QUERY), $query);
        $this->assertSame(['token' => 'synthetic-token-only', 'email' => $user->email], $query);
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }

    private function verificationRequest(User $user): Request
    {
        $mail = (new VerifyEmail)->toMail($user);
        $this->assertSame('https://app.alquivo.com/verify-email', strtok($mail->actionUrl, '?'));
        parse_str(parse_url($mail->actionUrl, PHP_URL_QUERY), $query);
        $this->assertSame((string) $user->id, $query['id']);
        $this->assertSame(sha1($user->email), $query['hash']);
        $this->assertSame(now()->addMinutes(60)->timestamp, (int) $query['expires']);

        return Request::create('https://app.alquivo.com/api/v1/auth/email/verify/'.$query['id'].'/'.$query['hash'].'?'.http_build_query([
            'expires' => $query['expires'], 'signature' => $query['signature'],
        ]));
    }

    public function test_verification_frontend_link_maps_to_a_valid_signed_api_url(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $this->assertTrue(URL::hasValidSignature($this->verificationRequest($user)));
        $this->assertNull($user->fresh()->email_verified_at);
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_verification_signature_rejects_another_host_and_expiry(): void
    {
        $request = $this->verificationRequest(User::factory()->create());
        $otherHost = Request::create(str_replace('app.alquivo.com', 'untrusted.example.test', $request->fullUrl()));
        $this->assertFalse(URL::hasValidSignature($otherHost));
        $this->travel(61)->minutes();
        $this->assertFalse(URL::hasValidSignature($request));
    }

    public function test_support_link_uses_the_app_not_the_waitlist_or_localhost(): void
    {
        $mail = (new SupportTicketNotification('SYNTHETIC-ONLY', true))->toMail(User::factory()->make());
        $this->assertSame('https://app.alquivo.com/support/inbox', $mail->actionUrl);
        $this->assertSame('Nuevo ticket de soporte · Alquivo', $mail->subject);
        $this->assertStringNotContainsString('localhost', json_encode($mail->toArray()));
        Notification::assertNothingSent();
    }

    public function test_email_second_factor_has_a_short_lifetime_and_no_external_link(): void
    {
        $mail = (new TwoFactorCodeNotification('123456'))->toMail(User::factory()->make());
        $text = json_encode($mail->toArray(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('123456', $text);
        $this->assertStringContainsString('5 minutos', $text);
        $this->assertNull($mail->actionUrl);
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }
}
