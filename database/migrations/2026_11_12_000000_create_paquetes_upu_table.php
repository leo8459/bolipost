<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paquetes_upu', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 35)->unique();
            $table->string('destinatario')->nullable();
            $table->string('telefono', 64)->nullable();
            $table->string('destino');
            $table->text('zona')->nullable();
            $table->decimal('peso', 10, 3)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paquetes_upu');
    }
};
