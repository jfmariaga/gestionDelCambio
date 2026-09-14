<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criterios_cierre', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_cambio_id')->constrained('solicitudes_cambio')->cascadeOnDelete();
            $table->string('descripcion', 255);
            $table->string('valor', 3)->nullable(); // SI | NO | NA
            $table->text('detalle')->nullable();
            $table->string('responsable', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('criterios_cierre');
    }
};
