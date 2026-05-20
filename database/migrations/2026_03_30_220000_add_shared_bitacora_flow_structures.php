<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vehicle_log_sessions')) {
            Schema::create('vehicle_log_sessions', function (Blueprint $table) {
                $table->id();
                $table->string('session_reference')->unique();
                $table->unsignedBigInteger('vehicle_id')->nullable()->index();
                $table->unsignedBigInteger('responsible_driver_id')->nullable()->index();
                $table->unsignedBigInteger('current_driver_id')->nullable()->index();
                $table->unsignedBigInteger('origin_vehicle_log_id')->nullable()->index();
                $table->dateTime('started_at')->nullable();
                $table->dateTime('last_reassigned_at')->nullable();
                $table->dateTime('ended_at')->nullable();
                $table->string('status', 40)->default('Activa')->index();
                $table->json('meta_json')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('vehicle_log_stage_events')) {
            Schema::create('vehicle_log_stage_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vehicle_log_session_id')->nullable()->index();
                $table->unsignedBigInteger('vehicle_log_id')->nullable()->index();
                $table->string('session_reference')->nullable()->index();
                $table->unsignedBigInteger('vehicle_id')->nullable()->index();
                $table->unsignedBigInteger('responsible_driver_id')->nullable()->index();
                $table->unsignedBigInteger('acting_driver_id')->nullable()->index();
                $table->string('stage_name', 60)->index();
                $table->string('event_kind', 40)->default('stage')->index();
                $table->string('address')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 11, 7)->nullable();
                $table->dateTime('event_at')->nullable();
                $table->string('photo_path')->nullable();
                $table->text('notes')->nullable();
                $table->json('payload_json')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('vehicle_log')) {
            Schema::table('vehicle_log', function (Blueprint $table) {
                if (!Schema::hasColumn('vehicle_log', 'session_reference')) {
                    $table->string('session_reference')->nullable()->index();
                }
                if (!Schema::hasColumn('vehicle_log', 'responsible_driver_id')) {
                    $table->unsignedBigInteger('responsible_driver_id')->nullable()->index();
                }
                if (!Schema::hasColumn('vehicle_log', 'current_driver_id')) {
                    $table->unsignedBigInteger('current_driver_id')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vehicle_log')) {
            Schema::table('vehicle_log', function (Blueprint $table) {
                if (Schema::hasColumn('vehicle_log', 'session_reference')) {
                    $table->dropColumn('session_reference');
                }
                if (Schema::hasColumn('vehicle_log', 'responsible_driver_id')) {
                    $table->dropColumn('responsible_driver_id');
                }
                if (Schema::hasColumn('vehicle_log', 'current_driver_id')) {
                    $table->dropColumn('current_driver_id');
                }
            });
        }

        Schema::dropIfExists('vehicle_log_stage_events');
        Schema::dropIfExists('vehicle_log_sessions');
    }
};
