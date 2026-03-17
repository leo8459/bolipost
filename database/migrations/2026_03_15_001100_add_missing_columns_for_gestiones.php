<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vehicles')) {
            Schema::table('vehicles', function (Blueprint $table) {
                if (!Schema::hasColumn('vehicles', 'kilometraje_inicial')) {
                    $table->decimal('kilometraje_inicial', 10, 2)->nullable();
                }
                if (!Schema::hasColumn('vehicles', 'kilometraje_actual')) {
                    $table->decimal('kilometraje_actual', 10, 2)->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('gas_stations')) {
            Schema::table('gas_stations', function (Blueprint $table) {
                if (!Schema::hasColumn('gas_stations', 'nit_emisor')) {
                    $table->string('nit_emisor', 50)->nullable()->index();
                }
                if (!Schema::hasColumn('gas_stations', 'razon_social')) {
                    $table->string('razon_social', 255)->nullable();
                }
                if (!Schema::hasColumn('gas_stations', 'direccion')) {
                    $table->string('direccion', 255)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Migration de integracion: sin rollback destructivo.
    }
};
