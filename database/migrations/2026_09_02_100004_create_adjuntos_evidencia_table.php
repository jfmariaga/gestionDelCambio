<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adjuntos_evidencia', function (Blueprint $table) {
            $table->id();
            $table->morphs('adjuntable'); // AccionPlan | CriterioCierre
            $table->string('disco', 20)->default('local');
            $table->string('ruta', 255);
            $table->string('nombre_original', 255);
            $table->string('mime', 150)->nullable();
            $table->unsignedBigInteger('tamano')->default(0); // bytes
            $table->foreignId('subido_por')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adjuntos_evidencia');
    }
};
