<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vehicle_log_investigation_tickets')) {
            return;
        }

        Schema::create('vehicle_log_investigation_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_code', 40)->unique();
            $table->string('session_reference', 120)->index();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('responsible_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('current_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('related_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason_type', 60);
            $table->string('status', 30)->default('Abierto')->index();
            $table->text('message')->nullable();
            $table->unsignedInteger('packages_total')->default(0);
            $table->unsignedInteger('packages_open')->default(0);
            $table->unsignedInteger('packages_delivered')->default(0);
            $table->json('meta_json')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_log_investigation_tickets');
    }
};
