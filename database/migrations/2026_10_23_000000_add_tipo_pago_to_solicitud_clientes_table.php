<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('solicitud_clientes') && ! Schema::hasColumn('solicitud_clientes', 'tipo_pago')) {
            Schema::table('solicitud_clientes', function (Blueprint $table) {
                $table->string('tipo_pago', 16)->nullable()->after('forma_pago');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('solicitud_clientes') && Schema::hasColumn('solicitud_clientes', 'tipo_pago')) {
            Schema::table('solicitud_clientes', function (Blueprint $table) {
                $table->dropColumn('tipo_pago');
            });
        }
    }
};
