<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vehicle_log')) {
            return;
        }

        Schema::table('vehicle_log', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicle_log', 'cantidad_paquetes')) {
                $table->unsignedInteger('cantidad_paquetes')->nullable()->after('kilometraje_recorrido');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('vehicle_log')) {
            return;
        }

        Schema::table('vehicle_log', function (Blueprint $table) {
            if (Schema::hasColumn('vehicle_log', 'cantidad_paquetes')) {
                $table->dropColumn('cantidad_paquetes');
            }
        });
    }
};
