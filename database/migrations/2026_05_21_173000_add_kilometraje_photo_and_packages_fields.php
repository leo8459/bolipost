<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vehicles')) {
            Schema::table('vehicles', function (Blueprint $table) {
                if (!Schema::hasColumn('vehicles', 'kilometraje_inicial_photo_path')) {
                    $table->string('kilometraje_inicial_photo_path')->nullable()->after('kilometraje_inicial');
                }
            });
        }

        if (Schema::hasTable('vehicle_log')) {
            Schema::table('vehicle_log', function (Blueprint $table) {
                if (!Schema::hasColumn('vehicle_log', 'cantidad_paquetes')) {
                    $table->unsignedInteger('cantidad_paquetes')->default(0)->after('kilometraje_recorrido');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vehicle_log')) {
            Schema::table('vehicle_log', function (Blueprint $table) {
                if (Schema::hasColumn('vehicle_log', 'cantidad_paquetes')) {
                    $table->dropColumn('cantidad_paquetes');
                }
            });
        }

        if (Schema::hasTable('vehicles')) {
            Schema::table('vehicles', function (Blueprint $table) {
                if (Schema::hasColumn('vehicles', 'kilometraje_inicial_photo_path')) {
                    $table->dropColumn('kilometraje_inicial_photo_path');
                }
            });
        }
    }
};

