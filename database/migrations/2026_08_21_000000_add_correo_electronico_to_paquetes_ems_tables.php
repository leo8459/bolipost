<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('paquetes_ems') && ! Schema::hasColumn('paquetes_ems', 'correo_electronico')) {
            Schema::table('paquetes_ems', function (Blueprint $table) {
                $table->string('correo_electronico')->nullable()->after('telefono_destinatario');
            });
        }

        if (Schema::hasTable('paquetes_ems_formulario') && ! Schema::hasColumn('paquetes_ems_formulario', 'correo_electronico')) {
            Schema::table('paquetes_ems_formulario', function (Blueprint $table) {
                $table->string('correo_electronico')->nullable()->after('telefono_destinatario');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('paquetes_ems_formulario') && Schema::hasColumn('paquetes_ems_formulario', 'correo_electronico')) {
            Schema::table('paquetes_ems_formulario', function (Blueprint $table) {
                $table->dropColumn('correo_electronico');
            });
        }

        if (Schema::hasTable('paquetes_ems') && Schema::hasColumn('paquetes_ems', 'correo_electronico')) {
            Schema::table('paquetes_ems', function (Blueprint $table) {
                $table->dropColumn('correo_electronico');
            });
        }
    }
};
