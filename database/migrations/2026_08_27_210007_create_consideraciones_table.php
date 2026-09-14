<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consideraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_cambio_id')->constrained('solicitudes_cambio')->cascadeOnDelete();
            $table->foreignId('pregunta_clave_id')->constrained('preguntas_clave')->cascadeOnDelete();

            // Textos congelados (copiados del catálogo al crearse).
            $table->string('proceso_nombre', 150);
            $table->string('pregunta_texto', 500);

            // Campos editables por el usuario (sección 3 del formato).
            $table->string('dueno_revisa', 200)->nullable();
            $table->text('evidencia_minima')->nullable();
            $table->text('accion')->nullable();

            $table->boolean('editado_manualmente')->default(false);
            $table->string('estado', 10)->default('vigente'); // vigente | huerfana
            $table->timestamps();

            $table->unique(['solicitud_cambio_id', 'pregunta_clave_id'], 'consideraciones_solicitud_pregunta_unique');
            $table->index(['solicitud_cambio_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consideraciones');
    }
};
