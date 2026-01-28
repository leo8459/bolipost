<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plantillas', function (Blueprint $table) {
            $table->id();

            // 🔹 Campos solicitados
            $table->string('nombre');
            $table->string('ciudad');
            $table->string('destinatario');
            $table->string('remitente');
            $table->string('telefono');
            $table->string('ciudad_destino');
            $table->string('estado')->default('LISTO');
            $table->text('observacion')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plantillas');
    }
};
