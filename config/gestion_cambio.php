<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Consecutivo de la solicitud de cambio
    |--------------------------------------------------------------------------
    |
    | Formato: <prefijo>-<AAAA>-<NNN>. La secuencia se reinicia cada año.
    | `digitos` controla el relleno con ceros a la izquierda (001, 002, ...).
    */
    'consecutivo' => [
        'prefijo' => env('GC_CONSECUTIVO_PREFIJO', 'GC'),
        'digitos' => (int) env('GC_CONSECUTIVO_DIGITOS', 3),
        'reinicia_por_anio' => true,
    ],
];
