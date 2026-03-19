<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('solicitud_clientes', 'estado_id')) {
            Schema::table('solicitud_clientes', function (Blueprint $table) {
                $table->foreignId('estado_id')
                    ->nullable()
                    ->after('barcode')
                    ->constrained('estados')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            });
        }

        if (Schema::hasColumn('solicitud_clientes', 'estados_id')) {
            DB::statement('UPDATE solicitud_clientes SET estado_id = estados_id WHERE estado_id IS NULL AND estados_id IS NOT NULL');
        }

        if (Schema::hasColumn('solicitud_clientes', 'estado')) {
            DB::statement("
                UPDATE solicitud_clientes sc
                SET estado_id = e.id
                FROM estados e
                WHERE sc.estado_id IS NULL
                  AND trim(upper(coalesce(sc.estado, ''))) = trim(upper(e.nombre_estado))
            ");
        }

        if (Schema::hasColumn('solicitud_clientes', 'estados_id')) {
            Schema::table('solicitud_clientes', function (Blueprint $table) {
                $table->dropForeign(['estados_id']);
                $table->dropColumn('estados_id');
            });
        }

        if (Schema::hasColumn('solicitud_clientes', 'estado')) {
            Schema::table('solicitud_clientes', function (Blueprint $table) {
                $table->dropColumn('estado');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('solicitud_clientes', 'estado')) {
            Schema::table('solicitud_clientes', function (Blueprint $table) {
                $table->string('estado', 30)->nullable()->after('barcode');
            });
        }

        if (! Schema::hasColumn('solicitud_clientes', 'estados_id')) {
            Schema::table('solicitud_clientes', function (Blueprint $table) {
                $table->foreignId('estados_id')
                    ->nullable()
                    ->after('barcode')
                    ->constrained('estados')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            });
        }

        if (Schema::hasColumn('solicitud_clientes', 'estado_id')) {
            DB::statement("
                UPDATE solicitud_clientes sc
                SET estados_id = estado_id
                WHERE estado_id IS NOT NULL
            ");

            DB::statement("
                UPDATE solicitud_clientes sc
                SET estado = e.nombre_estado
                FROM estados e
                WHERE sc.estado_id = e.id
            ");

            Schema::table('solicitud_clientes', function (Blueprint $table) {
                $table->dropForeign(['estado_id']);
                $table->dropColumn('estado_id');
            });
        }
    }
};
