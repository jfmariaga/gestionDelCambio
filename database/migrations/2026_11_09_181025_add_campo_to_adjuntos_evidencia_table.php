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
        Schema::table('adjuntos_evidencia', function (Blueprint $table) {
            $table->string('campo', 30)->nullable()->after('adjuntable_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('adjuntos_evidencia', function (Blueprint $table) {
            $table->dropColumn('campo');
        });
    }
};
