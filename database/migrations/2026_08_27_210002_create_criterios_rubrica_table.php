<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criterios_rubrica', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->text('desc_nivel_1');
            $table->text('desc_nivel_2');
            $table->text('desc_nivel_3');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('criterios_rubrica');
    }
};
