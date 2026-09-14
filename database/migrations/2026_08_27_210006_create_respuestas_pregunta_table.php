<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('respuestas_pregunta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_cambio_id')->constrained('solicitudes_cambio')->cascadeOnDelete();
            $table->foreignId('pregunta_clave_id')->constrained('preguntas_clave')->cascadeOnDelete();
            $table->string('valor', 3); // SI | NO | NA
            $table->timestamp('respondida_at')->nullable();
            $table->timestamps();

            $table->unique(['solicitud_cambio_id', 'pregunta_clave_id'], 'respuestas_solicitud_pregunta_unique');
            $table->index(['solicitud_cambio_id', 'valor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respuestas_pregunta');
    }
};
