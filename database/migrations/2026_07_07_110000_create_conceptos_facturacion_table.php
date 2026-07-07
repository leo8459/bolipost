<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conceptos_facturacion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('actividad_economica', 6);
            $table->string('codigo_sin', 7);
            $table->string('codigo', 50)->unique();
            $table->unsignedInteger('unidad_medida')->default(58);
            $table->string('descripcion', 500);
            $table->decimal('precio_base', 10, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conceptos_facturacion');
    }
};
