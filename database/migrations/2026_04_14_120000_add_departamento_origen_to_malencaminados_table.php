<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('malencaminados', function (Blueprint $table) {
            if (! Schema::hasColumn('malencaminados', 'departamento_origen')) {
                $table->string('departamento_origen', 50)->nullable()->after('codigo')->index();
            }
        });

        DB::statement("
            UPDATE malencaminados m
            SET departamento_origen = upper(trim(pe.origen))
            FROM paquetes_ems pe
            WHERE pe.id = m.paquetes_ems_id
        ");

        DB::statement("
            UPDATE malencaminados m
            SET departamento_origen = upper(trim(pc.origen))
            FROM paquetes_contrato pc
            WHERE pc.id = m.paquetes_contrato_id
              AND (m.departamento_origen IS NULL OR trim(m.departamento_origen) = '')
        ");

        DB::table('malencaminados')
            ->whereNull('departamento_origen')
            ->orWhereRaw('trim(departamento_origen) = ?', [''])
            ->update(['departamento_origen' => 'SIN ORIGEN']);
    }

    public function down(): void
    {
        Schema::table('malencaminados', function (Blueprint $table) {
            if (Schema::hasColumn('malencaminados', 'departamento_origen')) {
                $table->dropColumn('departamento_origen');
            }
        });
    }
};
