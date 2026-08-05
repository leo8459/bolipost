<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('paquetes_contrato') || Schema::hasColumn('paquetes_contrato', 'destino_registrado')) {
            return;
        }

        Schema::table('paquetes_contrato', function (Blueprint $table) {
            $table->string('destino_registrado')->nullable()->after('destino');
        });

        DB::table('paquetes_contrato')
            ->whereNull('destino_registrado')
            ->update(['destino_registrado' => DB::raw('destino')]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('paquetes_contrato') || ! Schema::hasColumn('paquetes_contrato', 'destino_registrado')) {
            return;
        }

        Schema::table('paquetes_contrato', function (Blueprint $table) {
            $table->dropColumn('destino_registrado');
        });
    }
};
