<?php

namespace App\Domain\GestionCambio;

use App\Enums\EstadoAccionPlan;
use App\Enums\EstadoSolicitud;
use App\Enums\NivelRiesgo;
use App\Models\AprobacionSolicitud;
use App\Models\BitacoraEvento;
use App\Models\SolicitudCambio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Transiciones de estado de la solicitud y las dos compuertas de aprobación
 * (inicial = al comenzar el cambio; cierre = tras implementar el plan de acción).
 */
final class TransicionSolicitud
{
    /* ---------------------------------------------------------------------
     | Compuerta 1 — aprobación inicial (en_evaluacion → aprobado)
     * ------------------------------------------------------------------- */

    /** @param 'aprobar'|'devolver' $accion */
    public function registrarDecisionInicial(SolicitudCambio $solicitud, User $usuario, string $accion, ?string $comentario = null): void
    {
        $this->exigirEstado($solicitud, EstadoSolicitud::EnEvaluacion);

        $fila = $solicitud->aprobaciones()
            ->where('etapa', AprobacionSolicitud::ETAPA_INICIAL)
            ->where('user_id', $usuario->id)
            ->first();

        if (! $fila) {
            throw new RuntimeException('El usuario no está en el conjunto de aprobadores de esta etapa.');
        }

        DB::transaction(function () use ($solicitud, $usuario, $accion, $comentario, $fila) {
            if ($accion === 'devolver') {
                if (blank($comentario)) {
                    throw new RuntimeException('Debe indicar un comentario al devolver.');
                }

                $fila->update(['decision' => AprobacionSolicitud::DEVUELTO, 'comentario' => $comentario, 'decidido_at' => now()]);
                $solicitud->forceFill([
                    'estado' => EstadoSolicitud::Solicitado,
                    'snapshot_at' => null,
                    'decision_comentario' => $comentario,
                ])->save();
                $this->registrar($solicitud, $usuario->id, 'devuelta', $comentario);

                return;
            }

            $fila->update(['decision' => AprobacionSolicitud::APROBADO, 'comentario' => $comentario, 'decidido_at' => now()]);
            $this->registrar($solicitud, $usuario->id, 'aprobacion_inicial_registrada', $comentario);

            if ($solicitud->fresh()->etapaAprobada(AprobacionSolicitud::ETAPA_INICIAL)) {
                $solicitud->forceFill(['estado' => EstadoSolicitud::Aprobado])->save();
                $this->registrar($solicitud, $usuario->id, 'aprobada', $comentario);
            }
        });

        $solicitud->refresh();

        if ($solicitud->estado === EstadoSolicitud::Solicitado) {
            app(Notificador::class)->notificarEvento($solicitud, 'devuelta');
        } elseif ($solicitud->estado === EstadoSolicitud::Aprobado) {
            app(Notificador::class)->notificarEvento($solicitud, 'aprobada');
        }
    }

    /* ---------------------------------------------------------------------
     | Implementación del plan de acción
     * ------------------------------------------------------------------- */

    public function iniciarImplementacion(SolicitudCambio $solicitud, ?int $usuarioId): void
    {
        $this->exigirEstado($solicitud, EstadoSolicitud::Aprobado);

        $solicitud->forceFill(['estado' => EstadoSolicitud::EnImplementacion])->save();
        $this->registrar($solicitud, $usuarioId, 'implementacion_iniciada');
    }

    /** Cuando todas las acciones del plan están validadas, la solicitud pasa a "Implementado". */
    public function marcarImplementadoSiCorresponde(SolicitudCambio $solicitud, ?int $usuarioId = null): void
    {
        if ($solicitud->estado !== EstadoSolicitud::EnImplementacion) {
            return;
        }

        if ($this->accionesSinValidar($solicitud) > 0) {
            return;
        }

        $solicitud->forceFill(['estado' => EstadoSolicitud::Implementado])->save();
        $this->registrar($solicitud, $usuarioId, 'plan_implementado');
    }

    /**
     * Botón manual "Marcar como implementado": el paso automático de arriba solo dispara al
     * validar la última tarea, así que una solicitud sin tareas (o con alguna que quedó sin
     * validar por otro camino) se quedaba atascada en "En implementación" sin forma de avanzar.
     *
     * @return array<int,string> bloqueos encontrados; vacío si se pudo marcar.
     */
    public function marcarImplementado(SolicitudCambio $solicitud, ?int $usuarioId): array
    {
        $this->exigirEstado($solicitud, EstadoSolicitud::EnImplementacion);

        $sinValidar = $this->accionesSinValidar($solicitud);

        if ($sinValidar > 0) {
            return ["Hay {$sinValidar} acción(es) del plan sin validar."];
        }

        $solicitud->forceFill(['estado' => EstadoSolicitud::Implementado])->save();
        $this->registrar($solicitud, $usuarioId, 'plan_implementado');

        return [];
    }

    private function accionesSinValidar(SolicitudCambio $solicitud): int
    {
        return $solicitud->accionesPlan()
            ->where('estado', '!=', EstadoAccionPlan::Validada->value)
            ->count();
    }

    /* ---------------------------------------------------------------------
     | Compuerta 2 — seguimiento y cierre (en_verificacion → cerrado)
     * ------------------------------------------------------------------- */

    /** @return array<int,string> bloqueos encontrados; vacío si se pudo enviar a verificación. */
    public function enviarAVerificacion(SolicitudCambio $solicitud, ?int $usuarioId): array
    {
        $this->exigirEstado($solicitud, EstadoSolicitud::Implementado);

        if (blank($solicitud->nota_cierre)) {
            return ['Debe registrar la nota de cierre antes de enviar a verificación.'];
        }

        DB::transaction(function () use ($solicitud, $usuarioId) {
            $solicitud->forceFill(['estado' => EstadoSolicitud::EnVerificacion])->save();
            $this->registrar($solicitud, $usuarioId, 'enviada_verificacion');
            app(AsignadorAprobador::class)->sembrarAprobaciones($solicitud->fresh(), AprobacionSolicitud::ETAPA_CIERRE);
        });

        app(Notificador::class)->notificarEvento($solicitud, 'verificacion');

        return [];
    }

    /**
     * @param  'aprobar'|'devolver'  $accion
     * @return array<int,string> bloqueos de cierre encontrados (solo al completar la etapa)
     */
    public function registrarDecisionCierre(SolicitudCambio $solicitud, User $usuario, string $accion, ?string $comentario = null): array
    {
        $this->exigirEstado($solicitud, EstadoSolicitud::EnVerificacion);

        $fila = $solicitud->aprobaciones()
            ->where('etapa', AprobacionSolicitud::ETAPA_CIERRE)
            ->where('user_id', $usuario->id)
            ->first();

        if (! $fila) {
            throw new RuntimeException('El usuario no está en el conjunto de aprobadores de cierre.');
        }

        if ($accion === 'devolver') {
            if (blank($comentario)) {
                throw new RuntimeException('Debe indicar un comentario al devolver.');
            }

            DB::transaction(function () use ($solicitud, $usuario, $comentario, $fila) {
                $fila->update(['decision' => AprobacionSolicitud::DEVUELTO, 'comentario' => $comentario, 'decidido_at' => now()]);
                $solicitud->forceFill([
                    'estado' => EstadoSolicitud::EnImplementacion,
                    'decision_comentario' => $comentario,
                ])->save();
                $this->registrar($solicitud, $usuario->id, 'cierre_devuelto', $comentario);
            });

            app(Notificador::class)->notificarEvento($solicitud->refresh(), 'devuelta');

            return [];
        }

        $fila->update(['decision' => AprobacionSolicitud::APROBADO, 'comentario' => $comentario, 'decidido_at' => now()]);
        $this->registrar($solicitud, $usuario->id, 'aprobacion_cierre_registrada', $comentario);

        if (! $solicitud->fresh()->etapaAprobada(AprobacionSolicitud::ETAPA_CIERRE)) {
            return [];
        }

        $bloqueos = $this->bloqueosDeCierre($solicitud);

        if ($bloqueos !== []) {
            return $bloqueos;
        }

        DB::transaction(function () use ($solicitud, $usuario) {
            $solicitud->forceFill([
                'estado' => EstadoSolicitud::Cerrado,
                'aprobado_por' => $usuario->id,
                'aprobado_at' => now(),
            ])->save();
            $this->registrar($solicitud, $usuario->id, 'cerrada');
        });

        app(Notificador::class)->notificarEvento($solicitud->refresh(), 'cerrada');

        return [];
    }

    /**
     * Cierre manual (admin / reintento tras resolver bloqueos): exige la etapa de cierre aprobada.
     *
     * @return array<int,string>
     */
    public function cerrar(SolicitudCambio $solicitud, ?int $usuarioId): array
    {
        if ($solicitud->estado !== EstadoSolicitud::EnVerificacion
            || ! $solicitud->etapaAprobada(AprobacionSolicitud::ETAPA_CIERRE)) {
            throw new RuntimeException('El cierre requiere la etapa de verificación aprobada por todos.');
        }

        $bloqueos = $this->bloqueosDeCierre($solicitud);

        if ($bloqueos !== []) {
            return $bloqueos;
        }

        DB::transaction(function () use ($solicitud, $usuarioId) {
            $solicitud->forceFill([
                'estado' => EstadoSolicitud::Cerrado,
                'aprobado_por' => $usuarioId,
                'aprobado_at' => now(),
            ])->save();
            $this->registrar($solicitud, $usuarioId, 'cerrada');
        });

        app(Notificador::class)->notificarEvento($solicitud, 'cerrada');

        return [];
    }

    public function cancelar(SolicitudCambio $solicitud, ?int $usuarioId, string $comentario): void
    {
        if ($solicitud->estado === EstadoSolicitud::Cerrado) {
            throw new RuntimeException('Una solicitud cerrada no se puede cancelar.');
        }

        DB::transaction(function () use ($solicitud, $usuarioId, $comentario) {
            $solicitud->forceFill(['estado' => EstadoSolicitud::Cancelado])->save();
            $this->registrar($solicitud, $usuarioId, 'anulada', $comentario);
        });

        app(Notificador::class)->notificarEvento($solicitud, 'anulada');
    }

    /**
     * Bloqueos de cierre:
     *  - acciones del plan sin validar (FR-057);
     *  - riesgos Medio/Alto sin acción en el plan (Fase 7).
     *
     * @return array<int,string>
     */
    public function bloqueosDeCierre(SolicitudCambio $solicitud): array
    {
        $bloqueos = [];

        $accionesSinValidar = $solicitud->accionesPlan()
            ->where('estado', '!=', EstadoAccionPlan::Validada->value)
            ->count();

        if ($accionesSinValidar > 0) {
            $bloqueos[] = "Hay {$accionesSinValidar} acción(es) del plan sin validar.";
        }

        $riesgosSinAccion = $solicitud->riesgosAsociados()
            ->whereIn('nivel', [NivelRiesgo::Medio->value, NivelRiesgo::Alto->value])
            ->whereDoesntHave('accionPlan')
            ->count();

        if ($riesgosSinAccion > 0) {
            $bloqueos[] = "Hay {$riesgosSinAccion} riesgo(s) Medio/Alto sin acción en el plan.";
        }

        return $bloqueos;
    }

    private function exigirEstado(SolicitudCambio $solicitud, EstadoSolicitud $esperado): void
    {
        if ($solicitud->estado !== $esperado) {
            throw new RuntimeException("La solicitud no está en estado {$esperado->value}.");
        }
    }

    private function registrar(SolicitudCambio $solicitud, ?int $usuarioId, string $evento, ?string $comentario = null): void
    {
        BitacoraEvento::create([
            'solicitud_cambio_id' => $solicitud->id,
            'user_id' => $usuarioId,
            'evento' => $evento,
            'comentario' => $comentario,
        ]);
    }
}
