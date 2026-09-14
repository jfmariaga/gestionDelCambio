<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Evidencia de cierre" se retira de la sección de Riesgos asociados: el cierre de un
 * riesgo Medio/Alto se gestiona ahora a través de su acción del plan (Fase 5 / Fase 7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riesgos_asociados', function (Blueprint $table) {
            $table->dropColumn('evidencia_cierre');
        });
    }

    public function down(): void
    {
        Schema::table('riesgos_asociados', function (Blueprint $table) {
            $table->text('evidencia_cierre')->nullable();
        });
    }
};
