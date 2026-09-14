<?php

namespace App\Enums;

/**
 * Escala de Probabilidad para la calificación de riesgos (sección 4 del formato).
 * El valor entero alimenta NR = Probabilidad * Impacto (ver App\Domain\GestionCambio\CalculoRiesgo).
 */
enum Probabilidad: int
{
    case Rara = 1;
    case PocoProbable = 3;
    case Posible = 5;
    case Probable = 7;
    case CasiSegura = 10;

    public function etiqueta(): string
    {
        return match ($this) {
            self::Rara => 'Rara',
            self::PocoProbable => 'Poco probable',
            self::Posible => 'Posible',
            self::Probable => 'Probable',
            self::CasiSegura => 'Casi segura',
        };
    }

    public function criterio(): string
    {
        return match ($this) {
            self::Rara => 'No se ha presentado y existen controles robustos; solo ocurriría en circunstancias excepcionales.',
            self::PocoProbable => 'Podría ocurrir, pero no es habitual; hay antecedentes aislados o controles efectivos.',
            self::Posible => 'Puede ocurrir alguna vez durante la vida del cambio; existen antecedentes o incertidumbre relevante.',
            self::Probable => 'Es posible que ocurra durante implementación u operación; controles parciales o alta exposición.',
            self::CasiSegura => 'Lo más probable es que ocurra o ya ocurre con frecuencia; controles inexistentes o ineficaces.',
        };
    }

    /** @return list<int> valores admitidos, para reglas de validación `in:...`. */
    public static function valores(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** @return array<int,string> value => etiqueta, para poblar un <select>. */
    public static function opciones(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $acc, self $c) => $acc + [$c->value => $c->etiqueta()],
            [],
        );
    }
}
