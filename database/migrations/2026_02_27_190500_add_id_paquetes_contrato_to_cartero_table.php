<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cartero', function (Blueprint $table) {
            if (!Schema::hasColumn('cartero', 'id_paquetes_contrato')) {
                $table->unsignedBigInteger('id_paquetes_contrato')->nullable()->after('id_paquetes_certi');
                $table->foreign('id_paquetes_contrato')
                    ->references('id')
                    ->on('paquetes_contrato')
                    ->nullOnDelete();
                $table->unique('id_paquetes_contrato');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cartero', function (Blueprint $table) {
            if (Schema::hasColumn('cartero', 'id_paquetes_contrato')) {
                $table->dropUnique(['id_paquetes_contrato']);
                $table->dropForeign(['id_paquetes_contrato']);
                $table->dropColumn('id_paquetes_contrato');
            }
        });
    }
};

