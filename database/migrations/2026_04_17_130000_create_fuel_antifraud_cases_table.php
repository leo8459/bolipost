<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fuel_antifraud_cases')) {
            return;
        }

        Schema::create('fuel_antifraud_cases', function (Blueprint $table): void {
            $table->id();
            $table->string('case_key')->unique();
            $table->string('type', 60)->default('duplicate_invoice');
            $table->string('status', 40)->default('pending');
            $table->string('invoice_number')->nullable()->index();
            $table->foreignId('fuel_invoice_id')->nullable()->constrained('fuel_invoices')->nullOnDelete();
            $table->foreignId('conflicting_fuel_invoice_id')->nullable()->constrained('fuel_invoices')->nullOnDelete();
            $table->unsignedBigInteger('fuel_log_id')->nullable()->index();
            $table->unsignedBigInteger('conflicting_fuel_log_id')->nullable()->index();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('conflicting_vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('conflicting_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->string('detected_source', 40)->nullable();
            $table->text('summary')->nullable();
            $table->json('evidence_json')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['fuel_invoice_id', 'conflicting_fuel_invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_antifraud_cases');
    }
};
