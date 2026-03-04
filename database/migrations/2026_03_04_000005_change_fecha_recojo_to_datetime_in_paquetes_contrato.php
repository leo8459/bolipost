<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('paquetes_contrato') || !Schema::hasColumn('paquetes_contrato', 'fecha_recojo')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(
                "ALTER TABLE paquetes_contrato
                 ALTER COLUMN fecha_recojo TYPE timestamp(0) without time zone
                 USING fecha_recojo::timestamp(0)"
            );
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE paquetes_contrato MODIFY fecha_recojo DATETIME NOT NULL');
            return;
        }

        if ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE paquetes_contrato ALTER COLUMN fecha_recojo DATETIME NOT NULL');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('paquetes_contrato') || !Schema::hasColumn('paquetes_contrato', 'fecha_recojo')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(
                "ALTER TABLE paquetes_contrato
                 ALTER COLUMN fecha_recojo TYPE date
                 USING fecha_recojo::date"
            );
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE paquetes_contrato MODIFY fecha_recojo DATE NOT NULL');
            return;
        }

        if ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE paquetes_contrato ALTER COLUMN fecha_recojo DATE NOT NULL');
        }
    }
};
