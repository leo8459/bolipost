<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('solicitud_clientes') || Schema::hasColumn('solicitud_clientes', 'cod_especial')) {
            return;
        }

        Schema::table('solicitud_clientes', function (Blueprint $table) {
            $table->string('cod_especial')->nullable()->after('barcode');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('solicitud_clientes') || ! Schema::hasColumn('solicitud_clientes', 'cod_especial')) {
            return;
        }

        Schema::table('solicitud_clientes', function (Blueprint $table) {
            $table->dropColumn('cod_especial');
        });
    }
};
