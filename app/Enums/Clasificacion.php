<?php

namespace App\Enums;

/**
 * Clasificación del cambio según la hoja "Evaluación" del formato oficial:
 *   11-16 -> Menor | 17-23 -> Mayor | 24-33 -> Crítico | fuera de rango -> Revisar
 */
enum Clasificacion: string
{
    case Menor = 'Menor';
    case Mayor = 'Mayor';
    case Critico = 'Crítico';
    case Revisar = 'Revisar';

    public static function desdeSuma(int $suma): self
    {
        return match (true) {
            $suma >= 11 && $suma <= 16 => self::Menor,
            $suma >= 17 && $suma <= 23 => self::Mayor,
            $suma >= 24 && $suma <= 33 => self::Critico,
            default => self::Revisar,
        };
    }
}
