<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_cambio', function (Blueprint $table) {
            $table->foreignId('aprobador_id')->nullable()->after('requiere_comite')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('riesgos_asociados', function (Blueprint $table) {
            $table->foreignId('responsable_id')->nullable()->after('responsable')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('criterios_cierre', function (Blueprint $table) {
            $table->foreignId('responsable_id')->nullable()->after('responsable')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_cambio', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aprobador_id');
        });
        Schema::table('riesgos_asociados', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsable_id');
        });
        Schema::table('criterios_cierre', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsable_id');
        });
    }
};
