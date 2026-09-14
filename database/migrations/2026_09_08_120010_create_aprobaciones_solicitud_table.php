<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de decisiones de las dos compuertas de aprobación:
 *  - etapa "inicial": al comenzar el cambio (por clasificación).
 *  - etapa "cierre":  tras implementar el plan de acción (Seguimiento y cierre).
 *
 * Una fila por (solicitud, usuario, etapa). La compuerta avanza cuando no queda
 * ninguna fila en 'pendiente' ni 'devuelto' para esa etapa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aprobaciones_solicitud', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_cambio_id')->constrained('solicitudes_cambio')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('etapa', 10);                      // inicial | cierre
            $table->string('decision', 10)->default('pendiente'); // pendiente | aprobado | devuelto
            $table->text('comentario')->nullable();
            $table->timestamp('decidido_at')->nullable();
            $table->timestamps();

            $table->unique(['solicitud_cambio_id', 'user_id', 'etapa'], 'aprob_solicitud_user_etapa_unique');
            $table->index(['solicitud_cambio_id', 'etapa', 'decision'], 'aprob_solicitud_etapa_decision_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aprobaciones_solicitud');
    }
};
