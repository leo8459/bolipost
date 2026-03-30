<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('maintenance_alerts')) {
            return;
        }

        Schema::table('maintenance_alerts', function (Blueprint $table) {
            if (!Schema::hasColumn('maintenance_alerts', 'postponed_until')) {
                $table->dateTime('postponed_until')->nullable()->after('fecha_resolucion');
            }

            if (!Schema::hasColumn('maintenance_alerts', 'postponed_once')) {
                $table->boolean('postponed_once')->default(false)->after('postponed_until');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('maintenance_alerts')) {
            return;
        }

        Schema::table('maintenance_alerts', function (Blueprint $table) {
            if (Schema::hasColumn('maintenance_alerts', 'postponed_once')) {
                $table->dropColumn('postponed_once');
            }

            if (Schema::hasColumn('maintenance_alerts', 'postponed_until')) {
                $table->dropColumn('postponed_until');
            }
        });
    }
};
