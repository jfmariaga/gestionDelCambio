<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluaciones_cambio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_cambio_id')->unique()->constrained('solicitudes_cambio')->cascadeOnDelete();
            $table->unsignedSmallInteger('suma')->nullable();
            $table->string('clasificacion', 10)->nullable(); // Menor | Mayor | Crítico | Revisar
            $table->timestamps();
        });

        Schema::create('evaluacion_calificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluacion_cambio_id')->constrained('evaluaciones_cambio')->cascadeOnDelete();
            $table->foreignId('criterio_rubrica_id')->constrained('criterios_rubrica')->cascadeOnDelete();
            $table->unsignedTinyInteger('valor'); // 1..3
            $table->timestamps();

            $table->unique(['evaluacion_cambio_id', 'criterio_rubrica_id'], 'evaluacion_calif_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluacion_calificaciones');
        Schema::dropIfExists('evaluaciones_cambio');
    }
};
