<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('paquetes_int') || Schema::hasColumn('paquetes_int', 'servicio_id')) {
            return;
        }

        Schema::table('paquetes_int', function (Blueprint $table) {
            $table->unsignedBigInteger('servicio_id')->nullable()->after('codigo');
            $table->foreign('servicio_id')->references('id')->on('servicio')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('paquetes_int') || !Schema::hasColumn('paquetes_int', 'servicio_id')) {
            return;
        }

        Schema::table('paquetes_int', function (Blueprint $table) {
            $table->dropForeign(['servicio_id']);
            $table->dropColumn('servicio_id');
        });
    }
};
