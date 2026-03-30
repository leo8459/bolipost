<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('preregistros')
            && Schema::hasColumn('preregistros', 'tarifa_estimada')
            && !Schema::hasColumn('preregistros', 'precio')) {
            DB::statement('ALTER TABLE preregistros RENAME COLUMN tarifa_estimada TO precio');
        }

        if (Schema::hasTable('solicitud_clientes')
            && Schema::hasColumn('solicitud_clientes', 'tarifa_estimada')
            && !Schema::hasColumn('solicitud_clientes', 'precio')) {
            DB::statement('ALTER TABLE solicitud_clientes RENAME COLUMN tarifa_estimada TO precio');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('preregistros')
            && Schema::hasColumn('preregistros', 'precio')
            && !Schema::hasColumn('preregistros', 'tarifa_estimada')) {
            DB::statement('ALTER TABLE preregistros RENAME COLUMN precio TO tarifa_estimada');
        }

        if (Schema::hasTable('solicitud_clientes')
            && Schema::hasColumn('solicitud_clientes', 'precio')
            && !Schema::hasColumn('solicitud_clientes', 'tarifa_estimada')) {
            DB::statement('ALTER TABLE solicitud_clientes RENAME COLUMN precio TO tarifa_estimada');
        }
    }
};
