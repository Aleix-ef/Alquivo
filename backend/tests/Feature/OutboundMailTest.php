<?php

namespace Tests\Feature;

use App\Support\OutboundMail;
use Tests\TestCase;

class OutboundMailTest extends TestCase
{
    public function test_resend_needs_an_api_key_and_a_valid_sender(): void
    {
        config([
            'mail.default' => 'resend',
            'mail.from.address' => 'soporte@alquivo.com',
            'services.resend.key' => null,
        ]);

        $this->assertFalse(app(OutboundMail::class)->available());

        config(['services.resend.key' => 're_test_key']);
        $this->assertTrue(app(OutboundMail::class)->available());

        config(['mail.from.address' => 'not-an-email']);
        $this->assertFalse(app(OutboundMail::class)->available());
    }

    public function test_log_mailer_is_never_treated_as_deliverable(): void
    {
        config(['mail.default' => 'log', 'mail.from.address' => 'soporte@alquivo.com']);

        $this->assertFalse(app(OutboundMail::class)->available());
    }
}
