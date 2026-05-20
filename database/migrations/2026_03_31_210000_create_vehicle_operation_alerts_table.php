<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vehicle_operation_alerts')) {
            return;
        }

        Schema::create('vehicle_operation_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_id')->index();
            $table->unsignedBigInteger('vehicle_log_session_id')->nullable()->index();
            $table->string('alert_type', 40)->index();
            $table->string('severity', 20)->default('info')->index();
            $table->string('status', 20)->default('Activa')->index();
            $table->string('title', 180);
            $table->text('message')->nullable();
            $table->string('current_stage', 40)->nullable()->index();
            $table->timestamp('last_heartbeat_at')->nullable()->index();
            $table->timestamp('detected_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->json('meta_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_operation_alerts');
    }
};
