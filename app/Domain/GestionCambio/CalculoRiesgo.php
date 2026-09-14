<?php

namespace App\Domain\GestionCambio;

use App\Enums\NivelRiesgo;
use InvalidArgumentException;

/**
 * Cálculo del Nivel de Riesgo tal como lo define el formato oficial (hoja "Formato", sección 4):
 *   NR = Probabilidad * Impacto     (columnas H * I, enteros 1..10)
 *   Nivel = IF(NR<=30,"Bajo",IF(NR<=60,"Medio","Alto"))
 *
 * Si falta la probabilidad o el impacto, NR y Nivel quedan sin definir (null).
 */
final class CalculoRiesgo
{
    public const MIN = 1;

    public const MAX = 10;

    /**
     * @return array{nr: int|null, nivel: NivelRiesgo|null}
     */
    public static function evaluar(?int $probabilidad, ?int $impacto): array
    {
        if ($probabilidad === null || $impacto === null) {
            return ['nr' => null, 'nivel' => null];
        }

        self::validarEscala($probabilidad, 'probabilidad');
        self::validarEscala($impacto, 'impacto');

        $nr = $probabilidad * $impacto;

        return ['nr' => $nr, 'nivel' => NivelRiesgo::desdeNr($nr)];
    }

    public static function enEscala(?int $valor): bool
    {
        return $valor !== null && $valor >= self::MIN && $valor <= self::MAX;
    }

    private static function validarEscala(int $valor, string $campo): void
    {
        if ($valor < self::MIN || $valor > self::MAX) {
            throw new InvalidArgumentException(
                "La {$campo} debe ser un entero entre ".self::MIN.' y '.self::MAX.'.'
            );
        }
    }
}
