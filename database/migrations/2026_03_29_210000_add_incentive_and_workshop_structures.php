<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('maintenance_types')) {
            Schema::table('maintenance_types', function (Blueprint $table) {
                if (!Schema::hasColumn('maintenance_types', 'es_preventivo')) {
                    $table->boolean('es_preventivo')->default(false)->after('maintenance_form_type');
                }
            });
        }

        if (!Schema::hasTable('driver_incentive_reports')) {
            Schema::create('driver_incentive_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
                $table->unsignedSmallInteger('report_year');
                $table->unsignedTinyInteger('report_month');
                $table->unsignedTinyInteger('stars_start')->default(5);
                $table->unsignedTinyInteger('stars_end')->default(5);
                $table->unsignedInteger('non_preventive_requests')->default(0);
                $table->unsignedInteger('preventive_requests')->default(0);
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();

                $table->unique(['driver_id', 'report_year', 'report_month'], 'driver_incentive_reports_unique_period');
            });
        }

        if (!Schema::hasTable('workshops')) {
            Schema::create('workshops', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
                $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
                $table->foreignId('maintenance_appointment_id')->nullable()->constrained('maintenance_appointments')->nullOnDelete();
                $table->foreignId('maintenance_log_id')->nullable()->constrained('maintenance_logs')->nullOnDelete();
                $table->string('nombre_taller', 150);
                $table->date('fecha_ingreso');
                $table->date('fecha_salida')->nullable();
                $table->string('estado', 40)->default('Abierto');
                $table->text('pre_entrada_estado');
                $table->text('diagnostico')->nullable();
                $table->text('observaciones')->nullable();
                $table->timestamps();

                $table->index(['estado', 'fecha_ingreso']);
            });
        }

        if (!Schema::hasTable('workshop_part_changes')) {
            Schema::create('workshop_part_changes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
                $table->string('codigo_pieza_nueva', 120);
                $table->string('codigo_pieza_antigua', 120)->nullable();
                $table->string('descripcion', 255)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('workshop_part_changes')) {
            Schema::dropIfExists('workshop_part_changes');
        }

        if (Schema::hasTable('workshops')) {
            Schema::dropIfExists('workshops');
        }

        if (Schema::hasTable('driver_incentive_reports')) {
            Schema::dropIfExists('driver_incentive_reports');
        }

        if (Schema::hasTable('maintenance_types') && Schema::hasColumn('maintenance_types', 'es_preventivo')) {
            Schema::table('maintenance_types', function (Blueprint $table) {
                $table->dropColumn('es_preventivo');
            });
        }
    }
};
