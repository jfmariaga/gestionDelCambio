<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_cambio', function (Blueprint $table) {
            $table->foreignId('planta_id')->nullable()->after('id')
                ->constrained('plantas')->nullOnDelete();
            $table->foreignId('aprobador_asignado_id')->nullable()->after('aprobador_id')
                ->constrained('users')->nullOnDelete();
            $table->boolean('aprobador_override')->default(false)->after('aprobador_asignado_id');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_cambio', function (Blueprint $table) {
            $table->dropConstrainedForeignId('planta_id');
            $table->dropConstrainedForeignId('aprobador_asignado_id');
            $table->dropColumn('aprobador_override');
        });
    }
};
