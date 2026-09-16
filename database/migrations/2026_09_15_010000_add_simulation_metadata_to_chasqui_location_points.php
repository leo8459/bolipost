<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chasqui_location_points', function (Blueprint $table): void {
            $table->boolean('is_simulated')->default(false)->after('gps_mocked')->index();
            $table->string('route_label', 160)->nullable()->after('is_simulated');
        });
    }

    public function down(): void
    {
        Schema::table('chasqui_location_points', function (Blueprint $table): void {
            $table->dropIndex(['is_simulated']);
            $table->dropColumn(['is_simulated', 'route_label']);
        });
    }
};
