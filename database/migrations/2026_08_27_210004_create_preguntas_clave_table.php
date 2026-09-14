<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preguntas_clave', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proceso_id')->constrained('procesos')->cascadeOnDelete();
            $table->foreignId('riesgo_predeterminado_id')->nullable()
                ->constrained('riesgos_predeterminados')->nullOnDelete();
            $table->string('texto', 500);
            $table->string('dueno_por_defecto', 200)->nullable();
            $table->text('evidencia_por_defecto')->nullable();
            $table->text('accion_por_defecto')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['proceso_id', 'orden', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preguntas_clave');
    }
};
