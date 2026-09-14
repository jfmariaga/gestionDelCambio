<?php

namespace App\Enums;

enum ValorRespuesta: string
{
    case Si = 'SI';
    case No = 'NO';
    case NoAplica = 'NA';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Si => 'Sí',
            self::No => 'No',
            self::NoAplica => 'N/A',
        };
    }
}
