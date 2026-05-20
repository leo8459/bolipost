<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('maintenance_type_vehicle')) {
            return;
        }

        Schema::create('maintenance_type_vehicle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_type_id')->constrained('maintenance_types')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['maintenance_type_id', 'vehicle_id'], 'maintenance_type_vehicle_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_type_vehicle');
    }
};
