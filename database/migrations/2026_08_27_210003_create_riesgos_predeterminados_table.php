<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riesgos_predeterminados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proceso_id')->constrained('procesos')->cascadeOnDelete();
            $table->string('texto', 500);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Único por (proceso, texto) para permitir consolidación de riesgos (FR-021).
            $table->unique(['proceso_id', 'texto'], 'riesgos_pred_proceso_texto_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riesgos_predeterminados');
    }
};
