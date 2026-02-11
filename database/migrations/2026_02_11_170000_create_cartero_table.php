<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cartero', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_paquetes_ems')->nullable();
            $table->unsignedBigInteger('id_paquetes_certi')->nullable();
            $table->unsignedBigInteger('id_estados');
            $table->unsignedBigInteger('id_user');
            $table->timestamps();

            $table->foreign('id_paquetes_ems')->references('id')->on('paquetes_ems')->nullOnDelete();
            $table->foreign('id_paquetes_certi')->references('id')->on('paquetes_certi')->nullOnDelete();
            $table->foreign('id_estados')->references('id')->on('estados');
            $table->foreign('id_user')->references('id')->on('users');
            $table->unique('id_paquetes_ems');
            $table->unique('id_paquetes_certi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cartero');
    }
};

