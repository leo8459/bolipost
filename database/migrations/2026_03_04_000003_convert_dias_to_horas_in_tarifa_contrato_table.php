<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('tarifa_contrato') &&
            Schema::hasColumn('tarifa_contrato', 'dias_entrega') &&
            !Schema::hasColumn('tarifa_contrato', 'horas_entrega')
        ) {
            DB::statement('ALTER TABLE tarifa_contrato RENAME COLUMN dias_entrega TO horas_entrega');
            DB::statement('UPDATE tarifa_contrato SET horas_entrega = COALESCE(horas_entrega, 0) * 24');
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('tarifa_contrato') &&
            Schema::hasColumn('tarifa_contrato', 'horas_entrega') &&
            !Schema::hasColumn('tarifa_contrato', 'dias_entrega')
        ) {
            DB::statement('UPDATE tarifa_contrato SET horas_entrega = CEIL(COALESCE(horas_entrega, 0) / 24.0)');
            DB::statement('ALTER TABLE tarifa_contrato RENAME COLUMN horas_entrega TO dias_entrega');
        }
    }
};

