<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paquetes_certi', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
            $table->string('destinatario');
            $table->integer('telefono');
            $table->string('cuidad');
            $table->string('zona');
            $table->string('ventanilla');
            $table->decimal('peso', 10, 3);
            $table->string('tipo');
            $table->string('aduana');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paquetes_certi');
    }
};
