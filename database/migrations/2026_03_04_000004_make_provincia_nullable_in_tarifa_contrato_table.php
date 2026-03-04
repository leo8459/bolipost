<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tarifa_contrato') && Schema::hasColumn('tarifa_contrato', 'provincia')) {
            DB::statement('ALTER TABLE tarifa_contrato ALTER COLUMN provincia DROP NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tarifa_contrato') && Schema::hasColumn('tarifa_contrato', 'provincia')) {
            DB::statement("UPDATE tarifa_contrato SET provincia = '' WHERE provincia IS NULL");
            DB::statement('ALTER TABLE tarifa_contrato ALTER COLUMN provincia SET NOT NULL');
        }
    }
};

