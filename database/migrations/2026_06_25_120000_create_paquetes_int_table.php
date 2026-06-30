<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paquetes_int', function (Blueprint $table) {
            $table->id();
            $table->string('cod_especial', 50)->index();
            $table->string('codigo', 100);
            $table->decimal('peso', 10, 3);
            $table->string('destino', 120)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paquetes_int');
    }
};
