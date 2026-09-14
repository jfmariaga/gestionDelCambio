<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destinatarios_area', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas_notificacion')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('email', 190)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['area_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destinatarios_area');
    }
};
