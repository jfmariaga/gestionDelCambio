<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proceso_usuario', function (Blueprint $table) {
            $table->foreignId('proceso_id')->constrained('procesos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->primary(['proceso_id', 'user_id'], 'proceso_usuario_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proceso_usuario');
    }
};
