<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('malencaminados', function (Blueprint $table) {
            if (! Schema::hasColumn('malencaminados', 'paquetes_certi_id')) {
                $table->foreignId('paquetes_certi_id')
                    ->nullable()
                    ->after('paquetes_contrato_id')
                    ->constrained('paquetes_certi')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('malencaminados', 'paquetes_ordi_id')) {
                $table->foreignId('paquetes_ordi_id')
                    ->nullable()
                    ->after('paquetes_certi_id')
                    ->constrained('paquetes_ordi')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('malencaminados', function (Blueprint $table) {
            if (Schema::hasColumn('malencaminados', 'paquetes_ordi_id')) {
                $table->dropForeign(['paquetes_ordi_id']);
                $table->dropColumn('paquetes_ordi_id');
            }

            if (Schema::hasColumn('malencaminados', 'paquetes_certi_id')) {
                $table->dropForeign(['paquetes_certi_id']);
                $table->dropColumn('paquetes_certi_id');
            }
        });
    }
};

