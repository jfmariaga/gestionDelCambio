<?php

namespace App\Notifications;

use App\Models\AprobacionSolicitud;
use App\Models\SolicitudCambio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa a un aprobador recién resuelto (por área de notificación o por ser dueño del proceso)
 * que tiene una decisión pendiente sobre una solicitud. A diferencia de EventoSolicitudNotification
 * (que solo llega a los destinatarios de área), esta llega a CUALQUIERA con una fila real en
 * aprobaciones_solicitud, sin importar cómo se resolvió.
 */
class AprobacionPendienteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  AprobacionSolicitud::ETAPA_INICIAL|AprobacionSolicitud::ETAPA_CIERRE  $etapa */
    public function __construct(public SolicitudCambio $solicitud, public string $etapa) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Aprobación pendiente · {$this->solicitud->consecutivo}")
            ->greeting("Hola {$notifiable->name},")
            ->line("Tiene una decisión pendiente sobre la solicitud {$this->solicitud->consecutivo} «{$this->solicitud->nombre_cambio}» ({$this->etiquetaEtapa()}).")
            ->action('Revisar y decidir', route('solicitudes.show', $this->solicitud));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'aprobacion_pendiente',
            'solicitud_id' => $this->solicitud->id,
            'consecutivo' => $this->solicitud->consecutivo,
            'etapa' => $this->etapa,
            'mensaje' => "Tiene una decisión pendiente sobre la solicitud {$this->solicitud->consecutivo} ({$this->etiquetaEtapa()}).",
            'url' => route('solicitudes.show', $this->solicitud),
        ];
    }

    private function etiquetaEtapa(): string
    {
        return $this->etapa === AprobacionSolicitud::ETAPA_CIERRE ? 'seguimiento y cierre' : 'aprobación inicial';
    }
}
