<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('paquetes_contrato')) {
            return;
        }

        Schema::table('paquetes_contrato', function (Blueprint $table) {
            if (!Schema::hasColumn('paquetes_contrato', 'provincia_origen')) {
                $table->string('provincia_origen')->nullable()->after('origen');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('paquetes_contrato')) {
            return;
        }

        Schema::table('paquetes_contrato', function (Blueprint $table) {
            if (Schema::hasColumn('paquetes_contrato', 'provincia_origen')) {
                $table->dropColumn('provincia_origen');
            }
        });
    }
};
