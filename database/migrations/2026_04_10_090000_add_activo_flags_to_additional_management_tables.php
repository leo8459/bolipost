<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'maintenance_appointments',
            'workshops',
            'maintenance_types',
            'vehicle_brands',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'activo')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->boolean('activo')->default(true)->after('id');
                    $table->index('activo');
                });

                DB::table($tableName)->update(['activo' => true]);
            }
        }
    }

    public function down(): void
    {
        // Sin rollback destructivo para preservar historial.
    }
};
