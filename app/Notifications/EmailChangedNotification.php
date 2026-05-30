<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica al correo anterior que su cuenta cambió la dirección de email.
 * Permite al titular legítimo detectar takeovers en curso y solicitar soporte
 * antes de que el atacante cierre todas las vías de recuperación.
 */
class EmailChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $emailAnterior,
        private readonly string $emailNuevo,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Cambio de correo en tu cuenta D\'Salud')
            ->greeting('Hola,')
            ->line("Te informamos que el correo asociado a tu cuenta cambió de {$this->emailAnterior} a {$this->emailNuevo}.")
            ->line('Si tú no realizaste este cambio, contacta de inmediato al administrador del sistema. Tu cuenta puede estar comprometida.')
            ->line('Si fuiste tú, puedes ignorar este mensaje.');
    }
}
