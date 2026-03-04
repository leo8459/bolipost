<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifa_contrato', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                ->constrained('empresa')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('origen');
            $table->string('destino');
            $table->string('servicio');
            $table->decimal('kilo', 10, 2);
            $table->decimal('kilo_extra', 10, 2);
            $table->string('provincia');
            $table->decimal('retencion', 10, 2);
            $table->unsignedInteger('dias_entrega');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifa_contrato');
    }
};

