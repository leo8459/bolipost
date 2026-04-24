<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paquetes_certi', function (Blueprint $table) {
            if (!Schema::hasColumn('paquetes_certi', 'servicio_id')) {
                $table->foreignId('servicio_id')
                    ->nullable()
                    ->after('cod_especial')
                    ->constrained('servicio')
                    ->nullOnDelete();
            }
        });

        Schema::table('paquetes_ordi', function (Blueprint $table) {
            if (!Schema::hasColumn('paquetes_ordi', 'servicio_id')) {
                $table->foreignId('servicio_id')
                    ->nullable()
                    ->after('codigo')
                    ->constrained('servicio')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('paquetes_certi', function (Blueprint $table) {
            if (Schema::hasColumn('paquetes_certi', 'servicio_id')) {
                $table->dropConstrainedForeignId('servicio_id');
            }
        });

        Schema::table('paquetes_ordi', function (Blueprint $table) {
            if (Schema::hasColumn('paquetes_ordi', 'servicio_id')) {
                $table->dropConstrainedForeignId('servicio_id');
            }
        });
    }
};

