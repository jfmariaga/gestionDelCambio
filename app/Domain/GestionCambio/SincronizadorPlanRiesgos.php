<?php

namespace App\Domain\GestionCambio;

use App\Enums\EstadoAccionPlan;
use App\Enums\EstadoFila;
use App\Enums\NivelRiesgo;
use App\Models\AccionPlan;
use App\Models\RiesgoAsociado;
use App\Models\SolicitudCambio;
use Illuminate\Support\Facades\DB;

/**
 * Mantiene el plan de acción en sincronía con los riesgos asociados de nivel Medio/Alto:
 * cada riesgo vigente Medio/Alto arrastra exactamente una acción del plan (Fase 7).
 *
 * - Riesgo Medio/Alto sin acción vinculada  → se crea la acción.
 * - Riesgo que deja de ser Medio/Alto, se vuelve huérfano o se elimina:
 *     · si la acción no fue trabajada  → se elimina;
 *     · si fue trabajada               → se desvincula (riesgo_asociado_id = null) con nota.
 */
final class SincronizadorPlanRiesgos
{
    public function sincronizar(SolicitudCambio $solicitud): void
    {
        DB::transaction(function () use ($solicitud) {
            $this->crearFaltantes($solicitud);
            $this->limpiarSobrantes($solicitud);
        });
    }

    private function crearFaltantes(SolicitudCambio $solicitud): void
    {
        $riesgos = $solicitud->riesgosAsociados()
            ->where('estado', EstadoFila::Vigente->value)
            ->whereIn('nivel', [NivelRiesgo::Medio->value, NivelRiesgo::Alto->value])
            ->whereDoesntHave('accionPlan')
            ->get();

        foreach ($riesgos as $riesgo) {
            /** @var RiesgoAsociado $riesgo */
            AccionPlan::create([
                'solicitud_cambio_id' => $solicitud->id,
                'riesgo_asociado_id' => $riesgo->id,
                'numero' => ($solicitud->accionesPlan()->max('numero') ?? 0) + 1,
                'descripcion' => $riesgo->riesgo_texto ?: 'Acción para riesgo asociado',
                'proceso' => $riesgo->proceso_nombre,
                'estado' => EstadoAccionPlan::Pendiente->value,
                'creador_id' => auth()->id() ?? $solicitud->created_by,
            ]);
        }
    }

    private function limpiarSobrantes(SolicitudCambio $solicitud): void
    {
        $acciones = $solicitud->accionesPlan()
            ->whereNotNull('riesgo_asociado_id')
            ->with('riesgoAsociado')
            ->get();

        foreach ($acciones as $accion) {
            $riesgo = $accion->riesgoAsociado;
            $sigueVigente = $riesgo
                && $riesgo->estado === EstadoFila::Vigente
                && $riesgo->esMedioOAlto();

            if ($sigueVigente) {
                continue;
            }

            if ($accion->fueTrabajada()) {
                $accion->update([
                    'riesgo_asociado_id' => null,
                    'nota' => trim(($accion->nota ? $accion->nota."\n" : '').'[Riesgo de origen ya no es Medio/Alto]'),
                ]);

                continue;
            }

            $accion->delete();
        }
    }
}
