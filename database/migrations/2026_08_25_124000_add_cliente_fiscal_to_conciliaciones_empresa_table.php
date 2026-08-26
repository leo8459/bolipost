<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conciliaciones_empresa', function (Blueprint $table): void {
            $table->string('factura_razon_social')->nullable();
            $table->string('factura_codigo_cliente', 100)->nullable();
            $table->string('factura_numero_documento', 100)->nullable();
            $table->string('factura_tipo_documento', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('conciliaciones_empresa', function (Blueprint $table): void {
            $table->dropColumn([
                'factura_razon_social',
                'factura_codigo_cliente',
                'factura_numero_documento',
                'factura_tipo_documento',
            ]);
        });
    }
};
