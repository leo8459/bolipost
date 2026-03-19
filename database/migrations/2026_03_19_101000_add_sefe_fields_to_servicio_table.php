<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicio', function (Blueprint $table) {
            $table->string('actividadEconomica', 6)->nullable()->after('nombre_servicio');
            $table->string('codigoSin', 7)->nullable()->after('actividadEconomica');
            $table->string('codigo', 50)->nullable()->after('codigoSin');
            $table->string('descripcion', 500)->nullable()->after('codigo');
            $table->unsignedInteger('unidadMedida')->nullable()->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('servicio', function (Blueprint $table) {
            $table->dropColumn([
                'actividadEconomica',
                'codigoSin',
                'codigo',
                'descripcion',
                'unidadMedida',
            ]);
        });
    }
};
