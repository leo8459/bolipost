<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('servicio_extras')) {
            Schema::create('servicio_extras', function (Blueprint $table) {
                $table->id();
                $table->string('nombre')->unique();
                $table->string('descripcion')->nullable();
                $table->timestamps();
            });
        }

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
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_extras');
    }
};
