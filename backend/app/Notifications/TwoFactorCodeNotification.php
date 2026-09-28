<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorCodeNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu código de acceso a Alquivo')
            ->greeting('Verifica tu identidad')
            ->line('Usa este código para completar el inicio de sesión:')
            ->line($this->code)
            ->line('Caduca en 5 minutos. Si no has intentado entrar, cambia tu contraseña y contacta con soporte.')
            ->salutation('Equipo de Alquivo');
    }
}
