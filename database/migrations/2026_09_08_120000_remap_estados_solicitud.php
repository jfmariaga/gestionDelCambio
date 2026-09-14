<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renombra los valores del catálogo "Estado" al nuevo ciclo de 8 estados
 * (borrador→solicitado, en_aprobacion→en_evaluacion, anulado→cancelado).
 * Los demás valores (aprobado, en_implementacion, cerrado) se conservan;
 * implementado y en_verificacion son nuevos y solo se alcanzan por transición.
 */
return new class extends Migration
{
    private array $mapa = [
        'borrador' => 'solicitado',
        'en_aprobacion' => 'en_evaluacion',
        'anulado' => 'cancelado',
    ];

    public function up(): void
    {
        foreach ($this->mapa as $antes => $despues) {
            DB::table('solicitudes_cambio')->where('estado', $antes)->update(['estado' => $despues]);
        }
    }

    public function down(): void
    {
        foreach ($this->mapa as $antes => $despues) {
            DB::table('solicitudes_cambio')->where('estado', $despues)->update(['estado' => $antes]);
        }
    }
};
