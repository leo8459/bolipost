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
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->cascadeOnDelete();
            $table->string('codigo_solicitud')->nullable()->unique();
            $table->string('barcode')->nullable();
            $table->string('cod_especial')->nullable();
            $table->string('origen');
            $table->string('tipo_correspondencia')->nullable();
            $table->string('servicio_especial')->nullable();
            $table->text('contenido');
            $table->unsignedInteger('cantidad');
            $table->decimal('peso', 10, 3)->nullable();
            $table->decimal('precio', 10, 2)->nullable();
            $table->boolean('pago_destinatario')->default(false);
            $table->string('nombre_remitente');
            $table->string('nombre_envia')->nullable();
            $table->string('carnet');
            $table->string('telefono_remitente')->nullable();
            $table->string('nombre_destinatario');
            $table->string('telefono_destinatario')->nullable();
            $table->string('direccion');
            $table->string('direccion_recojo')->nullable();
            $table->string('ciudad');
            $table->foreignId('estado_id')->nullable()->constrained('estados')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('servicio_id')->nullable()->constrained('servicio')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('destino_id')->constrained('destino')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('tarifario_tiktoker_id')->nullable()->constrained('tarifario_tiktoker')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('servicio_extra_id')->nullable()->constrained('servicio_extras')->cascadeOnUpdate()->nullOnDelete();
            $table->string('recepcionado_por')->nullable();
            $table->text('observacion')->nullable();
            $table->string('imagen')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_clientes');
    }
};
