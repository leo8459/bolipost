<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('maintenance_alert_user_reads')) {
            return;
        }

        Schema::create('maintenance_alert_user_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_alert_id')->constrained('maintenance_alerts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['maintenance_alert_id', 'user_id'], 'maintenance_alert_user_reads_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_alert_user_reads');
    }
};
