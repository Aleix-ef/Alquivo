<?php

namespace App\Domain\Support\Services;

use App\Notifications\SupportTicketNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

final class SupportTicketNotifier
{
    public function available(): bool
    {
        $transport = config('mail.mailers.'.config('mail.default').'.transport');

        return filter_var(config('support.email'), FILTER_VALIDATE_EMAIL)
            && in_array($transport, ['smtp', 'ses', 'ses-v2', 'postmark', 'resend', 'mailgun', 'sendmail'], true);
    }

    public function notify(string $ticketId, bool $newTicket): void
    {
        if (! $this->available()) {
            return;
        }

        try {
            Notification::route('mail', config('support.email'))->notify(
                new SupportTicketNotification(strtoupper(substr(str_replace('-', '', $ticketId), 0, 8)), $newTicket)
            );
        } catch (\Throwable $exception) {
            // The ticket is already saved. A mail or queue outage must not make
            // the client retry the message and create duplicate work.
            Log::warning('Support ticket notification could not be queued', [
                'ticket_id' => $ticketId, 'type' => get_class($exception),
            ]);
        }
    }
}
