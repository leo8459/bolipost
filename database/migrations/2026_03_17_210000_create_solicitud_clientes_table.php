<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('codigo_solicitud')->nullable()->unique();
            $table->string('estado', 30)->default('PENDIENTE')->index();
            $table->string('origen');
            $table->string('tipo_correspondencia')->nullable();
            $table->string('servicio_especial')->nullable();
            $table->text('contenido');
            $table->unsignedInteger('cantidad');
            $table->decimal('peso', 10, 3);
            $table->decimal('tarifa_estimada', 10, 2)->nullable();
            $table->string('nombre_remitente');
            $table->string('nombre_envia')->nullable();
            $table->string('carnet');
            $table->string('telefono_remitente')->nullable();
            $table->string('nombre_destinatario');
            $table->string('telefono_destinatario')->nullable();
            $table->string('direccion');
            $table->string('ciudad');
            $table->foreignId('servicio_id')->constrained('servicio')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('destino_id')->constrained('destino')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_clientes');
    }
};
