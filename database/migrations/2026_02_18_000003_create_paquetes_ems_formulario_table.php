<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paquetes_ems_formulario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paquete_ems_id')
                ->unique()
                ->constrained('paquetes_ems')
                ->cascadeOnDelete();
            $table->string('origen')->nullable();
            $table->string('tipo_correspondencia');
            $table->string('servicio_especial')->nullable();
            $table->text('contenido');
            $table->unsignedInteger('cantidad');
            $table->decimal('peso', 10, 3);
            $table->string('codigo')->nullable();
            $table->decimal('precio', 10, 2)->nullable();
            $table->string('nombre_remitente');
            $table->string('nombre_envia');
            $table->string('carnet');
            $table->string('telefono_remitente');
            $table->string('nombre_destinatario');
            $table->string('telefono_destinatario');
            $table->string('ciudad')->nullable();
            $table->unsignedBigInteger('servicio_id')->nullable();
            $table->unsignedBigInteger('destino_id')->nullable();
            $table->unsignedBigInteger('tarifario_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paquetes_ems_formulario');
    }
};
