<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardChartVolumeTest extends TestCase
{
    public function test_chart_filters_all_operational_measures_by_months_and_departments(): void
    {
        Schema::create('estados', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_estado');
        });
        Schema::create('empresa', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
        });
        Schema::create('paquetes_ems', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->string('origen')->nullable();
            $table->string('ciudad');
            $table->decimal('peso', 10, 3);
            $table->unsignedBigInteger('estado_id');
            $table->timestamp('created_at');
        });
        Schema::create('paquetes_contrato', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->string('origen')->nullable();
            $table->string('destino');
            $table->decimal('peso', 10, 3);
            $table->unsignedBigInteger('estados_id');
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at');
        });

        DB::table('estados')->insert([
            ['id' => 1, 'nombre_estado' => 'ENTREGADO'],
            ['id' => 2, 'nombre_estado' => 'CANCELADO'],
            ['id' => 3, 'nombre_estado' => 'ALMACEN'],
            ['id' => 4, 'nombre_estado' => 'EN TRANSITO'],
        ]);
        DB::table('empresa')->insert(['id' => 1, 'nombre' => 'Cliente Prueba']);
        DB::table('paquetes_ems')->insert([
            ['codigo' => 'EMS-LP', 'origen' => 'ORURO', 'ciudad' => 'LA PAZ', 'peso' => 1.5, 'estado_id' => 1, 'created_at' => '2026-07-10 10:00:00'],
            ['codigo' => 'EMS-OR', 'origen' => 'LA PAZ', 'ciudad' => 'ORURO', 'peso' => 2, 'estado_id' => 4, 'created_at' => '2026-08-10 10:00:00'],
            ['codigo' => 'EMS-SC', 'origen' => 'SANTA CRUZ', 'ciudad' => 'LA PAZ', 'peso' => 3, 'estado_id' => 4, 'created_at' => '2026-08-10 10:00:00'],
            ['codigo' => 'EMS-SEP', 'origen' => 'ORURO', 'ciudad' => 'LA PAZ', 'peso' => 4, 'estado_id' => 4, 'created_at' => '2026-09-10 10:00:00'],
            ['codigo' => 'EMS-CANCEL', 'origen' => 'ORURO', 'ciudad' => 'LA PAZ', 'peso' => 5, 'estado_id' => 2, 'created_at' => '2026-07-10 10:00:00'],
            ['codigo' => 'EMS-NO-ORIGIN', 'origen' => null, 'ciudad' => 'LA PAZ', 'peso' => 0.75, 'estado_id' => 1, 'created_at' => '2026-07-10 10:00:00'],
            ['codigo' => 'EMS-CH', 'origen' => 'CHUQUISACA', 'ciudad' => 'LA PAZ', 'peso' => 1, 'estado_id' => 4, 'created_at' => '2026-07-10 10:00:00'],
        ]);
        DB::table('paquetes_contrato')->insert([
            ['codigo' => 'CON-LP', 'origen' => 'ORURO', 'destino' => 'LA PAZ', 'peso' => 1.25, 'estados_id' => 1, 'created_at' => '2026-07-11 10:00:00'],
            ['codigo' => 'CON-OR', 'origen' => 'LA PAZ', 'destino' => 'ORURO', 'peso' => 2.5, 'estados_id' => 4, 'created_at' => '2026-08-11 10:00:00'],
            ['codigo' => 'CON-SC', 'origen' => 'SANTA CRUZ', 'destino' => 'LA PAZ', 'peso' => 3, 'estados_id' => 4, 'created_at' => '2026-08-11 10:00:00'],
        ]);
        DB::table('paquetes_contrato')->insert(['codigo' => 'CON-TEST', 'origen' => 'ORURO', 'destino' => 'LA PAZ', 'peso' => 8, 'estados_id' => 4, 'empresa_id' => 1, 'created_at' => '2026-08-11 10:00:00']);

        $route = 'dashboard.chart-volume-data';
        $this->withoutMiddleware()->getJson(route($route, [
            'anio' => 2026,
            'meses' => [7, 8],
            'departamentos' => ['LA PAZ', 'ORURO'],
        ]))
            ->assertOk()
            ->assertJsonPath('labels', ['LA PAZ', 'ORURO'])
            ->assertJsonPath('registrados', [2, 2])
            ->assertJsonPath('peso', [4.5, 2.75])
            ->assertJsonPath('entregas', [0, 2])
            ->assertJsonPath('totales.registrados', 4)
            ->assertJsonPath('totales.peso', 7.25)
            ->assertJsonPath('totales.entregas', 2);

        $this->withoutMiddleware()->getJson(route($route, [
            'anio' => 2026,
            'meses' => [7, 8],
            'departamentos' => ['LA PAZ', 'COCHABAMBA', 'SANTA CRUZ', 'ORURO', 'POTOSI', 'TARIJA', 'SUCRE', 'TRINIDAD', 'COBIJA'],
        ]))
            ->assertOk()
            ->assertJsonPath('labels', ['COBIJA', 'COCHABAMBA', 'LA PAZ', 'ORURO', 'POTOSI', 'SANTA CRUZ', 'SUCRE', 'TARIJA', 'TRINIDAD', 'SIN ORIGEN ASIGNADO'])
            ->assertJsonPath('registrados.6', 1)
            ->assertJsonPath('totales.registrados', 8)
            ->assertJsonPath('totales.peso', 15);

        $this->withoutMiddleware()->getJson(route($route, [
            'anio' => 2026,
            'meses' => [7, 9],
            'departamentos' => ['ORURO'],
        ]))
            ->assertOk()
            ->assertJsonPath('registrados', [3])
            ->assertJsonPath('totales.peso', 6.75)
            ->assertJsonPath('totales.entregas', 2);
    }
}
