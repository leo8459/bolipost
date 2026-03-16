<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('bitacoras', 'paquetes_ordi_id')) {
            Schema::table('bitacoras', function (Blueprint $table) {
                $table->foreignId('paquetes_ordi_id')
                    ->nullable()
                    ->after('paquetes_contrato_id')
                    ->constrained('paquetes_ordi')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('bitacoras', 'paquetes_certi_id')) {
            Schema::table('bitacoras', function (Blueprint $table) {
                $table->foreignId('paquetes_certi_id')
                    ->nullable()
                    ->after('paquetes_ordi_id')
                    ->constrained('paquetes_certi')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bitacoras', 'paquetes_certi_id')) {
            Schema::table('bitacoras', function (Blueprint $table) {
                $table->dropConstrainedForeignId('paquetes_certi_id');
            });
        }

        if (Schema::hasColumn('bitacoras', 'paquetes_ordi_id')) {
            Schema::table('bitacoras', function (Blueprint $table) {
                $table->dropConstrainedForeignId('paquetes_ordi_id');
            });
        }
    }
};
