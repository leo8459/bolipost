<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saca', function (Blueprint $table) {
            $table->id();
            $table->string('nro_saca');
            $table->string('identificador');
            $table->string('estado');
            $table->decimal('peso', 10, 3)->nullable();
            $table->unsignedInteger('paquetes')->nullable();
            $table->string('busqueda')->nullable();
            $table->string('receptaculo')->nullable();
            $table->foreignId('fk_despacho')->constrained('despacho')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saca');
    }
};
