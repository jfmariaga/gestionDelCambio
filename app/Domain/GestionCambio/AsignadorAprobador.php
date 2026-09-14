<?php

namespace App\Domain\GestionCambio;

use App\Enums\Clasificacion;
use App\Enums\EstadoFila;
use App\Enums\ValorRespuesta;
use App\Models\AprobacionSolicitud;
use App\Models\AreaNotificacion;
use App\Models\BitacoraEvento;
use App\Models\Proceso;
use App\Models\RespuestaPregunta;
use App\Models\SolicitudCambio;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resuelve y siembra el conjunto de aprobadores de una solicitud para cada una de las dos
 * compuertas de aprobación:
 *
 *  Etapa "inicial" (al comenzar el cambio, por clasificación):
 *   - Menor   → SIG + dueño(s) del proceso.
 *   - Mayor   → SIG + Ambiental + Calidad + SST + dueño(s) del proceso.
 *   - Crítico → lo anterior + Gerencia General ("Don Carlos").
 *
 *  Etapa "cierre" (tras implementar el plan de acción):
 *   - Dueños del proceso + líderes con pregunta clave en "Sí" o con riesgo asociado vigente.
 *   - SIG + SST + Ambiental + Calidad.
 *   - Gerencia General solo si la clasificación es Crítico.
 *
 * No hace nada si un administrador fijó el aprobador manualmente (`aprobador_override`).
 */
final class AsignadorAprobador
{
    private const SIG = 'gestion_integral';

    private const AMBIENTAL = 'gestion_ambiental';

    private const CALIDAD = 'calidad_inocuidad';

    private const SST = 'sst';

    private const GERENCIA = 'gerencia_general';

    /**
     * Conjunto de usuarios que deben aprobar la etapa indicada.
     *
     * @return Collection<int,User>
     */
    public function aprobadoresPara(SolicitudCambio $solicitud, string $etapa): Collection
    {
        return $etapa === AprobacionSolicitud::ETAPA_CIERRE
            ? $this->paraCierre($solicitud)
            : $this->paraInicial($solicitud);
    }

    /**
     * "Adelanto" al guardar: recalcula el set de la etapa inicial y lo refleja en el pivote de
     * vista rápida (`solicitud_aprobadores` + `aprobador_asignado_id`). No siembra decisiones.
     */
    public function sincronizar(SolicitudCambio $solicitud, string $etapa = AprobacionSolicitud::ETAPA_INICIAL): void
    {
        if ($solicitud->aprobador_override) {
            return;
        }

        $ids = $this->aprobadoresPara($solicitud, $etapa)->pluck('id')->unique()->values();

        if ($ids->isEmpty()) {
            return; // no se pisa el set anterior; se registra al sembrar si sigue vacío.
        }

        $anterior = $solicitud->aprobador_asignado_id;

        $solicitud->forceFill(['aprobador_asignado_id' => $ids->first()])->save();
        $solicitud->aprobadores()->sync($ids->all());

        if ($anterior !== $ids->first()) {
            BitacoraEvento::create([
                'solicitud_cambio_id' => $solicitud->id,
                'user_id' => auth()->id(),
                'evento' => 'aprobador_reasignado',
                'datos' => ['de' => $anterior, 'a' => $ids->first(), 'etapa' => $etapa],
            ]);
        }
    }

    /**
     * Fija las decisiones pendientes de una etapa a partir del set resuelto. Idempotente:
     * no toca decisiones ya registradas.
     */
    public function sembrarAprobaciones(SolicitudCambio $solicitud, string $etapa): void
    {
        $ids = ($etapa === AprobacionSolicitud::ETAPA_INICIAL && $solicitud->aprobador_override)
            ? $solicitud->aprobadores()->pluck('users.id')
            : $this->aprobadoresPara($solicitud, $etapa)->pluck('id')->unique()->values();

        if ($ids->isEmpty()) {
            BitacoraEvento::create([
                'solicitud_cambio_id' => $solicitud->id,
                'user_id' => auth()->id(),
                'evento' => 'aprobadores_sin_resolver',
                'datos' => ['etapa' => $etapa],
            ]);

            return;
        }

        foreach ($ids as $uid) {
            AprobacionSolicitud::firstOrCreate(
                ['solicitud_cambio_id' => $solicitud->id, 'user_id' => $uid, 'etapa' => $etapa],
                ['decision' => AprobacionSolicitud::PENDIENTE],
            );
        }

        if ($etapa === AprobacionSolicitud::ETAPA_INICIAL) {
            $solicitud->aprobadores()->syncWithoutDetaching($ids->all());

            if ($solicitud->aprobador_asignado_id === null) {
                $solicitud->forceFill(['aprobador_asignado_id' => $ids->first()])->save();
            }
        }
    }

    /** @return Collection<int,User> */
    private function paraInicial(SolicitudCambio $solicitud): Collection
    {
        $clasificacion = $solicitud->clasificacionVigente();

        if ($clasificacion === null) {
            return collect();
        }

        $duenos = $this->duenosDelProceso($solicitud);
        $sig = $this->usuariosDeArea(self::SIG);

        $usuarios = match ($clasificacion) {
            Clasificacion::Menor => $sig->merge($duenos),
            Clasificacion::Mayor => $sig
                ->merge($this->usuariosDeArea(self::AMBIENTAL))
                ->merge($this->usuariosDeArea(self::CALIDAD))
                ->merge($this->usuariosDeArea(self::SST))
                ->merge($duenos),
            default => $sig // Crítico / Revisar
                ->merge($this->usuariosDeArea(self::AMBIENTAL))
                ->merge($this->usuariosDeArea(self::CALIDAD))
                ->merge($this->usuariosDeArea(self::SST))
                ->merge($this->usuariosDeArea(self::GERENCIA))
                ->merge($duenos),
        };

        return $this->unicos($usuarios);
    }

    /** @return Collection<int,User> */
    private function paraCierre(SolicitudCambio $solicitud): Collection
    {
        $usuarios = $this->usuariosDeArea(self::SIG)
            ->merge($this->usuariosDeArea(self::SST))
            ->merge($this->usuariosDeArea(self::AMBIENTAL))
            ->merge($this->usuariosDeArea(self::CALIDAD))
            ->merge($this->duenosDelProceso($solicitud))
            ->merge($this->lideresConPreguntasORiesgos($solicitud));

        if ($solicitud->clasificacionVigente() === Clasificacion::Critico) {
            $usuarios = $usuarios->merge($this->usuariosDeArea(self::GERENCIA));
        }

        return $this->unicos($usuarios);
    }

    /** @return Collection<int,User> */
    private function duenosDelProceso(SolicitudCambio $solicitud): Collection
    {
        $proceso = Proceso::where('nombre', $solicitud->area_proceso)->first();

        return $proceso ? $proceso->duenos()->get() : collect();
    }

    /** @return Collection<int,User> */
    private function usuariosDeArea(string $clave): Collection
    {
        return AreaNotificacion::destinatariosDe($clave)
            ->filter(fn ($d) => $d->user_id !== null)
            ->map(fn ($d) => $d->usuario)
            ->filter()
            ->values();
    }

    /**
     * Usuarios "dueño de proceso" cuyos procesos aparecen en respuestas "Sí" del cuestionario o
     * en riesgos asociados vigentes de la solicitud.
     *
     * @return Collection<int,User>
     */
    private function lideresConPreguntasORiesgos(SolicitudCambio $solicitud): Collection
    {
        $procesosSi = RespuestaPregunta::query()
            ->where('respuestas_pregunta.solicitud_cambio_id', $solicitud->id)
            ->where('respuestas_pregunta.valor', ValorRespuesta::Si->value)
            ->join('preguntas_clave', 'preguntas_clave.id', '=', 'respuestas_pregunta.pregunta_clave_id')
            ->join('procesos', 'procesos.id', '=', 'preguntas_clave.proceso_id')
            ->pluck('procesos.nombre');

        $procesosRiesgo = $solicitud->riesgosAsociados()
            ->where('estado', EstadoFila::Vigente->value)
            ->pluck('proceso_nombre');

        $nombres = $procesosSi->merge($procesosRiesgo)->filter()->unique()->values();

        if ($nombres->isEmpty()) {
            return collect();
        }

        return User::role('dueno_proceso')
            ->whereHas('procesos', fn ($q) => $q->whereIn('nombre', $nombres))
            ->get();
    }

    /**
     * @param  Collection<int,User>  $usuarios
     * @return Collection<int,User>
     */
    private function unicos(Collection $usuarios): Collection
    {
        return $usuarios->filter()->unique('id')->values();
    }
}
