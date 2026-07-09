<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('paquetes_int') || Schema::hasColumn('paquetes_int', 'tramo')) {
            return;
        }

        Schema::table('paquetes_int', function (Blueprint $table) {
            $table->string('tramo', 120)->nullable()->after('destino');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('paquetes_int') || !Schema::hasColumn('paquetes_int', 'tramo')) {
            return;
        }

        Schema::table('paquetes_int', function (Blueprint $table) {
            $table->dropColumn('tramo');
        });
    }
};
