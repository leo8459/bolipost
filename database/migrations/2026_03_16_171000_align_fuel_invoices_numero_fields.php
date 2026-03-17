<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fuel_invoices')) {
            return;
        }

        if (!Schema::hasColumn('fuel_invoices', 'numero') || !Schema::hasColumn('fuel_invoices', 'numero_factura')) {
            return;
        }

        DB::statement("
            UPDATE fuel_invoices
            SET numero_factura = numero
            WHERE (numero_factura IS NULL OR trim(numero_factura) = '')
              AND numero IS NOT NULL
              AND trim(numero) <> ''
        ");

        DB::statement("
            UPDATE fuel_invoices fi
            SET numero = fi.numero_factura
            WHERE (fi.numero IS NULL OR trim(fi.numero) = '')
              AND fi.numero_factura IS NOT NULL
              AND trim(fi.numero_factura) <> ''
              AND NOT EXISTS (
                    SELECT 1
                    FROM fuel_invoices x
                    WHERE x.id <> fi.id
                      AND x.numero = fi.numero_factura
              )
        ");

        DB::statement("
            UPDATE fuel_invoices
            SET numero = 'FI-' || id::text
            WHERE numero IS NULL OR trim(numero) = ''
        ");
    }

    public function down(): void
    {
        // Integracion de datos: sin rollback destructivo.
    }
};

