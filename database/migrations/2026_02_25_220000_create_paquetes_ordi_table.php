<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paquetes_ordi', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
            $table->string('destinatario');
            $table->string('telefono', 30);
            $table->string('ciudad');
            $table->string('zona');
            $table->decimal('peso', 10, 3);
            $table->string('aduana', 50);
            $table->text('observaciones')->nullable();
            $table->string('cod_especial')->nullable();
            $table->unsignedBigInteger('fk_ventanilla');
            $table->unsignedBigInteger('fk_estado');
            $table->timestamps();

            $table->foreign('fk_ventanilla')->references('id')->on('ventanilla');
            $table->foreign('fk_estado')->references('id')->on('estados');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paquetes_ordi');
    }
};
