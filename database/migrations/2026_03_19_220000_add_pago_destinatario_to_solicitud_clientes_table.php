<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('solicitud_clientes') || Schema::hasColumn('solicitud_clientes', 'pago_destinatario')) {
            return;
        }

        Schema::table('solicitud_clientes', function (Blueprint $table) {
            $table->boolean('pago_destinatario')->default(false)->after('precio');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('solicitud_clientes') || !Schema::hasColumn('solicitud_clientes', 'pago_destinatario')) {
            return;
        }

        Schema::table('solicitud_clientes', function (Blueprint $table) {
            $table->dropColumn('pago_destinatario');
        });
    }
};
