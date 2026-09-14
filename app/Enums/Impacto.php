<?php

namespace App\Enums;

/**
 * Escala de Impacto para la calificación de riesgos (sección 4 del formato).
 * El valor entero alimenta NR = Probabilidad * Impacto (ver App\Domain\GestionCambio\CalculoRiesgo).
 */
enum Impacto: int
{
    case Bajo = 1;
    case MedioBajo = 3;
    case Medio = 5;
    case Alto = 7;
    case Catastrofico = 10;

    public function etiqueta(): string
    {
        return match ($this) {
            self::Bajo => 'Bajo',
            self::MedioBajo => 'Medio bajo',
            self::Medio => 'Medio',
            self::Alto => 'Alto',
            self::Catastrofico => 'Catastrófico',
        };
    }

    public function criterio(): string
    {
        return match ($this) {
            self::Bajo => 'Sin lesión, sin afectación del producto/cliente, costo y retraso insignificantes; corrección inmediata.',
            self::MedioBajo => 'Afectación menor y reversible; desviación local, retraso o costo limitado, sin incumplimiento crítico.',
            self::Medio => 'Producto no conforme/reproceso, lesión menor, impacto ambiental controlable, queja o retraso relevante.',
            self::Alto => 'Incumplimiento importante, pérdida de lote/cliente, lesión seria, parada significativa o impacto ambiental relevante.',
            self::Catastrofico => 'Producto inseguro/recall, fatalidad, sanción o cierre, daño ambiental grave, pérdida crítica de datos o continuidad.',
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
