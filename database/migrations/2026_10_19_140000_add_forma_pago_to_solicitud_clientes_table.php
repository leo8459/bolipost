<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('solicitud_clientes')) {
            return;
        }

        if (! Schema::hasColumn('solicitud_clientes', 'forma_pago')) {
            Schema::table('solicitud_clientes', function (Blueprint $table) {
                $table->string('forma_pago', 16)->nullable();
            });
        }

        if (Schema::hasColumn('solicitud_clientes', 'pago_destinatario')) {
            DB::table('solicitud_clientes')
                ->where('pago_destinatario', true)
                ->whereNull('forma_pago')
                ->update(['forma_pago' => 'destino']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('solicitud_clientes') && Schema::hasColumn('solicitud_clientes', 'forma_pago')) {
            Schema::table('solicitud_clientes', function (Blueprint $table) {
                $table->dropColumn('forma_pago');
            });
        }
    }
};
