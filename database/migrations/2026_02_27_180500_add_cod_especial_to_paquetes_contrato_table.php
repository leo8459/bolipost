<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('paquetes_contrato', 'cod_especial')) {
            Schema::table('paquetes_contrato', function (Blueprint $table) {
                $table->string('cod_especial', 50)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('paquetes_contrato', 'cod_especial')) {
            Schema::table('paquetes_contrato', function (Blueprint $table) {
                $table->dropColumn('cod_especial');
            });
        }
    }
};

