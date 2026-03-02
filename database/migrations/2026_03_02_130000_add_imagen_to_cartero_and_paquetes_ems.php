<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('cartero', 'imagen')) {
            Schema::table('cartero', function (Blueprint $table) {
                $table->string('imagen')->nullable()->after('descripcion');
            });
        }

        if (Schema::hasColumn('cartero', 'foto') && Schema::hasColumn('cartero', 'imagen')) {
            DB::statement('UPDATE cartero SET imagen = foto WHERE imagen IS NULL AND foto IS NOT NULL');
        }

        if (!Schema::hasColumn('paquetes_ems', 'imagen')) {
            Schema::table('paquetes_ems', function (Blueprint $table) {
                $table->string('imagen')->nullable()->after('direccion');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cartero', 'imagen')) {
            Schema::table('cartero', function (Blueprint $table) {
                $table->dropColumn('imagen');
            });
        }

        if (Schema::hasColumn('paquetes_ems', 'imagen')) {
            Schema::table('paquetes_ems', function (Blueprint $table) {
                $table->dropColumn('imagen');
            });
        }
    }
};
