<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paquetes_ems', function (Blueprint $table) {
<<<<<<< HEAD
            $table->foreignId('estado_id')
                ->nullable()
                ->constrained('estados')
                ->after('tarifario_id');
        });
=======
            if (!Schema::hasColumn('paquetes_ems', 'estado_id')) {
                $table->foreignId('estado_id')
                    ->nullable()
                    ->after('tarifario_id');
            }
        });

        if (Schema::hasTable('estados')) {
            Schema::table('paquetes_ems', function (Blueprint $table) {
                $table->foreign('estado_id')
                    ->references('id')
                    ->on('estados');
            });
        }
>>>>>>> a41ccfb (Uchazara)
    }

    public function down(): void
    {
        Schema::table('paquetes_ems', function (Blueprint $table) {
<<<<<<< HEAD
            $table->dropForeign(['estado_id']);
            $table->dropColumn('estado_id');
=======
            if (Schema::hasColumn('paquetes_ems', 'estado_id')) {
                $table->dropForeign(['estado_id']);
                $table->dropColumn('estado_id');
            }
>>>>>>> a41ccfb (Uchazara)
        });
    }
};
