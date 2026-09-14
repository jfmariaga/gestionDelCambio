<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_aprobadores', function (Blueprint $table) {
            $table->foreignId('solicitud_cambio_id')->constrained('solicitudes_cambio')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['solicitud_cambio_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_aprobadores');
    }
};
