<?php

namespace App\Notifications;

use App\Models\AccionPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TareaAsignadaNotification extends Notification implements ShouldQueue
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
            ->subject("Tarea asignada · {$solicitud->consecutivo}")
            ->greeting("Hola {$notifiable->name},")
            ->line("Se le asignó una tarea del plan de acción de la solicitud {$solicitud->consecutivo}.")
            ->line("Tarea: {$this->accion->descripcion}")
            ->line('Proceso: '.($this->accion->proceso ?: '—'))
            ->line('Fecha: '.(optional($this->accion->fecha)->format('d/m/Y') ?: 'sin fecha'))
            ->action('Ver tarea', route('solicitudes.tarea', [$solicitud, $this->accion]));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'tarea_asignada',
            'solicitud_id' => $this->accion->solicitud_cambio_id,
            'consecutivo' => $this->accion->solicitud->consecutivo,
            'accion_id' => $this->accion->id,
            'descripcion' => $this->accion->descripcion,
            'mensaje' => "Se le asignó la tarea «{$this->accion->descripcion}».",
            'url' => route('solicitudes.tarea', [$this->accion->solicitud_cambio_id, $this->accion->id]),
        ];
    }
}
