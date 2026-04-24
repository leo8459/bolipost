<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tarifa_contrato')) {
            return;
        }

        Schema::table('tarifa_contrato', function (Blueprint $table) {
            if (!Schema::hasColumn('tarifa_contrato', 'provincia_origen')) {
                $table->string('provincia_origen')->nullable()->after('provincia');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tarifa_contrato')) {
            return;
        }

        Schema::table('tarifa_contrato', function (Blueprint $table) {
            if (Schema::hasColumn('tarifa_contrato', 'provincia_origen')) {
                $table->dropColumn('provincia_origen');
            }
        });
    }
};

