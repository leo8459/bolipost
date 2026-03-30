<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fuel_invoice_details')) {
            return;
        }

        Schema::table('fuel_invoice_details', function (Blueprint $table) {
            if (!Schema::hasColumn('fuel_invoice_details', 'estado')) {
                $table->string('estado', 30)->default('Pendiente')->after('subtotal');
            }
        });

        DB::statement("
            UPDATE fuel_invoice_details fid
            SET estado = CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM vehicle_log vl
                    WHERE vl.fuel_log_id = fid.id
                      AND vl.kilometraje_llegada IS NOT NULL
                ) THEN 'Finalizado'
                WHEN EXISTS (
                    SELECT 1
                    FROM vehicle_log vl
                    WHERE vl.fuel_log_id = fid.id
                ) THEN 'Pendiente'
                ELSE 'Sin bitacora'
            END
        ");
    }

    public function down(): void
    {
        if (!Schema::hasTable('fuel_invoice_details') || !Schema::hasColumn('fuel_invoice_details', 'estado')) {
            return;
        }

        Schema::table('fuel_invoice_details', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
