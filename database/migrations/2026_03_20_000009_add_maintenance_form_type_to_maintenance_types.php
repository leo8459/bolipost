<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('maintenance_types')) {
            return;
        }

        Schema::table('maintenance_types', function (Blueprint $table) {
            if (!Schema::hasColumn('maintenance_types', 'maintenance_form_type')) {
                $table->string('maintenance_form_type', 20)->nullable()->after('vehicle_class_id');
            }
        });

        $rows = DB::table('maintenance_types as mt')
            ->leftJoin('vehicle_classes as vc', 'vc.id', '=', 'mt.vehicle_class_id')
            ->select(['mt.id', 'vc.maintenance_form_type'])
            ->get();

        foreach ($rows as $row) {
            DB::table('maintenance_types')
                ->where('id', $row->id)
                ->update([
                    'maintenance_form_type' => in_array($row->maintenance_form_type, ['moto', 'vehiculo'], true)
                        ? $row->maintenance_form_type
                        : 'vehiculo',
                ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('maintenance_types')) {
            return;
        }

        Schema::table('maintenance_types', function (Blueprint $table) {
            if (Schema::hasColumn('maintenance_types', 'maintenance_form_type')) {
                $table->dropColumn('maintenance_form_type');
            }
        });
    }
};
