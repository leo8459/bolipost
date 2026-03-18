<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('servicio_extras')->upsert([
            [
                'nombre' => 'serviciotiktokero',
                'descripcion' => 'Servicio puerta a puerta',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre' => 'serviciotiktokeroventanilla',
                'descripcion' => 'Recojo en ventanilla',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['nombre'], ['descripcion', 'updated_at']);

        if (Schema::hasTable('solicitud_clientes') && ! Schema::hasColumn('solicitud_clientes', 'servicio_extra_id')) {
            Schema::table('solicitud_clientes', function (Blueprint $table) {
                $table->foreignId('servicio_extra_id')
                    ->nullable()
                    ->constrained('servicio_extras')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('solicitud_clientes') && ! Schema::hasColumn('solicitud_clientes', 'direccion_recojo')) {
            Schema::table('solicitud_clientes', function (Blueprint $table) {
                $table->string('direccion_recojo')->nullable();
            });
        }

        if (Schema::hasTable('solicitud_clientes') && Schema::hasColumn('solicitud_clientes', 'servicio_id')) {
            DB::statement('ALTER TABLE solicitud_clientes ALTER COLUMN servicio_id DROP NOT NULL');
        }

        if (Schema::hasTable('solicitud_clientes') && Schema::hasColumn('solicitud_clientes', 'peso')) {
            DB::statement('ALTER TABLE solicitud_clientes ALTER COLUMN peso DROP NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('solicitud_clientes') && Schema::hasColumn('solicitud_clientes', 'servicio_extra_id')) {
            Schema::table('solicitud_clientes', function (Blueprint $table) {
                $table->dropForeign(['servicio_extra_id']);
                $table->dropColumn('servicio_extra_id');
            });
        }

        if (Schema::hasTable('solicitud_clientes') && Schema::hasColumn('solicitud_clientes', 'direccion_recojo')) {
            Schema::table('solicitud_clientes', function (Blueprint $table) {
                $table->dropColumn('direccion_recojo');
            });
        }
    }
};
