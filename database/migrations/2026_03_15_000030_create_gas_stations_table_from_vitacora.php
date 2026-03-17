<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gas_stations')) {
            Schema::create('gas_stations', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->string('ubicacion')->nullable();
                $table->string('ciudad')->nullable();
                $table->string('provincia')->nullable();
                $table->string('telefono', 20)->nullable();
                $table->string('email')->nullable();
                $table->decimal('latitud', 10, 8)->nullable();
                $table->decimal('longitud', 11, 8)->nullable();
                $table->boolean('activa')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gas_stations');
    }
};

