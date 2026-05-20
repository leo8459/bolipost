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
            if (!Schema::hasColumn('maintenance_appointments', 'formulario_documento_path')) {
                $table->string('formulario_documento_path')->nullable()->after('evidencia_path');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('maintenance_appointments')) {
            return;
        }

        Schema::table('maintenance_appointments', function (Blueprint $table) {
            if (Schema::hasColumn('maintenance_appointments', 'formulario_documento_path')) {
                $table->dropColumn('formulario_documento_path');
            }
        });
    }
};
