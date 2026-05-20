<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('activity_logs', 'vehicle_log_id')) {
                $table->unsignedBigInteger('vehicle_log_id')->nullable()->after('record_id');
                $table->index('vehicle_log_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            if (Schema::hasColumn('activity_logs', 'vehicle_log_id')) {
                $table->dropIndex(['vehicle_log_id']);
                $table->dropColumn('vehicle_log_id');
            }
        });
    }
};
