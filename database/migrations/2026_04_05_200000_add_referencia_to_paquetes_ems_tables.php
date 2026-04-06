<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('paquetes_ems') && ! Schema::hasColumn('paquetes_ems', 'referencia')) {
            Schema::table('paquetes_ems', function (Blueprint $table) {
                $table->string('referencia')->nullable()->after('direccion');
            });
        }

        if (Schema::hasTable('paquetes_ems_formulario') && ! Schema::hasColumn('paquetes_ems_formulario', 'referencia')) {
            Schema::table('paquetes_ems_formulario', function (Blueprint $table) {
                $table->string('referencia')->nullable()->after('direccion');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('paquetes_ems_formulario') && Schema::hasColumn('paquetes_ems_formulario', 'referencia')) {
            Schema::table('paquetes_ems_formulario', function (Blueprint $table) {
                $table->dropColumn('referencia');
            });
        }

        if (Schema::hasTable('paquetes_ems') && Schema::hasColumn('paquetes_ems', 'referencia')) {
            Schema::table('paquetes_ems', function (Blueprint $table) {
                $table->dropColumn('referencia');
            });
        }
    }
};
