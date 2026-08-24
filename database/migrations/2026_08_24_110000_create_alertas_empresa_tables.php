<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas_empresa', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 150);
            $table->text('mensaje')->nullable();
            $table->string('portada_path');
            $table->string('pdf_path')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('publicada_at')->useCurrent();
            $table->timestamp('vence_at')->nullable();
            $table->timestamps();
        });

        Schema::create('alerta_empresa_destinatarios', function (Blueprint $table) {
            $table->foreignId('alerta_empresa_id')->constrained('alertas_empresa')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresa')->cascadeOnDelete();
            $table->primary(['alerta_empresa_id', 'empresa_id']);
        });

        Schema::create('alerta_empresa_lecturas', function (Blueprint $table) {
            $table->foreignId('alerta_empresa_id')->constrained('alertas_empresa')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('leida_at')->useCurrent();
            $table->primary(['alerta_empresa_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerta_empresa_lecturas');
        Schema::dropIfExists('alerta_empresa_destinatarios');
        Schema::dropIfExists('alertas_empresa');
    }
};
