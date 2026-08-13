<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('preregistros') || Schema::hasColumn('preregistros', 'precio')) {
            return;
        }

        if (Schema::hasColumn('preregistros', 'tarifa_estimada')) {
            DB::statement('ALTER TABLE preregistros RENAME COLUMN tarifa_estimada TO precio');

            return;
        }

        Schema::table('preregistros', function (Blueprint $table) {
            $table->decimal('precio', 10, 2)->nullable()->after('peso');
        });
    }

    public function down(): void
    {
        if (
            Schema::hasTable('preregistros')
            && Schema::hasColumn('preregistros', 'precio')
            && ! Schema::hasColumn('preregistros', 'tarifa_estimada')
        ) {
            DB::statement('ALTER TABLE preregistros RENAME COLUMN precio TO tarifa_estimada');
        }
    }
};
