<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('solicitud_clientes') && ! Schema::hasColumn('solicitud_clientes', 'tarifario_tiktoker_id')) {
            Schema::table('solicitud_clientes', function (Blueprint $table) {
                $table->foreignId('tarifario_tiktoker_id')
                    ->nullable()
                    ->constrained('tarifario_tiktoker')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            });
        }

        if (
            Schema::hasTable('tarifario_tiktoker')
            && Schema::hasColumn('tarifario_tiktoker', 'solicitud_cliente_id')
            && Schema::hasColumn('solicitud_clientes', 'tarifario_tiktoker_id')
        ) {
            DB::statement('
                UPDATE solicitud_clientes
                SET tarifario_tiktoker_id = tarifario_tiktoker.id
                FROM tarifario_tiktoker
                WHERE solicitud_clientes.id = tarifario_tiktoker.solicitud_cliente_id
            ');

            Schema::table('tarifario_tiktoker', function (Blueprint $table) {
                $table->dropForeign(['solicitud_cliente_id']);
                $table->dropColumn('solicitud_cliente_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tarifario_tiktoker') && ! Schema::hasColumn('tarifario_tiktoker', 'solicitud_cliente_id')) {
            Schema::table('tarifario_tiktoker', function (Blueprint $table) {
                $table->foreignId('solicitud_cliente_id')
                    ->nullable()
                    ->constrained('solicitud_clientes')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            });
        }

        if (
            Schema::hasTable('solicitud_clientes')
            && Schema::hasColumn('solicitud_clientes', 'tarifario_tiktoker_id')
            && Schema::hasColumn('tarifario_tiktoker', 'solicitud_cliente_id')
        ) {
            DB::statement('
                UPDATE tarifario_tiktoker
                SET solicitud_cliente_id = solicitud_clientes.id
                FROM solicitud_clientes
                WHERE solicitud_clientes.tarifario_tiktoker_id = tarifario_tiktoker.id
            ');

            Schema::table('solicitud_clientes', function (Blueprint $table) {
                $table->dropForeign(['tarifario_tiktoker_id']);
                $table->dropColumn('tarifario_tiktoker_id');
            });
        }
    }
};
