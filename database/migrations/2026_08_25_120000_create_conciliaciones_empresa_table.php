<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conciliaciones_empresa', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresa')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->timestamp('gestora_at')->nullable();
            $table->foreignId('gestora_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('conciliacion_at')->nullable();
            $table->foreignId('conciliacion_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('documento_path')->nullable();
            $table->string('documento_nombre')->nullable();
            $table->string('documento_mime', 120)->nullable();
            $table->unsignedBigInteger('documento_tamano')->nullable();
            $table->timestamp('documento_at')->nullable();
            $table->foreignId('documento_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['empresa_id', 'anio', 'mes'], 'conciliacion_empresa_periodo_unique');
            $table->index(['anio', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliaciones_empresa');
    }
};
