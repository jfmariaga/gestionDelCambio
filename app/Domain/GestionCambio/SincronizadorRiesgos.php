<?php

namespace App\Domain\GestionCambio;

use App\Enums\EstadoFila;
use App\Enums\ValorRespuesta;
use App\Models\PreguntaClave;
use App\Models\RiesgoAsociado;
use App\Models\SolicitudCambio;
use Illuminate\Support\Collection;

/**
 * Mantiene la sección 4 "Riesgos asociados" en sincronía con las respuestas "Sí".
 *
 * - Cada pregunta "Sí" con riesgo predeterminado agrega/consolida una fila por
 *   (solicitud, riesgo_predeterminado); las preguntas de origen se guardan en la pivote
 *   `riesgo_asociado_pregunta` (FR-016, FR-021).
 * - Una pregunta "Sí" sin riesgo predeterminado no crea fila; se reporta como advertencia
 *   (FR-022) vía preguntasSiSinRiesgo().
 * - Al quitar la última pregunta de origen, la fila se elimina si no fue editada/calificada;
 *   en caso contrario queda huérfana (FR-030).
 */
final class SincronizadorRiesgos
{
    public function sincronizarPregunta(SolicitudCambio $solicitud, PreguntaClave $pregunta, ValorRespuesta $valor): void
    {
        if (! $solicitud->permiteSincronizacion()) {
            return;
        }

        if ($pregunta->riesgo_predeterminado_id === null) {
            return; // FR-022: sin riesgo asociado en el catálogo.
        }

        $fila = RiesgoAsociado::query()
            ->where('solicitud_cambio_id', $solicitud->id)
            ->where('riesgo_predeterminado_id', $pregunta->riesgo_predeterminado_id)
            ->first();

        if ($valor === ValorRespuesta::Si) {
            $this->activar($solicitud, $pregunta, $fila);

            return;
        }

        $this->desvincular($fila, $pregunta);
    }

    /** Reprocesa todas las respuestas de la solicitud. Idempotente. */
    public function sincronizarTodo(SolicitudCambio $solicitud): void
    {
        $solicitud->loadMissing('respuestas.pregunta');

        foreach ($solicitud->respuestas as $respuesta) {
            $this->sincronizarPregunta($solicitud, $respuesta->pregunta, $respuesta->valor);
        }
    }

    /**
     * Preguntas respondidas "Sí" que no tienen riesgo predeterminado en el catálogo (FR-022).
     *
     * @return Collection<int,PreguntaClave>
     */
    public function preguntasSiSinRiesgo(SolicitudCambio $solicitud)
    {
        $solicitud->loadMissing('respuestas.pregunta');

        return $solicitud->respuestas
            ->filter(fn ($r) => $r->valor === ValorRespuesta::Si && $r->pregunta->riesgo_predeterminado_id === null)
            ->map(fn ($r) => $r->pregunta)
            ->values();
    }

    private function activar(SolicitudCambio $solicitud, PreguntaClave $pregunta, ?RiesgoAsociado $fila): void
    {
        if (! $fila) {
            $pregunta->loadMissing('riesgoPredeterminado.proceso');
            $riesgo = $pregunta->riesgoPredeterminado;

            $fila = RiesgoAsociado::create([
                'solicitud_cambio_id' => $solicitud->id,
                'riesgo_predeterminado_id' => $riesgo->id,
                'proceso_nombre' => $riesgo->proceso?->nombre ?? '',
                'riesgo_texto' => $riesgo->texto,
                'editado_manualmente' => false,
                'estado' => EstadoFila::Vigente,
            ]);
        } elseif ($fila->estado === EstadoFila::Huerfana) {
            $fila->update(['estado' => EstadoFila::Vigente]);
        }

        $fila->preguntas()->syncWithoutDetaching([$pregunta->id]);
    }

    private function desvincular(?RiesgoAsociado $fila, PreguntaClave $pregunta): void
    {
        if (! $fila) {
            return;
        }

        $fila->preguntas()->detach($pregunta->id);

        if ($fila->preguntas()->count() > 0) {
            return; // otra pregunta "Sí" sigue sosteniendo el riesgo (consolidación).
        }

        if ($this->fueTrabajada($fila)) {
            $fila->update(['estado' => EstadoFila::Huerfana]);

            return;
        }

        $fila->delete();
    }

    private function fueTrabajada(RiesgoAsociado $fila): bool
    {
        return $fila->editado_manualmente
            || $fila->probabilidad !== null
            || $fila->impacto !== null;
    }
}
