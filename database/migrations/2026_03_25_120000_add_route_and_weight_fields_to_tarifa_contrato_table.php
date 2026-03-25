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
            if (!Schema::hasColumn('tarifa_contrato', 'direccion1')) {
                $table->string('direccion1')->nullable()->after('servicio');
            }

            if (!Schema::hasColumn('tarifa_contrato', 'direccion2')) {
                $table->string('direccion2')->nullable()->after('direccion1');
            }

            if (!Schema::hasColumn('tarifa_contrato', 'zona1')) {
                $table->string('zona1')->nullable()->after('direccion2');
            }

            if (!Schema::hasColumn('tarifa_contrato', 'zona2')) {
                $table->string('zona2')->nullable()->after('zona1');
            }

            if (!Schema::hasColumn('tarifa_contrato', 'peso1')) {
                $table->decimal('peso1', 10, 2)->nullable()->after('zona2');
            }

            if (!Schema::hasColumn('tarifa_contrato', 'peso2')) {
                $table->decimal('peso2', 10, 2)->nullable()->after('peso1');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tarifa_contrato')) {
            return;
        }

        Schema::table('tarifa_contrato', function (Blueprint $table) {
            $columns = [];

            foreach (['direccion1', 'direccion2', 'zona1', 'zona2', 'peso1', 'peso2'] as $column) {
                if (Schema::hasColumn('tarifa_contrato', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
