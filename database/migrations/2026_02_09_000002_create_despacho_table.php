<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('despacho', function (Blueprint $table) {
            $table->id();
            $table->string('oforigen');
            $table->string('ofdestino');
            $table->string('categoria');
            $table->string('subclase');
            $table->string('nro_despacho');
            $table->string('nro_envase');
            $table->decimal('peso', 10, 3);
            $table->string('identificador');
            $table->unsignedSmallInteger('anio');
            $table->string('servicio');
            $table->string('departamento');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('despacho');
    }
};
