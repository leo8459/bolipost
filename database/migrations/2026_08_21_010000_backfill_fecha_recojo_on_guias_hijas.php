<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('paquetes_contrato')
            || ! Schema::hasColumn('paquetes_contrato', 'codigo_madre')
            || ! Schema::hasColumn('paquetes_contrato', 'fecha_recojo')
        ) {
            return;
        }

        // Para cada guia hija existente, su fecha de recojo es su propia fecha
        // de creacion. No se toma ninguna fecha de la guia madre.
        DB::table('paquetes_contrato')
            ->whereNotNull('codigo_madre')
            ->whereRaw("TRIM(codigo_madre) <> ''")
            ->whereNull('fecha_recojo')
            ->whereNotNull('created_at')
            ->update([
                'fecha_recojo' => DB::raw('created_at'),
            ]);

        // Respaldo para registros historicos anormales que tampoco tengan
        // created_at: garantiza que ninguna guia hija quede sin fecha.
        DB::table('paquetes_contrato')
            ->whereNotNull('codigo_madre')
            ->whereRaw("TRIM(codigo_madre) <> ''")
            ->whereNull('fecha_recojo')
            ->update([
                'fecha_recojo' => now(),
            ]);
    }

    public function down(): void
    {
        // La correccion de datos es irreversible: no se deben borrar fechas
        // de recojo validas al revertir el despliegue.
    }
};
