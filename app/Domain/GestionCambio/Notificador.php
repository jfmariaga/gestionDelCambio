<?php

namespace App\Domain\GestionCambio;

use App\Enums\Clasificacion;
use App\Models\AreaNotificacion;
use App\Models\BitacoraEvento;
use App\Models\SolicitudCambio;
use App\Notifications\EventoSolicitudNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Enruta los avisos de eventos de una solicitud a las áreas que correspondan según la
 * clasificación del cambio (Revisión R2 / US11 / FR-062…FR-067):
 *
 *  - Siempre: Gestión Integral + Jefes.
 *  - Mayor / Crítico: además SST, Gestión Ambiental, Calidad e Inocuidad.
 *  - Evento "enviada": además Comité de cambio.
 *
 * Área sin destinatarios activos -> se registra en la bitácora y el flujo continúa.
 */
final class Notificador
{
    /** @param 'creada'|'enviada'|'aprobada'|'devuelta'|'cerrada'|'anulada' $evento */
    public function notificarEvento(SolicitudCambio $solicitud, string $evento): void
    {
        foreach ($this->areasPara($solicitud, $evento) as $clave) {
            $this->notificarArea($solicitud, $evento, $clave);
        }
    }

    /** @return list<string> */
    private function areasPara(SolicitudCambio $solicitud, string $evento): array
    {
        $areas = ['gestion_integral', 'jefes'];

        if ($evento === 'verificacion'
            || in_array($solicitud->clasificacionVigente(), [Clasificacion::Mayor, Clasificacion::Critico], true)) {
            $areas = [...$areas, 'sst', 'gestion_ambiental', 'calidad_inocuidad'];
        }

        if ($evento === 'enviada') {
            $areas[] = 'comite_cambio';
        }

        if ($evento === 'verificacion' && $solicitud->clasificacionVigente() === Clasificacion::Critico) {
            $areas[] = 'gerencia_general';
        }

        return array_values(array_unique($areas));
    }

    private function notificarArea(SolicitudCambio $solicitud, string $evento, string $clave): void
    {
        $destinatarios = AreaNotificacion::destinatariosDe($clave);

        if ($destinatarios->isEmpty()) {
            BitacoraEvento::create([
                'solicitud_cambio_id' => $solicitud->id,
                'user_id' => auth()->id(),
                'evento' => 'notificacion_sin_destinatarios',
                'datos' => ['area' => $clave, 'evento' => $evento],
            ]);

            return;
        }

        $notificacion = new EventoSolicitudNotification($solicitud, $evento, $clave);

        foreach ($destinatarios as $destinatario) {
            if ($destinatario->user_id && $destinatario->usuario) {
                $destinatario->usuario->notify($notificacion);
            } elseif ($destinatario->email) {
                Notification::route('mail', $destinatario->email)->notify($notificacion);
            }
        }
    }
}
