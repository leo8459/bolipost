<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('solicitud_clientes') && ! Schema::hasColumn('solicitud_clientes', 'recargo_pago_destino')) {
            Schema::table('solicitud_clientes', function (Blueprint $table) {
                $table->decimal('recargo_pago_destino', 8, 2)->default(0)->after('precio');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('solicitud_clientes') && Schema::hasColumn('solicitud_clientes', 'recargo_pago_destino')) {
            Schema::table('solicitud_clientes', function (Blueprint $table) {
                $table->dropColumn('recargo_pago_destino');
            });
        }
    }
};
