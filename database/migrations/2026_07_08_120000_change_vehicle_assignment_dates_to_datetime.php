<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vehicle_assignments')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE vehicle_assignments MODIFY fecha_inicio DATETIME NULL');
            DB::statement('ALTER TABLE vehicle_assignments MODIFY fecha_fin DATETIME NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE vehicle_assignments ALTER COLUMN fecha_inicio TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING fecha_inicio::timestamp(0)');
            DB::statement('ALTER TABLE vehicle_assignments ALTER COLUMN fecha_fin TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING fecha_fin::timestamp(0)');
            return;
        }

        // SQLite keeps flexible column storage, so existing date columns can store datetime strings.
    }

    public function down(): void
    {
        if (!Schema::hasTable('vehicle_assignments')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE vehicle_assignments MODIFY fecha_inicio DATE NULL');
            DB::statement('ALTER TABLE vehicle_assignments MODIFY fecha_fin DATE NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE vehicle_assignments ALTER COLUMN fecha_inicio TYPE DATE USING fecha_inicio::date');
            DB::statement('ALTER TABLE vehicle_assignments ALTER COLUMN fecha_fin TYPE DATE USING fecha_fin::date');
        }
    }
};
