<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 13);
            $table->text('fcm_token');
            $table->string('package_name', 120)->nullable();
            $table->text('last_sig')->nullable();
            $table->timestamps();

            $table->unique(['codigo', 'fcm_token']);
            $table->index('codigo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_subscriptions');
    }
};
