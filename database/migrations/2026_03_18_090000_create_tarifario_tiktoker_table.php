<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifario_tiktoker', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origen_id')->constrained('origen')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('destino_id')->constrained('destino')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('servicio_extra_id')->nullable()->constrained('servicio_extras')->cascadeOnUpdate()->nullOnDelete();
            $table->decimal('peso1', 10, 2);
            $table->decimal('peso2', 10, 2);
            $table->decimal('peso3', 10, 2);
            $table->decimal('peso_extra', 10, 2);
            $table->unsignedInteger('tiempo_entrega');
            $table->timestamps();

            $table->index(['origen_id', 'destino_id'], 'tarifario_tiktoker_origen_destino_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifario_tiktoker');
    }
};
