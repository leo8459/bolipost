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

        DB::statement("ALTER TABLE fuel_invoice_details ALTER COLUMN estado SET DEFAULT 'No verificar'");

        DB::table('fuel_invoice_details')
            ->whereIn('estado', ['Pendiente', 'Finalizado', 'Sin bitacora'])
            ->update(['estado' => 'No verificar']);

        DB::table('fuel_invoice_details')
            ->whereNull('estado')
            ->update(['estado' => 'No verificar']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('fuel_invoice_details') || !Schema::hasColumn('fuel_invoice_details', 'estado')) {
            return;
        }

        DB::statement("ALTER TABLE fuel_invoice_details ALTER COLUMN estado SET DEFAULT 'Pendiente'");

        DB::table('fuel_invoice_details')
            ->where('estado', 'No verificar')
            ->update(['estado' => 'Pendiente']);
    }
};
