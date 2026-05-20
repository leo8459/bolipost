<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fuel_invoices') && !Schema::hasColumn('fuel_invoices', 'activo')) {
            Schema::table('fuel_invoices', function (Blueprint $table) {
                $table->boolean('activo')->default(true)->after('invoice_photo_path');
                $table->index('activo');
            });

            DB::table('fuel_invoices')->update(['activo' => true]);
        }

        if (Schema::hasTable('fuel_invoice_details') && !Schema::hasColumn('fuel_invoice_details', 'activo')) {
            Schema::table('fuel_invoice_details', function (Blueprint $table) {
                $table->boolean('activo')->default(true)->after('subtotal');
                $table->index('activo');
            });

            DB::table('fuel_invoice_details')->update(['activo' => true]);
        }

        if (Schema::hasTable('maintenance_logs') && !Schema::hasColumn('maintenance_logs', 'activo')) {
            Schema::table('maintenance_logs', function (Blueprint $table) {
                $table->boolean('activo')->default(true)->after('observaciones');
                $table->index('activo');
            });

            DB::table('maintenance_logs')->update(['activo' => true]);
        }

        if (Schema::hasTable('vehicle_log') && !Schema::hasColumn('vehicle_log', 'activo')) {
            Schema::table('vehicle_log', function (Blueprint $table) {
                $table->boolean('activo')->default(true)->after('points_json');
                $table->index('activo');
            });

            DB::table('vehicle_log')->update(['activo' => true]);
        }
    }

    public function down(): void
    {
        // Sin rollback destructivo para preservar el historial operativo.
    }
};
