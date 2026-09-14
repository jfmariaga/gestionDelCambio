<?php

namespace App\Domain\GestionCambio;

use App\Enums\Clasificacion;

/**
 * Clasificación del cambio a partir de la rúbrica de 11 criterios (hoja "Evaluación"):
 *   Suma = SUM(E3:E13)  con cada criterio en 1..3
 *   11-16 -> Menor | 17-23 -> Mayor | 24-33 -> Crítico | fuera de rango -> Revisar
 *
 * La clasificación solo se entrega cuando están calificados los 11 criterios.
 */
final class ClasificadorCambio
{
    public const TOTAL_CRITERIOS = 11;

    /**
     * @param  array<int,int>  $calificaciones  valores 1..3 indexados por criterio
     * @return array{completa: bool, suma: int|null, clasificacion: Clasificacion|null, faltan: int}
     */
    public static function evaluar(array $calificaciones, int $totalCriterios = self::TOTAL_CRITERIOS): array
    {
        $valores = array_values(array_filter(
            $calificaciones,
            static fn ($v) => $v !== null && $v >= 1 && $v <= 3
        ));

        $completa = count($valores) === $totalCriterios;

        if (! $completa) {
            return [
                'completa' => false,
                'suma' => null,
                'clasificacion' => null,
                'faltan' => max($totalCriterios - count($valores), 0),
            ];
        }

        $suma = array_sum($valores);

        return [
            'completa' => true,
            'suma' => $suma,
            'clasificacion' => Clasificacion::desdeSuma($suma),
            'faltan' => 0,
        ];
    }
}
