<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('maintenance_alerts') && Schema::hasColumn('maintenance_alerts', 'ot_id')) {
            Schema::table('maintenance_alerts', function (Blueprint $table) {
                $table->dropConstrainedForeignId('ot_id');
            });
        }

        if (Schema::hasTable('maintenance_work_orders')) {
            Schema::drop('maintenance_work_orders');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('maintenance_work_orders')) {
            Schema::create('maintenance_work_orders', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 30)->unique();
                $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
                $table->foreignId('maintenance_type_id')->nullable()->constrained('maintenance_types')->nullOnDelete();
                $table->string('status', 20)->default('Pendiente');
                $table->text('descripcion')->nullable();
                $table->decimal('costo_estimado', 12, 2)->nullable();
                $table->decimal('costo_final', 12, 2)->nullable();
                $table->string('factura_final_path')->nullable();
                $table->dateTime('fecha_solicitud')->nullable();
                $table->dateTime('fecha_aprobacion')->nullable();
                $table->dateTime('fecha_inicio')->nullable();
                $table->dateTime('fecha_completado')->nullable();
                $table->dateTime('fecha_cierre')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['vehicle_id', 'status']);
            });
        }

        if (Schema::hasTable('maintenance_alerts') && !Schema::hasColumn('maintenance_alerts', 'ot_id')) {
            Schema::table('maintenance_alerts', function (Blueprint $table) {
                $table->foreignId('ot_id')->nullable()->constrained('maintenance_work_orders')->nullOnDelete();
            });
        }
    }
};

