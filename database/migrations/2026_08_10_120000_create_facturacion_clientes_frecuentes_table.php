<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facturacion_clientes_frecuentes', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_documento', 20);
            $table->string('numero_documento', 80);
            $table->string('complemento_documento', 30)->default('');
            $table->string('razon_social', 255);
            $table->string('correo_facturacion', 50)->nullable();
            $table->unsignedBigInteger('ultima_venta_id')->nullable();
            $table->unsignedInteger('usos')->default(1);
            $table->timestamps();

            $table->unique(
                ['tipo_documento', 'numero_documento', 'complemento_documento'],
                'fact_clientes_freq_doc_unique'
            );
            $table->index('numero_documento', 'fact_clientes_freq_numero_idx');
            $table->index('updated_at', 'fact_clientes_freq_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturacion_clientes_frecuentes');
    }
};
