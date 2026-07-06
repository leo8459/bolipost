<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('paquetes_int') || !Schema::hasColumn('paquetes_int', 'cod_especial')) {
            return;
        }

        DB::statement('ALTER TABLE paquetes_int ALTER COLUMN cod_especial DROP NOT NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('paquetes_int') || !Schema::hasColumn('paquetes_int', 'cod_especial')) {
            return;
        }

        DB::statement("UPDATE paquetes_int SET cod_especial = '' WHERE cod_especial IS NULL");
        DB::statement('ALTER TABLE paquetes_int ALTER COLUMN cod_especial SET NOT NULL');
    }
};
