<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paquetes_ems', function (Blueprint $table) {
            $table->string('direccion')->nullable()->after('telefono_destinatario');
        });

        Schema::table('paquetes_ems_formulario', function (Blueprint $table) {
            $table->string('direccion')->nullable()->after('telefono_destinatario');
        });
    }

    public function down(): void
    {
        Schema::table('paquetes_ems_formulario', function (Blueprint $table) {
            $table->dropColumn('direccion');
        });

        Schema::table('paquetes_ems', function (Blueprint $table) {
            $table->dropColumn('direccion');
        });
    }
};
