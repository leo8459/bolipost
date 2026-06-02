<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cartero_assignment_reports', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->uuid('token')->unique();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('regional')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->unsignedInteger('total_assigned')->default(0);
            $table->json('summary_by_type')->nullable();
            $table->json('rows')->nullable();
            $table->timestamps();
        });

        Schema::create('cartero_assignment_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cartero_assignment_report_id')
                ->constrained('cartero_assignment_reports')
                ->cascadeOnDelete();
            $table->string('tipo_paquete', 30);
            $table->unsignedBigInteger('paquete_id');
            $table->string('codigo')->nullable();
            $table->timestamps();

            $table->index(['tipo_paquete', 'paquete_id'], 'cartero_assignment_report_items_package_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cartero_assignment_report_items');
        Schema::dropIfExists('cartero_assignment_reports');
    }
};
