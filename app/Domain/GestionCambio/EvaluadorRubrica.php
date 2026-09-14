<?php

namespace App\Domain\GestionCambio;

use App\Enums\Clasificacion;
use App\Models\CriterioRubrica;
use App\Models\EvaluacionCambio;
use App\Models\SolicitudCambio;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Gestiona la rúbrica de la hoja "Evaluación": persiste la calificación de cada criterio
 * (1..3) y recalcula suma + clasificación (Menor/Mayor/Crítico/Revisar) mediante
 * ClasificadorCambio. La clasificación solo se fija cuando están los 11 criterios.
 */
final class EvaluadorRubrica
{
    /**
     * @return array{completa: bool, suma: int|null, clasificacion: Clasificacion|null, faltan: int}
     */
    public function calificar(SolicitudCambio $solicitud, int $criterioId, int $valor): array
    {
        if ($valor < 1 || $valor > 3) {
            throw new InvalidArgumentException('La calificación debe estar entre 1 y 3.');
        }

        return DB::transaction(function () use ($solicitud, $criterioId, $valor) {
            $evaluacion = EvaluacionCambio::firstOrCreate(['solicitud_cambio_id' => $solicitud->id]);

            $evaluacion->calificaciones()->updateOrCreate(
                ['criterio_rubrica_id' => $criterioId],
                ['valor' => $valor],
            );

            return $this->recalcular($evaluacion->fresh('calificaciones'));
        });
    }

    /**
     * @return array{completa: bool, suma: int|null, clasificacion: Clasificacion|null, faltan: int}
     */
    public function recalcular(EvaluacionCambio $evaluacion): array
    {
        $total = CriterioRubrica::count();
        $calificaciones = $evaluacion->calificaciones->pluck('valor', 'criterio_rubrica_id')->all();

        $resultado = ClasificadorCambio::evaluar($calificaciones, $total);

        $clasificacionAntes = $evaluacion->clasificacion;

        $evaluacion->update([
            'suma' => $resultado['suma'],
            'clasificacion' => $resultado['clasificacion'],
        ]);

        // Al cambiar la clasificación, recalcular el adelanto del conjunto de aprobadores
        // de la compuerta inicial (Fase 3).
        if ($clasificacionAntes !== $resultado['clasificacion'] && $evaluacion->solicitud) {
            app(AsignadorAprobador::class)->sincronizar($evaluacion->solicitud->fresh());
        }

        return $resultado;
    }
}
