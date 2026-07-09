<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('paquetes_int') || Schema::hasColumn('paquetes_int', 'estado_id')) {
            return;
        }

        Schema::table('paquetes_int', function (Blueprint $table) {
            $table->unsignedBigInteger('estado_id')->nullable()->after('servicio_id');
            $table->foreign('estado_id')->references('id')->on('estados')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('paquetes_int') || !Schema::hasColumn('paquetes_int', 'estado_id')) {
            return;
        }

        Schema::table('paquetes_int', function (Blueprint $table) {
            $table->dropForeign(['estado_id']);
            $table->dropColumn('estado_id');
        });
    }
};
