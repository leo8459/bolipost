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
            if (!Schema::hasColumn('vehicle_log', 'latitud_inicio')) {
                $table->decimal('latitud_inicio', 11, 8)->nullable();
            }
            if (!Schema::hasColumn('vehicle_log', 'logitud_inicio')) {
                $table->decimal('logitud_inicio', 11, 8)->nullable();
            }
            if (!Schema::hasColumn('vehicle_log', 'latitud_destino')) {
                $table->decimal('latitud_destino', 11, 8)->nullable();
            }
            if (!Schema::hasColumn('vehicle_log', 'logitud_destino')) {
                $table->decimal('logitud_destino', 11, 8)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('vehicle_log')) {
            return;
        }

        Schema::table('vehicle_log', function (Blueprint $table) {
            if (Schema::hasColumn('vehicle_log', 'latitud_inicio')) {
                $table->dropColumn('latitud_inicio');
            }
            if (Schema::hasColumn('vehicle_log', 'logitud_inicio')) {
                $table->dropColumn('logitud_inicio');
            }
            if (Schema::hasColumn('vehicle_log', 'latitud_destino')) {
                $table->dropColumn('latitud_destino');
            }
            if (Schema::hasColumn('vehicle_log', 'logitud_destino')) {
                $table->dropColumn('logitud_destino');
            }
        });
    }
};

