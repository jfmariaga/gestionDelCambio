<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_cambio_id')->nullable()
                ->constrained('solicitudes_cambio')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('evento', 40);
            $table->text('comentario')->nullable();
            $table->json('datos')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['solicitud_cambio_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora_eventos');
    }
};
