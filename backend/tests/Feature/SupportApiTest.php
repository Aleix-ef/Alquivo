<?php

namespace Tests\Feature;

use App\Domain\Documents\Services\UploadScanner;
use App\Mail\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SupportApiTest extends TestCase
{
    use RefreshDatabase;

    private array $temporaryFiles = [];

    private function upload(string $name = 'captura.png', ?string $contents = null): UploadedFile
    {
        $contents ??= base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jq1sAAAAASUVORK5CYII=');
        $backing = UploadedFile::fake()->createWithContent($name, $contents);
        $this->temporaryFiles[] = $backing;

        // A real UploadedFile uses finfo, unlike Laravel's fake MIME-by-name helper.
        return new UploadedFile($backing->getRealPath(), $name, null, null, true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'smtp', 'support.email' => 'soporte@alquivo.com']);
        Mail::fake();
    }

    private function payload(array $changes = []): array
    {
        return [...['name' => 'Ana', 'email' => 'ana@example.test', 'subject' => 'Problema con una factura', 'message' => 'No puedo subir la factura de este mes.', 'privacy_acknowledged' => true], ...$changes];
    }

    public function test_public_form_sends_only_to_support_with_safe_reply_address(): void
    {
        $this->postJson('/api/v1/public/support', $this->payload(['to' => 'attacker@example.test']))->assertCreated()->assertJsonStructure(['message', 'reference']);
        Mail::assertSent(SupportMessage::class, function ($mail) {
            return $mail->hasTo('soporte@alquivo.com') && ! $mail->hasTo('attacker@example.test') && $mail->envelope()->replyTo[0]->address === 'ana@example.test';
        });
        Mail::assertSentCount(1);
    }

    public function test_authenticated_sender_cannot_override_the_account_email_and_unverified_can_ask_for_help(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($user)->postJson('/api/v1/support', $this->payload(['email' => 'spoof@example.test']))->assertCreated();
        Mail::assertSent(SupportMessage::class, fn ($mail) => $mail->details['email'] === $user->email && str_contains($mail->details['source'], '#'.$user->id) && str_contains($mail->details['source'], 'correo sin verificar'));
    }

    public function test_private_support_endpoint_requires_login(): void
    {
        $this->postJson('/api/v1/support', $this->payload())->assertUnauthorized();
        Mail::assertNothingSent();
    }

    public function test_valid_attachments_are_scanned_and_named_safely_without_public_storage(): void
    {
        $file = $this->upload();
        $this->mock(UploadScanner::class)->shouldReceive('scan')->once();
        $this->postJson('/api/v1/public/support', $this->payload(['attachments' => [$file]]))->assertCreated();
        Mail::assertSent(SupportMessage::class, fn ($mail) => count($mail->attachments()) === 1 && $mail->attachments()[0]->as === 'adjunto-1.png');
    }

    public function test_disallowed_file_content_is_rejected_even_with_an_image_extension(): void
    {
        $this->postJson('/api/v1/public/support', $this->payload(['attachments' => [$this->upload('captura.png', '<script>alert(1)</script>')]]))->assertUnprocessable()->assertJsonValidationErrors('attachments.0');
        Mail::assertNothingSent();
    }

    public function test_attachment_size_and_count_are_limited(): void
    {
        $this->postJson('/api/v1/public/support', $this->payload(['attachments' => [$this->upload('big.pdf', "%PDF-1.4\n".str_repeat('x', 2049 * 1024))]]))->assertUnprocessable()->assertJsonValidationErrors('attachments.0');
        $this->postJson('/api/v1/public/support', $this->payload(['attachments' => array_map(fn ($i) => $this->upload("{$i}.png"), range(1, 4))]))->assertUnprocessable()->assertJsonValidationErrors('attachments');
        Mail::assertNothingSent();
    }

    public function test_malware_prevents_delivery(): void
    {
        $scanner = $this->mock(UploadScanner::class);
        $scanner->shouldReceive('scan')->once()->andThrow(ValidationException::withMessages(['file' => ['Unsafe']]));
        $this->postJson('/api/v1/public/support', $this->payload(['attachments' => [$this->upload()]]))->assertUnprocessable()->assertJsonValidationErrors('attachments.0');
        Mail::assertNothingSent();
    }

    public function test_scanner_outage_prevents_delivery(): void
    {
        $this->mock(UploadScanner::class)->shouldReceive('scan')->once()->andReturnUsing(fn () => abort(503, 'Scanner unavailable'));
        $this->postJson('/api/v1/public/support', $this->payload(['attachments' => [$this->upload()]]))->assertStatus(503);
        Mail::assertNothingSent();
    }

    public function test_honeypot_and_header_injection_are_rejected(): void
    {
        $this->postJson('/api/v1/public/support', $this->payload(['company_website' => 'https://spam.test']))->assertUnprocessable();
        $this->postJson('/api/v1/public/support', $this->payload(['subject' => "Hola\r\nBcc: injected@example.test"]))->assertUnprocessable();
        $this->postJson('/api/v1/public/support', $this->payload(['privacy_acknowledged' => false]))->assertUnprocessable();
        Mail::assertNothingSent();
    }

    public function test_rate_limit_is_shared_between_public_and_private_endpoints(): void
    {
        $this->actingAs(User::factory()->create());
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/public/support', $this->payload())->assertCreated();
        }
        $this->postJson('/api/v1/support', $this->payload())->assertTooManyRequests();
        Mail::assertSentCount(3);
    }

    public function test_development_mailer_never_claims_delivery_or_logs_the_message(): void
    {
        config(['mail.default' => 'log']);
        $this->postJson('/api/v1/public/support', $this->payload())->assertStatus(503);
        Mail::assertNothingSent();
    }

    public function test_failed_transport_returns_a_safe_retryable_error(): void
    {
        Mail::shouldReceive('to')->once()->with('soporte@alquivo.com')->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('Private transport credentials must not appear'));
        $this->postJson('/api/v1/public/support', $this->payload())->assertStatus(503)->assertDontSee('Private transport credentials');
    }

    public function test_mail_html_escapes_visitor_supplied_content(): void
    {
        $mail = new SupportMessage([...$this->payload(['message' => '<script>alert(1)</script>']), 'reference' => 'test', 'source' => 'Público']);
        $rendered = $mail->render();
        $this->assertStringNotContainsString('<script>', $rendered);
        $this->assertStringContainsString('&lt;script&gt;', $rendered);
    }
}
