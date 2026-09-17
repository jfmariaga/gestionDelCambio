<?php

namespace App\Notifications;

use App\Models\AccionPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TareaRechazadaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AccionPlan $accion, public string $motivo) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $solicitud = $this->accion->solicitud;

        return (new MailMessage)
            ->subject("Tarea devuelta · {$solicitud->consecutivo}")
            ->greeting("Hola {$notifiable->name},")
            ->line("El líder devolvió la tarea «{$this->accion->descripcion}» de la solicitud {$solicitud->consecutivo}.")
            ->line("Motivo: {$this->motivo}")
            ->action('Ver tarea', route('solicitudes.tarea', [$solicitud, $this->accion]));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'tarea_rechazada',
            'solicitud_id' => $this->accion->solicitud_cambio_id,
            'consecutivo' => $this->accion->solicitud->consecutivo,
            'accion_id' => $this->accion->id,
            'motivo' => $this->motivo,
            'mensaje' => "La tarea «{$this->accion->descripcion}» fue devuelta: {$this->motivo}",
            'url' => route('solicitudes.tarea', [$this->accion->solicitud_cambio_id, $this->accion->id]),
        ];
    }
}
