<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paquetes_ems', function (Blueprint $table) {
            $table->id();
            $table->string('origen');
            $table->string('tipo_correspondencia');
            $table->text('contenido');
            $table->unsignedInteger('cantidad');
            $table->decimal('peso', 10, 2);
            $table->string('codigo')->unique();
            $table->decimal('precio', 10, 2);
            $table->string('nombre_remitente');
            $table->string('nombre_envia');
            $table->string('carnet');
            $table->string('telefono_remitente');
            $table->string('nombre_destinatario');
            $table->string('telefono_destinatario');
            $table->string('ciudad');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paquetes_ems');
    }
};
