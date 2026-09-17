<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('solicitudes_cambio', function (Blueprint $table) {
            $table->text('nota_cierre')->nullable()->after('decision_comentario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes_cambio', function (Blueprint $table) {
            $table->dropColumn('nota_cierre');
        });
    }
};
