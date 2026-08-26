<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conciliaciones_empresa', function (Blueprint $table): void {
            $table->string('pago_comprobante_path')->nullable();
            $table->string('pago_comprobante_nombre')->nullable();
            $table->unsignedBigInteger('pago_comprobante_tamano')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('conciliaciones_empresa', function (Blueprint $table): void {
            $table->dropColumn([
                'pago_comprobante_path',
                'pago_comprobante_nombre',
                'pago_comprobante_tamano',
            ]);
        });
    }
};
