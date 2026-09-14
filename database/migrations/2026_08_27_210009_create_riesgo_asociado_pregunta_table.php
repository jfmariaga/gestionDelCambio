<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riesgo_asociado_pregunta', function (Blueprint $table) {
            $table->foreignId('riesgo_asociado_id')->constrained('riesgos_asociados')->cascadeOnDelete();
            $table->foreignId('pregunta_clave_id')->constrained('preguntas_clave')->cascadeOnDelete();

            $table->primary(['riesgo_asociado_id', 'pregunta_clave_id'], 'riesgo_asociado_pregunta_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riesgo_asociado_pregunta');
    }
};
