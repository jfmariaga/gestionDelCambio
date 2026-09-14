<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_cambio', function (Blueprint $table) {
            $table->id();
            $table->string('consecutivo', 20)->unique();
            $table->date('fecha');

            // Sección 1 - Resumen del cambio
            $table->string('nombre_cambio', 255);
            $table->string('solicitante_cargo', 255)->nullable();
            $table->string('area_proceso', 150)->nullable();
            $table->string('tipo_cambio', 100)->nullable();
            $table->string('clasificacion_manual', 20)->nullable();
            $table->date('fecha_requerida')->nullable();
            $table->decimal('costo_estimado', 15, 2)->nullable();
            $table->boolean('requiere_comite')->default(false);

            // Sección 2 - Descripción simple
            $table->text('situacion_actual')->nullable();
            $table->text('que_cambiara')->nullable();
            $table->text('resultado_esperado')->nullable();

            // Estado / flujo
            $table->string('estado', 20)->default('borrador');
            $table->timestamp('snapshot_at')->nullable();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprobado_at')->nullable();
            $table->text('decision_comentario')->nullable();

            // Auditoría
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_cambio');
    }
};
