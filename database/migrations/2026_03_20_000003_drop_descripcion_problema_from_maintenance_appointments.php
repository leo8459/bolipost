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
            if (Schema::hasColumn('maintenance_appointments', 'descripcion_problema')) {
                $table->dropColumn('descripcion_problema');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('maintenance_appointments')) {
            return;
        }

        Schema::table('maintenance_appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('maintenance_appointments', 'descripcion_problema')) {
                $table->text('descripcion_problema')->nullable()->after('es_accidente');
            }
        });
    }
};
