<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Adjuntos de evidencia (Revisión R2 / US10)
    |--------------------------------------------------------------------------
    | Tamaño máximo por archivo adjunto en el plan de acción y en aprobación /
    | cierre, en kilobytes. Sin restricción de tipo (foto, video, PDF, Excel…).
    */
    'adjunto_max_kb' => (int) env('GC_ADJUNTO_MAX_KB', 20480),

    'adjunto_disco' => env('GC_ADJUNTO_DISCO', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Panel de métricas del administrador
    |--------------------------------------------------------------------------
    | Días hacia adelante que definen una acción del plan como "próxima a vencer".
    */
    'dias_proximo_vencimiento' => (int) env('GC_DIAS_PROXIMO_VENCIMIENTO', 7),

    /*
    |--------------------------------------------------------------------------
    | Plantas / sedes por defecto (Revisión R2 / US12)
    |--------------------------------------------------------------------------
    | Semilla inicial. Se pueden agregar/renombrar/desactivar desde
    | Administración sin afectar solicitudes ya asociadas.
    */
    'plantas_por_defecto' => [
        ['nombre' => 'Panal', 'codigo' => 'PANAL', 'orden' => 1],
        ['nombre' => 'Leva Pan', 'codigo' => 'LEVAPAN', 'orden' => 2],
        ['nombre' => 'Leva Col', 'codigo' => 'LEVACOL', 'orden' => 3],
    ],

    /*
    |--------------------------------------------------------------------------
    | Áreas de notificación (Revisión R2 / US11)
    |--------------------------------------------------------------------------
    */
    'areas_notificacion' => [
        'gestion_integral' => 'Gestión Integral',
        'sst' => 'SST',
        'gestion_ambiental' => 'Gestión Ambiental',
        'calidad_inocuidad' => 'Calidad e Inocuidad',
        'comite_cambio' => 'Comité de cambio',
        'gerencia_general' => 'Gerencia General',
        'jefes' => 'Jefes',
    ],
];
