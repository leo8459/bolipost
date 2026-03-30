<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitud_clientes', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitud_clientes', 'recepcionado_por')) {
                $table->string('recepcionado_por')->nullable()->after('tarifario_tiktoker_id');
            }

            if (!Schema::hasColumn('solicitud_clientes', 'observacion')) {
                $table->text('observacion')->nullable()->after('recepcionado_por');
            }

            if (!Schema::hasColumn('solicitud_clientes', 'imagen')) {
                $table->string('imagen')->nullable()->after('observacion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('solicitud_clientes', function (Blueprint $table) {
            if (Schema::hasColumn('solicitud_clientes', 'imagen')) {
                $table->dropColumn('imagen');
            }

            if (Schema::hasColumn('solicitud_clientes', 'observacion')) {
                $table->dropColumn('observacion');
            }

            if (Schema::hasColumn('solicitud_clientes', 'recepcionado_por')) {
                $table->dropColumn('recepcionado_por');
            }
        });
    }
};
