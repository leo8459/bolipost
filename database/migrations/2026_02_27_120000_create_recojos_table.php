<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recojos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('codigo')->index();
            $table->string('estado');
            $table->string('origen');
            $table->string('destino');
            $table->string('nombre_r');
            $table->string('telefono_r');
            $table->text('contenido');
            $table->string('direccion_r');
            $table->string('nombre_d');
            $table->string('telefono_d');
            $table->string('direccion_d');
            $table->string('mapa')->nullable();
            $table->string('provincia');
            $table->decimal('peso', 10, 3);
            $table->date('fecha_recojo');
            $table->text('observacion')->nullable();
            $table->text('justificacion')->nullable();
            $table->string('imagen')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recojos');
    }
};
