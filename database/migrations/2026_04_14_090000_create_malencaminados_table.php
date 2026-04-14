<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('malencaminados', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->index();
            $table->text('observacion')->nullable();
            $table->unsignedInteger('malencaminamiento')->default(1);
            $table->foreignId('paquetes_ems_id')->nullable()->constrained('paquetes_ems')->nullOnDelete();
            $table->foreignId('paquetes_contrato_id')->nullable()->constrained('paquetes_contrato')->nullOnDelete();
            $table->string('destino_anterior')->nullable();
            $table->string('destino_nuevo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('malencaminados');
    }
};

