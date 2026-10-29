<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('solicitud_clientes') && ! Schema::hasColumn('solicitud_clientes', 'recargo_volumen')) {
            Schema::table('solicitud_clientes', function (Blueprint $table) {
                $table->boolean('recargo_volumen')->default(false)->after('recargo_pago_destino');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('solicitud_clientes') && Schema::hasColumn('solicitud_clientes', 'recargo_volumen')) {
            Schema::table('solicitud_clientes', function (Blueprint $table) {
                $table->dropColumn('recargo_volumen');
            });
        }
    }
};
