<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas_notificacion', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 30)->unique(); // gestion_integral, sst, gestion_ambiental, ...
            $table->string('nombre', 120);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas_notificacion');
    }
};
