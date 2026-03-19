<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        $estadoPendienteId = DB::table('estados')
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['PENDIENTE'])
            ->value('id');

        if (! $estadoPendienteId) {
            $estadoPendienteId = DB::table('estados')->insertGetId([
                'nombre_estado' => 'PENDIENTE',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::statement("
            UPDATE solicitud_clientes sc
            SET estados_id = e.id
            FROM estados e
            WHERE sc.estados_id IS NULL
              AND trim(upper(coalesce(sc.estado, ''))) = trim(upper(e.nombre_estado))
        ");

        DB::table('solicitud_clientes')
            ->whereNull('estados_id')
            ->update(['estados_id' => (int) $estadoPendienteId]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('solicitud_clientes', 'estados_id')) {
            Schema::table('solicitud_clientes', function (Blueprint $table) {
                $table->dropForeign(['estados_id']);
                $table->dropColumn('estados_id');
            });
        }
    }
};
