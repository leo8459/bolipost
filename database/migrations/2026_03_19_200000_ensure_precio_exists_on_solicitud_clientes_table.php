<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('solicitud_clientes')) {
            return;
        }

        if (Schema::hasColumn('solicitud_clientes', 'precio')) {
            return;
        }

        if (Schema::hasColumn('solicitud_clientes', 'tarifa_estimada')) {
            DB::statement('ALTER TABLE solicitud_clientes RENAME COLUMN tarifa_estimada TO precio');
            return;
        }

        Schema::table('solicitud_clientes', function (Blueprint $table) {
            $table->decimal('precio', 10, 2)->nullable()->after('peso');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('solicitud_clientes')) {
            return;
        }

        if (Schema::hasColumn('solicitud_clientes', 'precio') && !Schema::hasColumn('solicitud_clientes', 'tarifa_estimada')) {
            DB::statement('ALTER TABLE solicitud_clientes RENAME COLUMN precio TO tarifa_estimada');
        }
    }
};
