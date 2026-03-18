<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vehicle_log') || !Schema::hasColumn('vehicle_log', 'fuel_log_id')) {
            return;
        }

        // Remove legacy FK that points to fuel_logs(id)
        DB::statement('ALTER TABLE vehicle_log DROP CONSTRAINT IF EXISTS vehicle_log_fuel_log_id_foreign');

        if (!Schema::hasTable('fuel_invoice_details')) {
            return;
        }

        // Recreate FK to the table currently used by FuelLog model.
        DB::statement('ALTER TABLE vehicle_log ADD CONSTRAINT vehicle_log_fuel_log_id_foreign FOREIGN KEY (fuel_log_id) REFERENCES fuel_invoice_details(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('vehicle_log') || !Schema::hasColumn('vehicle_log', 'fuel_log_id')) {
            return;
        }

        DB::statement('ALTER TABLE vehicle_log DROP CONSTRAINT IF EXISTS vehicle_log_fuel_log_id_foreign');

        if (!Schema::hasTable('fuel_logs')) {
            return;
        }

        DB::statement('ALTER TABLE vehicle_log ADD CONSTRAINT vehicle_log_fuel_log_id_foreign FOREIGN KEY (fuel_log_id) REFERENCES fuel_logs(id) ON DELETE SET NULL');
    }
};

