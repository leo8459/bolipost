<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('cartero', 'id_paquetes_ordi')) {
            Schema::table('cartero', function (Blueprint $table) {
                $table->unsignedBigInteger('id_paquetes_ordi')->nullable()->after('id_paquetes_certi');
                $table->foreign('id_paquetes_ordi')
                    ->references('id')
                    ->on('paquetes_ordi')
                    ->nullOnDelete();
                $table->unique('id_paquetes_ordi');
            });
        }

        if (!Schema::hasColumn('paquetes_certi', 'imagen')) {
            Schema::table('paquetes_certi', function (Blueprint $table) {
                $table->string('imagen')->nullable()->after('fk_ventanilla');
            });
        }

        if (!Schema::hasColumn('paquetes_ordi', 'imagen')) {
            Schema::table('paquetes_ordi', function (Blueprint $table) {
                $table->string('imagen')->nullable()->after('fk_estado');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cartero', 'id_paquetes_ordi')) {
            Schema::table('cartero', function (Blueprint $table) {
                $table->dropUnique(['id_paquetes_ordi']);
                $table->dropForeign(['id_paquetes_ordi']);
                $table->dropColumn('id_paquetes_ordi');
            });
        }

        if (Schema::hasColumn('paquetes_certi', 'imagen')) {
            Schema::table('paquetes_certi', function (Blueprint $table) {
                $table->dropColumn('imagen');
            });
        }

        if (Schema::hasColumn('paquetes_ordi', 'imagen')) {
            Schema::table('paquetes_ordi', function (Blueprint $table) {
                $table->dropColumn('imagen');
            });
        }
    }
};
