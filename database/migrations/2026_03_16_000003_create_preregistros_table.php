<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preregistros', function (Blueprint $table) {
            $table->id();
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
            $table->foreignId('validado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validado_at')->nullable();
            $table->foreignId('paquete_ems_id')->nullable()->constrained('paquetes_ems')->nullOnDelete();
            $table->string('codigo_generado')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preregistros');
    }
};
