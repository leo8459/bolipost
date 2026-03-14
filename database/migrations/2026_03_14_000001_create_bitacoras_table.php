<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacoras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paquetes_ems_id')->nullable()->constrained('paquetes_ems')->nullOnDelete();
            $table->foreignId('paquetes_contrato_id')->nullable()->constrained('paquetes_contrato')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('cod_especial', 50)->index();
            $table->string('transportadora')->nullable();
            $table->string('provincia')->nullable();
            $table->string('factura')->nullable();
            $table->decimal('precio_total', 10, 2)->nullable();
            $table->decimal('peso', 10, 3)->default(0);
            $table->string('imagen_factura')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacoras');
    }
};
