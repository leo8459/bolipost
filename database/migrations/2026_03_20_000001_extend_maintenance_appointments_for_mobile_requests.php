<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('maintenance_appointments')) {
            return;
        }

        Schema::table('maintenance_appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('maintenance_appointments', 'requested_by_user_id')) {
                $table->foreignId('requested_by_user_id')
                    ->nullable()
                    ->after('driver_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('maintenance_appointments', 'solicitud_fecha')) {
                $table->dateTime('solicitud_fecha')->nullable()->after('fecha_programada');
            }

            if (!Schema::hasColumn('maintenance_appointments', 'origen_solicitud')) {
                $table->string('origen_solicitud', 40)->default('web')->after('solicitud_fecha');
            }

            if (!Schema::hasColumn('maintenance_appointments', 'evidencia_path')) {
                $table->string('evidencia_path')->nullable()->after('descripcion_problema');
            }
        });
    }

    public function down(): void
    {
        // Sin rollback destructivo.
    }
};
