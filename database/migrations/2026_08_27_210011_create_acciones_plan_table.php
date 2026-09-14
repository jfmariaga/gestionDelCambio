<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acciones_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_cambio_id')->constrained('solicitudes_cambio')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero')->default(0);
            $table->text('descripcion');
            $table->string('proceso', 150)->nullable();
            $table->string('responsable', 200)->nullable();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha')->nullable();
            $table->string('estado', 12)->default('Pendiente'); // Pendiente | En curso | Cerrada
            $table->text('evidencia')->nullable();
            $table->text('nota')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acciones_plan');
    }
};
