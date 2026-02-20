<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cartero_guias', function (Blueprint $table) {
            $table->id();
            $table->string('guia');
            $table->string('transportadora');
            $table->string('provincia');
            $table->foreignId('user_id')->constrained('users');
            $table->string('factura')->nullable();
            $table->string('codigo');
            $table->decimal('precio_total', 10, 2)->nullable();
            $table->decimal('peso_total', 10, 3)->nullable();
            $table->timestamps();

            $table->index('guia');
            $table->index('codigo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cartero_guias');
    }
};
