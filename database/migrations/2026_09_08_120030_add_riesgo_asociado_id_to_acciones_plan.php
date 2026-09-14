<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada riesgo asociado de nivel Medio/Alto arrastra una acción del plan (Fase 7).
 * `riesgo_asociado_id` vincula esa acción con su riesgo de origen; null = acción manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acciones_plan', function (Blueprint $table) {
            $table->foreignId('riesgo_asociado_id')->nullable()->after('solicitud_cambio_id')
                ->constrained('riesgos_asociados')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('acciones_plan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('riesgo_asociado_id');
        });
    }
};
