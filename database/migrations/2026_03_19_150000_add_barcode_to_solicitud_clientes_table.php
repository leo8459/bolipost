<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('solicitud_clientes', 'barcode')) {
            Schema::table('solicitud_clientes', function (Blueprint $table) {
                $table->string('barcode')->nullable()->after('codigo_solicitud');
            });
        }

        DB::table('solicitud_clientes')
            ->whereNull('barcode')
            ->whereNotNull('codigo_solicitud')
            ->update([
                'barcode' => DB::raw('codigo_solicitud'),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('solicitud_clientes', 'barcode')) {
            Schema::table('solicitud_clientes', function (Blueprint $table) {
                $table->dropColumn('barcode');
            });
        }
    }
};
