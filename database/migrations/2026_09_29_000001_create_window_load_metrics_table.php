<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('window_load_metrics', function (Blueprint $table): void {
            $table->id();
            $table->string('route_name', 190);
            $table->string('window_name', 180);
            $table->unsignedInteger('load_time_ms');
            $table->unsignedInteger('server_time_ms')->nullable();
            $table->timestamp('measured_at')->index();
            $table->timestamps();

            $table->index(['route_name', 'measured_at'], 'window_load_route_measured_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('window_load_metrics');
    }
};
