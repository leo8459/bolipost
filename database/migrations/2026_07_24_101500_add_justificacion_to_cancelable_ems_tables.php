<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['paquetes_ems', 'paquetes_contrato', 'solicitud_clientes'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'justificacion')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->text('justificacion')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['paquetes_ems', 'solicitud_clientes'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'justificacion')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('justificacion');
                });
            }
        }
    }
};
