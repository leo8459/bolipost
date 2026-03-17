<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('paquetes_ems') || !Schema::hasTable('estados') || !Schema::hasColumn('paquetes_ems', 'estado_id')) {
            return;
        }

        $constraintExists = DB::table('information_schema.table_constraints')
            ->where('table_schema', 'public')
            ->where('table_name', 'paquetes_ems')
            ->where('constraint_type', 'FOREIGN KEY')
            ->where('constraint_name', 'paquetes_ems_estado_id_foreign')
            ->exists();

        if ($constraintExists) {
            return;
        }

        Schema::table('paquetes_ems', function (Blueprint $table) {
            $table->foreign('estado_id')
                ->references('id')
                ->on('estados');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('paquetes_ems') || !Schema::hasColumn('paquetes_ems', 'estado_id')) {
            return;
        }

        $constraintExists = DB::table('information_schema.table_constraints')
            ->where('table_schema', 'public')
            ->where('table_name', 'paquetes_ems')
            ->where('constraint_type', 'FOREIGN KEY')
            ->where('constraint_name', 'paquetes_ems_estado_id_foreign')
            ->exists();

        if (!$constraintExists) {
            return;
        }

        Schema::table('paquetes_ems', function (Blueprint $table) {
            $table->dropForeign('paquetes_ems_estado_id_foreign');
        });
    }
};

