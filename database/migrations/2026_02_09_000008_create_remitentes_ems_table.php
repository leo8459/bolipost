<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remitentes_ems', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_remitente');
            $table->string('telefono_remitente', 50);
            $table->string('carnet', 100)->unique();
            $table->string('nombre_envia');
            $table->timestamps();

            $table->index('nombre_remitente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remitentes_ems');
    }
};
