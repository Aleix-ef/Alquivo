<?php

namespace App\Support;

final class OutboundMail
{
    private const TRANSPORTS = ['smtp', 'ses', 'ses-v2', 'postmark', 'resend', 'mailgun', 'sendmail'];

    public function available(): bool
    {
        $transport = config('mail.mailers.'.config('mail.default').'.transport');
        if (! in_array($transport, self::TRANSPORTS, true)
            || ! filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if ($transport === 'resend') {
            $key = config('services.resend.key');

            return is_string($key) && str_starts_with($key, 're_') && strlen($key) > 3;
        }

        return true;
    }
}
