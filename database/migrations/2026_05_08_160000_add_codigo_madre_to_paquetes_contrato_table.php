<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paquetes_contrato', function (Blueprint $table) {
            if (! Schema::hasColumn('paquetes_contrato', 'codigo_madre')) {
                $table->string('codigo_madre')->nullable()->after('codigo')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('paquetes_contrato', function (Blueprint $table) {
            if (Schema::hasColumn('paquetes_contrato', 'codigo_madre')) {
                $table->dropColumn('codigo_madre');
            }
        });
    }
};
