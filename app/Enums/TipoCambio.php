<?php

namespace App\Enums;

/**
 * Tipo de cambio (lista desplegable del formato FOSIG-02):
 *   Temporal · Permanente · Emergente · Piloto / prueba · Reversión
 */
enum TipoCambio: string
{
    case Temporal = 'Temporal';
    case Permanente = 'Permanente';
    case Emergente = 'Emergente';
    case PilotoPrueba = 'Piloto / prueba';
    case Reversion = 'Reversión';

    public function etiqueta(): string
    {
        return $this->value;
    }

    /** @return array<string,string> value => etiqueta, para poblar un <select>. */
    public static function opciones(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $acc, self $c) => $acc + [$c->value => $c->etiqueta()],
            [],
        );
    }
}
