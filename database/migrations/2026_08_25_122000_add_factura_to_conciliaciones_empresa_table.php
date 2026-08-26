<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conciliaciones_empresa', function (Blueprint $table): void {
            $table->string('factura_venta_id', 100)->nullable()->unique();
            $table->string('factura_detalle_id', 100)->nullable();
            $table->text('factura_descripcion')->nullable();
            $table->string('factura_codigo_orden', 100)->nullable();
            $table->string('factura_codigo_seguimiento', 100)->nullable();
            $table->timestamp('factura_fecha')->nullable();
            $table->decimal('factura_monto', 15, 2)->nullable();
            $table->unsignedSmallInteger('facturado_anio')->nullable();
            $table->unsignedTinyInteger('facturado_mes')->nullable();
            $table->timestamp('por_cobrar_at')->nullable();
            $table->foreignId('por_cobrar_por')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conciliaciones_empresa', function (Blueprint $table): void {
            $table->dropForeign(['por_cobrar_por']);
            $table->dropUnique(['factura_venta_id']);
            $table->dropColumn([
                'factura_venta_id',
                'factura_detalle_id',
                'factura_descripcion',
                'factura_codigo_orden',
                'factura_codigo_seguimiento',
                'factura_fecha',
                'factura_monto',
                'facturado_anio',
                'facturado_mes',
                'por_cobrar_at',
                'por_cobrar_por',
            ]);
        });
    }
};
