<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vehicles')) {
            return;
        }

        Schema::table('vehicles', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicles', 'tacometro_danado')) {
                $table->boolean('tacometro_danado')->default(false)->after('activo');
            }
        });

        DB::table('vehicles')
            ->whereNull('kilometraje_inicial')
            ->whereNull('kilometraje_actual')
            ->whereNull('kilometraje')
            ->update([
                'kilometraje_inicial' => 0,
                'kilometraje_actual' => 0,
                'kilometraje' => 0,
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('vehicles') || !Schema::hasColumn('vehicles', 'tacometro_danado')) {
            return;
        }

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('tacometro_danado');
        });
    }
};
