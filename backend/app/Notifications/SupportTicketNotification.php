<?php

namespace App\Notifications;

use App\Domain\Support\Services\SupportTicketNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SupportTicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $reference, public readonly bool $newTicket) {}

    public function via(object $notifiable): array
    {
        return app(SupportTicketNotifier::class)->available() ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->newTicket ? 'Nuevo ticket de soporte · Alquivo' : 'Nueva respuesta en un ticket · Alquivo')
            ->greeting($this->newTicket ? 'Tienes un ticket nuevo' : 'Hay una nueva respuesta del cliente')
            ->line('Referencia: #'.$this->reference)
            ->line('Abre la bandeja de Alquivo para leer el mensaje y responder. El contenido y los adjuntos permanecen en la aplicación.')
            ->action('Abrir bandeja de tickets', rtrim((string) config('services.frontend_url'), '/').'/support/inbox')
            ->salutation('Alquivo');
    }
}
