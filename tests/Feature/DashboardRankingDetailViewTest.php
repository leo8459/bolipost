<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardRankingDetailViewTest extends TestCase
{
    public function test_department_detail_partial_keeps_transit_pending_and_delivery_modals(): void
    {
        $item = (object) [
            'puesto' => 4,
            'departamento' => 'ORURO',
            'total' => 3,
            'entregados' => 1,
            'transito' => 1,
            'pendientes' => 1,
            'cumplimiento' => 33.3,
            'top_entregador' => 'SIN DATOS',
            'transito_por_modulo' => ['EMS' => 1],
            'pendientes_por_modulo' => ['EMS' => 1],
            'entregados_por_modulo' => ['EMS' => 1],
            'transito_grupos' => [],
            'pendientes_grupos' => [],
            'entregados_detalle' => [],
        ];

        $html = view('dashboard.partials.department-modals', [
            'rankingDepartamentos' => collect([$item]),
            'rangoLabel' => 'Todo el tiempo',
        ])->render();

        $this->assertStringContainsString('id="departamentoTransitoModal4"', $html);
        $this->assertStringContainsString('id="departamentoPendientesModal4"', $html);
        $this->assertStringContainsString('id="departamentoDetalleModal4"', $html);
        $this->assertStringContainsString('data-transit-filter-panel', $html);
        $this->assertStringContainsString('data-pending-filter-panel', $html);
    }

    public function test_department_detail_endpoint_rejects_unknown_department_before_querying_data(): void
    {
        $this->withoutMiddleware()->get(route('dashboard.ranking-department-details', [
            'ranking_department' => 'DESCONOCIDO',
            'ranking_position' => 1,
        ]))->assertNotFound();
    }

    public function test_department_detail_endpoint_loads_transit_and_pending_for_only_selected_department(): void
    {
        Schema::create('estados', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_estado');
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('ciudad')->nullable();
        });
        Schema::create('paquetes_ems', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->string('origen');
            $table->string('ciudad');
            $table->string('nombre_destinatario')->nullable();
            $table->string('cod_especial')->nullable();
            $table->unsignedBigInteger('estado_id');
            $table->timestamp('created_at');
        });
        Schema::create('eventos_ems', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->unsignedBigInteger('evento_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at');
        });

        DB::table('estados')->insert([
            ['id' => 1, 'nombre_estado' => 'ENTREGADO'],
            ['id' => 2, 'nombre_estado' => 'CANCELADO'],
            ['id' => 3, 'nombre_estado' => 'TRANSITO'],
            ['id' => 4, 'nombre_estado' => 'ALMACEN'],
        ]);
        DB::table('paquetes_ems')->insert([
            ['codigo' => 'EMS-TRANSITO', 'origen' => 'ORURO', 'ciudad' => 'LA PAZ', 'cod_especial' => 'ORU-1', 'estado_id' => 3, 'created_at' => '2026-10-01 10:00:00'],
            ['codigo' => 'EMS-PENDIENTE', 'origen' => 'ORURO', 'ciudad' => 'ORURO', 'cod_especial' => null, 'estado_id' => 4, 'created_at' => '2026-10-01 11:00:00'],
            ['codigo' => 'EMS-CANCELADO', 'origen' => 'ORURO', 'ciudad' => 'ORURO', 'cod_especial' => null, 'estado_id' => 2, 'created_at' => '2026-10-01 12:00:00'],
            ['codigo' => 'EMS-OTRO', 'origen' => 'LA PAZ', 'ciudad' => 'LA PAZ', 'cod_especial' => null, 'estado_id' => 4, 'created_at' => '2026-10-01 13:00:00'],
        ]);
        DB::table('eventos_ems')->insert([
            ['codigo' => 'EMS-PENDIENTE', 'evento_id' => 295, 'created_at' => '2026-10-01 11:05:00'],
            ['codigo' => 'EMS-CANCELADO', 'evento_id' => 295, 'created_at' => '2026-10-01 12:05:00'],
        ]);

        $this->withoutMiddleware()->get(route('dashboard.ranking-department-details', [
            'ranking_department' => 'ORURO',
            'ranking_position' => 2,
            'modules' => ['ems'],
        ]))
            ->assertOk()
            ->assertSee('id="departamentoTransitoModal2"', false)
            ->assertSee('id="departamentoPendientesModal2"', false)
            ->assertSee('EMS-TRANSITO')
            ->assertSee('EMS-PENDIENTE')
            ->assertDontSee('EMS-CANCELADO')
            ->assertDontSee('EMS-OTRO');
    }
}
