<?php

namespace App\Enums;

/**
 * Nivel cualitativo del riesgo según el formato oficial (hoja "Formato", sección 4):
 *   NR <= 30  -> Bajo
 *   NR <= 60  -> Medio
 *   NR  > 60  -> Alto
 */
enum NivelRiesgo: string
{
    case Bajo = 'Bajo';
    case Medio = 'Medio';
    case Alto = 'Alto';

    public static function desdeNr(int $nr): self
    {
        return match (true) {
            $nr <= 30 => self::Bajo,
            $nr <= 60 => self::Medio,
            default => self::Alto,
        };
    }

    /** Clases de color para la insignia en la UI (Tailwind). */
    public function colorBadge(): string
    {
        return match ($this) {
            self::Bajo => 'bg-green-100 text-green-800',
            self::Medio => 'bg-yellow-100 text-yellow-800',
            self::Alto => 'bg-red-100 text-red-800',
        };
    }
}
