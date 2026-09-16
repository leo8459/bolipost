<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chasqui_location_points', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('location_id', 64);
            $table->string('device_id', 190);
            $table->string('device_name', 120)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy_m', 10, 2)->nullable();
            $table->decimal('speed_kmh', 8, 2)->nullable();
            $table->decimal('heading', 6, 2)->nullable();
            $table->decimal('battery_percent', 5, 2)->nullable();
            $table->boolean('is_moving')->default(false);
            $table->boolean('gps_enabled')->default(true);
            $table->boolean('gps_mocked')->default(false);
            $table->timestamp('sent_at');
            $table->timestamp('received_at');
            $table->timestamps();

            $table->unique(['location_id', 'sent_at'], 'chasqui_location_device_sent_unique');
            $table->index(['sent_at', 'user_id'], 'chasqui_location_day_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chasqui_location_points');
    }
};
