<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS drivers_licencia_unique_active
            ON drivers (licencia)
            WHERE deleted_at IS NULL
              AND licencia IS NOT NULL
              AND btrim(licencia) <> ''
        ");

        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS drivers_telefono_unique_active
            ON drivers (telefono)
            WHERE deleted_at IS NULL
              AND telefono IS NOT NULL
              AND btrim(telefono) <> ''
        ");

    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS drivers_licencia_unique_active');
        DB::statement('DROP INDEX IF EXISTS drivers_telefono_unique_active');
    }
};
