<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifario_referencias_manuales', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 80)->unique();
            $table->string('nombre', 180);
            $table->string('estado', 20)->default('VIGENTE');
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('publicada_por')->nullable();
            $table->timestamp('publicada_en');
            $table->timestamp('vigente_desde');
            $table->timestamp('vigente_hasta')->nullable();
            $table->timestamps();

            $table->index(['estado', 'vigente_desde', 'vigente_hasta'], 'tarifario_manual_referencia_estado_vigencia_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifario_referencias_manuales');
    }
};
