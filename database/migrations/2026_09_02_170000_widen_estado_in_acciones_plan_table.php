<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El valor más largo del enum EstadoAccionPlan ("Cerrada — pendiente de validación") tiene
 * 33 caracteres; la columna se había definido como VARCHAR(30). Se amplía a 40.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acciones_plan', function (Blueprint $table) {
            $table->string('estado', 40)->default('Pendiente')->change();
        });
    }

    public function down(): void
    {
        Schema::table('acciones_plan', function (Blueprint $table) {
            $table->string('estado', 30)->default('Pendiente')->change();
        });
    }
};
