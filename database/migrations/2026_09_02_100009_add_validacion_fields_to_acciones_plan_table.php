<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acciones_plan', function (Blueprint $table) {
            $table->foreignId('creador_id')->nullable()->after('responsable_id')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('validada_por')->nullable()->after('creador_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('validada_at')->nullable()->after('validada_por');
            $table->text('comentario_validacion')->nullable()->after('validada_at');
        });

        // Ampliar 'estado' de string(12) para los nuevos valores del enum
        // (el más largo, "Cerrada — pendiente de validación", tiene 33 caracteres).
        Schema::table('acciones_plan', function (Blueprint $table) {
            $table->string('estado', 40)->default('Pendiente')->change();
        });
    }

    public function down(): void
    {
        Schema::table('acciones_plan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('creador_id');
            $table->dropConstrainedForeignId('validada_por');
            $table->dropColumn(['validada_at', 'comentario_validacion']);
            $table->string('estado', 12)->default('Pendiente')->change();
        });
    }
};
