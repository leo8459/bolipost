<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vehicle_log')) {
            return;
        }

        Schema::table('vehicle_log', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicle_log', 'kilometraje_recorrido')) {
                $table->decimal('kilometraje_recorrido', 10, 2)->nullable()->after('kilometraje_llegada');
            }

            if (!Schema::hasColumn('vehicle_log', 'odometro_photo_path')) {
                $table->string('odometro_photo_path')->nullable()->after('firma_digital');
            }
        });

        DB::table('vehicle_log')
            ->whereNull('kilometraje_recorrido')
            ->whereNotNull('kilometraje_salida')
            ->whereNotNull('kilometraje_llegada')
            ->update([
                'kilometraje_recorrido' => DB::raw('GREATEST(kilometraje_llegada - kilometraje_salida, 0)'),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('vehicle_log')) {
            return;
        }

        Schema::table('vehicle_log', function (Blueprint $table) {
            if (Schema::hasColumn('vehicle_log', 'kilometraje_recorrido')) {
                $table->dropColumn('kilometraje_recorrido');
            }

            if (Schema::hasColumn('vehicle_log', 'odometro_photo_path')) {
                $table->dropColumn('odometro_photo_path');
            }
        });
    }
};
