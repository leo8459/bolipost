<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gas_stations') || !Schema::hasColumn('gas_stations', 'nombre')) {
            return;
        }

        DB::table('gas_stations')
            ->where(function ($q) {
                $q->whereNull('nombre')
                    ->orWhereRaw("trim(nombre) = ''");
            })
            ->update([
                'nombre' => DB::raw("COALESCE(NULLIF(razon_social, ''), NULLIF(nit_emisor, ''), 'SIN NOMBRE')"),
            ]);

        DB::statement("ALTER TABLE gas_stations ALTER COLUMN nombre SET DEFAULT 'SIN NOMBRE'");
    }

    public function down(): void
    {
        if (!Schema::hasTable('gas_stations') || !Schema::hasColumn('gas_stations', 'nombre')) {
            return;
        }

        DB::statement('ALTER TABLE gas_stations ALTER COLUMN nombre DROP DEFAULT');
    }
};

