<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vehicle_log')) {
            Schema::create('vehicle_log', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('drivers_id')->nullable();
                $table->unsignedBigInteger('vehicles_id');
                $table->unsignedBigInteger('fuel_log_id')->nullable();
                $table->date('fecha');
                $table->decimal('kilometraje_salida', 10, 2);
                $table->decimal('kilometraje_llegada', 10, 2)->nullable();
                $table->string('recorrido_inicio');
                $table->string('recorrido_destino');
                $table->boolean('abastecimiento_combustible')->default(false);
                $table->text('firma_digital')->nullable();
                $table->json('ruta_json')->nullable();
                $table->timestamps();

                $table->foreign('drivers_id')->references('id')->on('drivers')->nullOnDelete();
                $table->foreign('vehicles_id')->references('id')->on('vehicles')->cascadeOnDelete();
                $table->foreign('fuel_log_id')->references('id')->on('fuel_logs')->nullOnDelete();

                $table->index('drivers_id');
                $table->index('vehicles_id');
                $table->index('fuel_log_id');
                $table->index('fecha');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_log');
    }
};

