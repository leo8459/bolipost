<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_historiales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresa')->nullOnDelete();
            $table->foreignId('archivado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nombre');
            $table->string('sigla')->nullable();
            $table->string('codigo_cliente')->nullable();
            $table->string('clasificacion')->nullable();
            $table->string('documentacion_legal')->nullable();
            $table->date('inicio_contrato')->nullable();
            $table->date('fin_contrato')->nullable();
            $table->string('cobertura')->nullable();
            $table->decimal('presupuesto', 15, 2)->nullable();
            $table->string('documento_pdf_path')->nullable();
            $table->json('datos_completos');
            $table->timestamps();

            $table->index(['codigo_cliente', 'created_at']);
            $table->index(['empresa_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_historiales');
    }
};
