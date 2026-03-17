<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vehicles')) {
            Schema::create('vehicles', function (Blueprint $table) {
                $table->id();
                $table->string('placa', 20)->unique();
                $table->string('marca', 50)->nullable();
                $table->string('modelo', 50)->nullable();
                $table->string('tipo_combustible', 30)->nullable();
                $table->string('color', 30)->nullable();
                $table->integer('anio')->nullable();
                $table->decimal('capacidad_tanque', 10, 2)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};

