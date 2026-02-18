<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('paquetes_ems', 'cod_especial')) {
            return;
        }

        try {
            DB::statement('ALTER TABLE paquetes_ems DROP CONSTRAINT IF EXISTS paquetes_ems_cod_especial_unique');
        } catch (\Throwable $e) {
            // ignore; may not be PostgreSQL or constraint may not exist
        }

        try {
            Schema::table('paquetes_ems', function (Blueprint $table) {
                $table->dropUnique(['cod_especial']);
            });
        } catch (\Throwable $e) {
            // ignore if unique index is already removed
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('paquetes_ems', 'cod_especial')) {
            return;
        }

        try {
            Schema::table('paquetes_ems', function (Blueprint $table) {
                $table->unique('cod_especial');
            });
        } catch (\Throwable $e) {
            // ignore if unique already exists
        }
    }
};
