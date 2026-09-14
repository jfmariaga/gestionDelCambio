<?php

namespace App\Notifications;

use App\Models\AccionPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TareaPorValidarNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AccionPlan $accion) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $solicitud = $this->accion->solicitud;

        return (new MailMessage)
            ->subject("Tarea por validar · {$solicitud->consecutivo}")
            ->greeting("Hola {$notifiable->name},")
            ->line("El responsable marcó como cerrada la tarea «{$this->accion->descripcion}» de la solicitud {$solicitud->consecutivo}.")
            ->line('Revise la evidencia y valide o rechace la tarea.')
            ->action('Revisar', route('solicitudes.show', $solicitud));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'tarea_por_validar',
            'solicitud_id' => $this->accion->solicitud_cambio_id,
            'consecutivo' => $this->accion->solicitud->consecutivo,
            'accion_id' => $this->accion->id,
            'mensaje' => "La tarea «{$this->accion->descripcion}» está lista para su validación.",
            'url' => route('solicitudes.show', $this->accion->solicitud_cambio_id),
        ];
    }
}
