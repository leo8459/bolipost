<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('maintenance_logs')) {
            Schema::create('maintenance_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
                $table->string('tipo', 100)->nullable();
                $table->date('fecha')->nullable();
                $table->date('proxima_fecha')->nullable();
                $table->decimal('costo', 10, 2)->nullable();
                $table->decimal('kilometraje', 10, 2)->nullable();
                $table->string('taller')->nullable();
                $table->text('descripcion')->nullable();
                $table->string('comprobante')->nullable();
                $table->text('observaciones')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('fecha');
                $table->index('proxima_fecha');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_logs');
    }
};

