<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bitacoras', function (Blueprint $table) {
            if (!Schema::hasColumn('bitacoras', 'qr_url')) {
                $table->string('qr_url')->nullable()->after('imagen_factura');
            }

            if (!Schema::hasColumn('bitacoras', 'qr_texto')) {
                $table->text('qr_texto')->nullable()->after('qr_url');
            }

            if (!Schema::hasColumn('bitacoras', 'qr_datos')) {
                $table->json('qr_datos')->nullable()->after('qr_texto');
            }

            if (!Schema::hasColumn('bitacoras', 'factura_fecha_emision')) {
                $table->dateTime('factura_fecha_emision')->nullable()->after('qr_datos');
            }

            if (!Schema::hasColumn('bitacoras', 'factura_nit_emisor')) {
                $table->string('factura_nit_emisor', 50)->nullable()->after('factura_fecha_emision');
            }

            if (!Schema::hasColumn('bitacoras', 'factura_cuf')) {
                $table->string('factura_cuf', 255)->nullable()->after('factura_nit_emisor');
            }

            if (!Schema::hasColumn('bitacoras', 'factura_razon_social')) {
                $table->string('factura_razon_social')->nullable()->after('factura_cuf');
            }

            if (!Schema::hasColumn('bitacoras', 'factura_cliente')) {
                $table->string('factura_cliente')->nullable()->after('factura_razon_social');
            }

            if (!Schema::hasColumn('bitacoras', 'factura_direccion')) {
                $table->string('factura_direccion')->nullable()->after('factura_cliente');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bitacoras', function (Blueprint $table) {
            $columns = [
                'qr_url',
                'qr_texto',
                'qr_datos',
                'factura_fecha_emision',
                'factura_nit_emisor',
                'factura_cuf',
                'factura_razon_social',
                'factura_cliente',
                'factura_direccion',
            ];

            $existing = array_values(array_filter($columns, fn (string $column) => Schema::hasColumn('bitacoras', $column)));
            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
