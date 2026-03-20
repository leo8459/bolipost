<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('cartero', 'id_solicitud_cliente')) {
            Schema::table('cartero', function (Blueprint $table) {
                $table->unsignedBigInteger('id_solicitud_cliente')->nullable()->after('id_paquetes_contrato');
                $table->foreign('id_solicitud_cliente')
                    ->references('id')
                    ->on('solicitud_clientes')
                    ->nullOnDelete();
                $table->unique('id_solicitud_cliente');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cartero', 'id_solicitud_cliente')) {
            Schema::table('cartero', function (Blueprint $table) {
                $table->dropUnique(['id_solicitud_cliente']);
                $table->dropForeign(['id_solicitud_cliente']);
                $table->dropColumn('id_solicitud_cliente');
            });
        }
    }
};
