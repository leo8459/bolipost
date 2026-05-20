<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fuel_invoice_details') || !Schema::hasColumn('fuel_invoice_details', 'estado')) {
            return;
        }

        DB::table('fuel_invoice_details')
            ->where('estado', 'No verificar')
            ->update(['estado' => 'Falta verificar']);

        DB::statement("ALTER TABLE fuel_invoice_details ALTER COLUMN estado SET DEFAULT 'Falta verificar'");
    }

    public function down(): void
    {
        if (!Schema::hasTable('fuel_invoice_details') || !Schema::hasColumn('fuel_invoice_details', 'estado')) {
            return;
        }

        DB::table('fuel_invoice_details')
            ->where('estado', 'Falta verificar')
            ->update(['estado' => 'No verificar']);

        DB::statement("ALTER TABLE fuel_invoice_details ALTER COLUMN estado SET DEFAULT 'No verificar'");
    }
};
