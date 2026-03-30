<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tarifa_contrato')) {
            return;
        }

        if (Schema::hasColumn('tarifa_contrato', 'direccion1') && !Schema::hasColumn('tarifa_contrato', 'direccion')) {
            Schema::table('tarifa_contrato', function (Blueprint $table) {
                $table->renameColumn('direccion1', 'direccion');
            });
        }

        if (Schema::hasColumn('tarifa_contrato', 'zona1') && !Schema::hasColumn('tarifa_contrato', 'zona')) {
            Schema::table('tarifa_contrato', function (Blueprint $table) {
                $table->renameColumn('zona1', 'zona');
            });
        }

        if (Schema::hasColumn('tarifa_contrato', 'peso1') && !Schema::hasColumn('tarifa_contrato', 'peso')) {
            Schema::table('tarifa_contrato', function (Blueprint $table) {
                $table->renameColumn('peso1', 'peso');
            });
        }

        if (Schema::hasColumn('tarifa_contrato', 'direccion') && Schema::hasColumn('tarifa_contrato', 'direccion2')) {
            DB::statement("UPDATE tarifa_contrato SET direccion = COALESCE(NULLIF(direccion, ''), NULLIF(direccion2, ''))");
        }

        if (Schema::hasColumn('tarifa_contrato', 'zona') && Schema::hasColumn('tarifa_contrato', 'zona2')) {
            DB::statement("UPDATE tarifa_contrato SET zona = COALESCE(NULLIF(zona, ''), NULLIF(zona2, ''))");
        }

        if (Schema::hasColumn('tarifa_contrato', 'peso') && Schema::hasColumn('tarifa_contrato', 'peso2')) {
            DB::statement('UPDATE tarifa_contrato SET peso = COALESCE(peso, peso2)');
        }

        Schema::table('tarifa_contrato', function (Blueprint $table) {
            $dropColumns = [];

            foreach (['direccion2', 'zona2', 'peso2'] as $column) {
                if (Schema::hasColumn('tarifa_contrato', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tarifa_contrato')) {
            return;
        }

        Schema::table('tarifa_contrato', function (Blueprint $table) {
            if (!Schema::hasColumn('tarifa_contrato', 'direccion2')) {
                $table->string('direccion2')->nullable()->after('direccion');
            }

            if (!Schema::hasColumn('tarifa_contrato', 'zona2')) {
                $table->string('zona2')->nullable()->after('zona');
            }

            if (!Schema::hasColumn('tarifa_contrato', 'peso2')) {
                $table->decimal('peso2', 10, 2)->nullable()->after('peso');
            }
        });

        if (Schema::hasColumn('tarifa_contrato', 'direccion') && !Schema::hasColumn('tarifa_contrato', 'direccion1')) {
            Schema::table('tarifa_contrato', function (Blueprint $table) {
                $table->renameColumn('direccion', 'direccion1');
            });
        }

        if (Schema::hasColumn('tarifa_contrato', 'zona') && !Schema::hasColumn('tarifa_contrato', 'zona1')) {
            Schema::table('tarifa_contrato', function (Blueprint $table) {
                $table->renameColumn('zona', 'zona1');
            });
        }

        if (Schema::hasColumn('tarifa_contrato', 'peso') && !Schema::hasColumn('tarifa_contrato', 'peso1')) {
            Schema::table('tarifa_contrato', function (Blueprint $table) {
                $table->renameColumn('peso', 'peso1');
            });
        }
    }
};
