<?php

namespace App\Notifications;

use App\Models\SolicitudCambio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso a un área (Gestión Integral, SST, …) sobre un evento de una solicitud de cambio
 * (Revisión R2 / US11). Se entrega in-app y por correo, de forma encolada.
 */
class EventoSolicitudNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param 'creada'|'enviada'|'aprobada'|'devuelta'|'cerrada'|'anulada' $evento */
    public function __construct(
        public SolicitudCambio $solicitud,
        public string $evento,
        public ?string $area = null,
    ) {}

    public function via(object $notifiable): array
    {
        // Los destinatarios "on-demand" (correos externos) solo reciben correo.
        return $notifiable instanceof AnonymousNotifiable
            ? ['mail']
            : ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->solicitud;

        return (new MailMessage)
            ->subject("Gestión del cambio {$s->consecutivo}: {$this->titulo()}")
            ->line("La solicitud {$s->consecutivo} «{$s->nombre_cambio}» {$this->frase()}.")
            ->line('Clasificación: '.($s->clasificacionVigente()?->value ?? 'sin clasificar'))
            ->line('Planta: '.($s->planta?->nombre ?? '—'))
            ->action('Ver solicitud', route('solicitudes.show', $s));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'evento_solicitud',
            'evento' => $this->evento,
            'area' => $this->area,
            'solicitud_id' => $this->solicitud->id,
            'consecutivo' => $this->solicitud->consecutivo,
            'mensaje' => "La solicitud {$this->solicitud->consecutivo} {$this->frase()}.",
            'url' => route('solicitudes.show', $this->solicitud->id),
        ];
    }

    private function titulo(): string
    {
        return ucfirst($this->frase());
    }

    private function frase(): string
    {
        return match ($this->evento) {
            'creada' => 'fue registrada',
            'enviada' => 'fue enviada a aprobación',
            'aprobada' => 'fue aprobada',
            'devuelta' => 'fue devuelta',
            'verificacion' => 'pasó a seguimiento y cierre',
            'cerrada' => 'fue cerrada',
            'anulada' => 'fue anulada',
            default => "cambió ({$this->evento})",
        };
    }
}
