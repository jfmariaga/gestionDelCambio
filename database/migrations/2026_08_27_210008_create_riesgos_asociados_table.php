<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riesgos_asociados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_cambio_id')->constrained('solicitudes_cambio')->cascadeOnDelete();
            $table->foreignId('riesgo_predeterminado_id')->nullable()
                ->constrained('riesgos_predeterminados')->nullOnDelete();

            // Textos congelados.
            $table->string('proceso_nombre', 150);
            $table->string('riesgo_texto', 500);

            // Campos editables (sección 4 del formato).
            $table->text('control_existente')->nullable();
            $table->text('accion_requerida')->nullable();
            $table->string('responsable', 200)->nullable();
            $table->date('fecha')->nullable();
            $table->unsignedTinyInteger('probabilidad')->nullable(); // 1..10
            $table->unsignedTinyInteger('impacto')->nullable();      // 1..10
            $table->unsignedSmallInteger('nr')->nullable();          // calculado = probabilidad * impacto
            $table->string('nivel', 5)->nullable();                  // Bajo | Medio | Alto
            $table->text('evidencia_cierre')->nullable();

            $table->boolean('editado_manualmente')->default(false);
            $table->string('estado', 10)->default('vigente'); // vigente | huerfano
            $table->timestamps();

            $table->unique(['solicitud_cambio_id', 'riesgo_predeterminado_id'], 'riesgos_asoc_solicitud_riesgo_unique');
            $table->index(['solicitud_cambio_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riesgos_asociados');
    }
};
