<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Se elimina la sección "Consideraciones": las preguntas clave ya solo generan riesgos,
 * y esos riesgos (Medio/Alto) se llevan al plan de acción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('consideraciones');
    }

    public function down(): void
    {
        Schema::create('consideraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_cambio_id')->constrained('solicitudes_cambio')->cascadeOnDelete();
            $table->foreignId('pregunta_clave_id')->constrained('preguntas_clave')->cascadeOnDelete();
            $table->string('proceso_nombre', 150);
            $table->string('pregunta_texto', 500);
            $table->string('dueno_revisa', 200)->nullable();
            $table->text('evidencia_minima')->nullable();
            $table->text('accion')->nullable();
            $table->boolean('editado_manualmente')->default(false);
            $table->string('estado', 10)->default('vigente');
            $table->timestamps();
            $table->unique(['solicitud_cambio_id', 'pregunta_clave_id'], 'consideraciones_solicitud_pregunta_unique');
            $table->index(['solicitud_cambio_id', 'estado']);
        });
    }
};
