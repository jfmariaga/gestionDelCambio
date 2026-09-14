<?php

namespace App\Domain\GestionCambio;

use App\Enums\EstadoSolicitud;
use App\Models\BitacoraEvento;
use App\Models\SolicitudCambio;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Envía la solicitud a aprobación y congela su contenido: a partir de `snapshot_at` los textos
 * de consideraciones y riesgos ya no se re-sincronizan y la edición del catálogo no la afecta
 * (FR-035 / SC-009).
 */
final class CongeladorSolicitud
{
    public function enviarAAprobacion(SolicitudCambio $solicitud, ?int $usuarioId = null): void
    {
        if ($solicitud->estado !== EstadoSolicitud::Solicitado) {
            throw new RuntimeException('Solo se puede enviar a evaluación una solicitud en estado Solicitado.');
        }

        DB::transaction(function () use ($solicitud, $usuarioId) {
            $solicitud->forceFill([
                'estado' => EstadoSolicitud::EnEvaluacion,
                'snapshot_at' => now(),
            ])->save();

            BitacoraEvento::create([
                'solicitud_cambio_id' => $solicitud->id,
                'user_id' => $usuarioId,
                'evento' => 'enviada',
                'datos' => ['estado_anterior' => EstadoSolicitud::Solicitado->value],
            ]);

            // Compuerta 1: fija el conjunto de aprobadores por clasificación (Fase 3).
            app(AsignadorAprobador::class)->sembrarAprobaciones($solicitud->fresh(), 'inicial');
        });

        app(Notificador::class)->notificarEvento($solicitud, 'enviada');
    }
}
