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
                'nombre' => 'POR COBRAR',
                'descripcion' => 'Servicio extra por cobrar',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre' => 'IDA Y VUELTA',
                'descripcion' => 'Servicio extra ida y vuelta',
                'created_at' => now(),
                'updated_at' => now(),
            ],
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

        if (Schema::hasTable('tarifario_tiktoker') && ! Schema::hasColumn('tarifario_tiktoker', 'servicio_extra_id')) {
            Schema::table('tarifario_tiktoker', function (Blueprint $table) {
                $table->foreignId('servicio_extra_id')
                    ->nullable()
                    ->constrained('servicio_extras')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tarifario_tiktoker') && Schema::hasColumn('tarifario_tiktoker', 'servicio_extra_id')) {
            Schema::table('tarifario_tiktoker', function (Blueprint $table) {
                $table->dropForeign(['servicio_extra_id']);
                $table->dropColumn('servicio_extra_id');
            });
        }
    }
};
