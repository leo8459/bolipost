<?php

namespace Tests\Feature;

use App\Http\Controllers\CarterosController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TodosPaquetesController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class CarterosAssignedStateConsistencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();

        DB::table('estados')->insert([
            ['id' => 13, 'nombre_estado' => 'CARTERO'],
            ['id' => 27, 'nombre_estado' => 'ENTREGADO'],
        ]);
        DB::table('users')->insert([
            'id' => 38,
            'name' => 'Cartero de prueba',
            'ciudad' => 'LA PAZ',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([
            'eventos_ems', 'eventos_certi', 'eventos_ordi', 'eventos_contrato', 'eventos_tiktoker', 'eventos',
            'cartero',
            'solicitud_clientes',
            'paquetes_contrato',
            'paquetes_ordi',
            'paquetes_certi',
            'paquetes_ems',
            'users',
            'estados',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_assigned_list_excludes_delivered_package_with_stale_cartero_assignment(): void
    {
        DB::table('paquetes_contrato')->insert($this->contractRow(36814, 'C0030A00397BO', 27));
        DB::table('cartero')->insert($this->assignmentRow(29138, 36814, 13));

        $response = $this->combinedDataResponse(13);

        $this->assertSame(0, $response->getData(true)['meta']['total']);
        $this->assertSame([], $response->getData(true)['data']);
    }

    public function test_dashboard_excludes_stale_and_orphaned_cartero_assignments(): void
    {
        DB::table('paquetes_contrato')->insert([
            $this->contractRow(36814, 'CONTRATO-ACTIVO', 13),
            $this->contractRow(36815, 'CONTRATO-DESFASADO', 27),
        ]);
        DB::table('cartero')->insert([
            $this->assignmentRow(29138, 36814, 13),
            $this->assignmentRow(29139, 36815, 13),
            $this->assignmentRow(29140, null, 13),
        ]);
        $user = new User();
        $user->forceFill(['id' => 38, 'name' => 'Cartero de prueba', 'ciudad' => 'LA PAZ']);

        $method = new ReflectionMethod(DashboardController::class, 'buildCarteroPendingSummary');
        $summary = $method->invoke(
            app(DashboardController::class),
            $user,
            ['administrador'],
            true,
            'LA PAZ',
        );

        $laPaz = $summary['departments']->firstWhere('department', 'LA PAZ');

        $this->assertTrue($summary['enabled']);
        $this->assertSame(1, $laPaz->total_pendientes);
        $this->assertSame('CONTRATO-ACTIVO', $laPaz->rows->first()->detalle->first()->codigo);
        $this->assertSame('sin_datos', $laPaz->rows->first()->detalle->first()->situacion);
        $this->assertTrue($laPaz->rows->first()->detalle->first()->assignment_estimated);
    }

    public function test_pending_details_use_latest_assignment_and_operational_start_for_overdue_days(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-17 12:00:00'));
        try {
            DB::table('paquetes_contrato')->insert($this->contractRow(1, 'GUIA-MORA', 13));
            DB::table('cartero')->insert($this->assignmentRow(1, 1, 13));
            DB::table('eventos')->insert([
                ['id' => 295, 'nombre_evento' => 'Recojo'],
                ['id' => 900, 'nombre_evento' => \App\Support\CarteroEvent::ASIGNADO],
                ['id' => 901, 'nombre_evento' => \App\Support\CarteroEvent::CAMBIADO],
            ]);
            DB::table('eventos_contrato')->insert([
                ['codigo' => 'GUIA-MORA', 'evento_id' => 295, 'created_at' => now()->subDays(10)],
                ['codigo' => 'GUIA-MORA', 'evento_id' => 900, 'created_at' => now()->subDays(5)],
                ['codigo' => 'GUIA-MORA', 'evento_id' => 901, 'created_at' => now()->subDays(2)],
            ]);
            $method = new ReflectionMethod(DashboardController::class, 'buildCarteroPendingDetails');
            $detail = $method->invoke(app(DashboardController::class), [38], 13)->get(38)->first();
            $this->assertSame(now()->subDays(2)->toDateTimeString(), $detail->assigned_at);
            $this->assertFalse($detail->assignment_estimated);
            $this->assertSame('rezago', $detail->situacion);
            $this->assertEquals(9, $detail->dias_atraso);
            $this->assertEquals(8, $detail->dias_rezago);
            $this->assertTrue($method->invoke(app(DashboardController::class), [99], 13)->isEmpty());
        } finally {
            $this->travelBack();
        }
    }

    public function test_changing_package_state_from_all_packages_synchronizes_cartero_assignment(): void
    {
        DB::table('paquetes_contrato')->insert($this->contractRow(36815, 'CONTRATO-002', 13));
        DB::table('cartero')->insert($this->assignmentRow(29139, 36815, 13));

        $request = Request::create('/todos-paquetes/contrato/36815/estado', 'PATCH', [
            'estado_id' => 27,
        ]);

        (new TodosPaquetesController())->updateEstado($request, 'contrato', 36815);

        $this->assertSame(27, (int) DB::table('paquetes_contrato')->where('id', 36815)->value('estados_id'));
        $this->assertSame(27, (int) DB::table('cartero')->where('id', 29139)->value('id_estados'));
    }

    public function test_assigned_city_filter_always_contains_the_nine_department_capitals(): void
    {
        $method = new ReflectionMethod(CarterosController::class, 'assignedPackageDestinationCities');
        $method->setAccessible(true);

        $cities = $method->invoke(new CarterosController())->pluck('value')->all();

        $this->assertSame([
            'COCHABAMBA',
            'LA PAZ',
            'ORURO',
            'POTOSI',
            'SANTA CRUZ',
            'SUCRE',
            'TARIJA',
            'TRINIDAD',
            'COBIJA',
        ], $cities);
    }

    private function combinedDataResponse(int $estadoId)
    {
        $method = new ReflectionMethod(CarterosController::class, 'combinedDataResponse');
        $method->setAccessible(true);

        return $method->invoke(
            new CarterosController(),
            Request::create('/api/carteros/asignados', 'GET'),
            $estadoId,
            null,
            true,
        );
    }

    private function contractRow(int $id, string $codigo, int $estadoId): array
    {
        return [
            'id' => $id,
            'codigo' => $codigo,
            'cod_especial' => null,
            'origen' => 'COCHABAMBA',
            'nombre_d' => 'Destinatario',
            'telefono_d' => '70000000',
            'destino' => 'LA PAZ',
            'direccion_d' => 'Direccion',
            'peso' => 1,
            'estados_id' => $estadoId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function assignmentRow(int $id, ?int $packageId, int $estadoId): array
    {
        return [
            'id' => $id,
            'id_paquetes_contrato' => $packageId,
            'id_estados' => $estadoId,
            'id_user' => 38,
            'intento' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function createTables(): void
    {
        Schema::create('eventos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_evento');
        });
        foreach (['eventos_ems', 'eventos_certi', 'eventos_ordi', 'eventos_contrato', 'eventos_tiktoker'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->string('codigo');
                $table->unsignedBigInteger('evento_id');
                $table->timestamps();
            });
        }
        Schema::create('estados', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_estado');
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('ciudad')->nullable();
        });
        Schema::create('paquetes_ems', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->string('origen')->nullable();
            $table->string('nombre_destinatario')->nullable();
            $table->string('telefono_destinatario')->nullable();
            $table->string('direccion')->nullable();
            $table->string('ciudad')->nullable();
            $table->decimal('peso')->nullable();
            $table->decimal('precio')->nullable();
            $table->unsignedBigInteger('estado_id');
            $table->timestamps();
        });
        Schema::create('paquetes_certi', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->string('cod_especial')->nullable();
            $table->string('destinatario')->nullable();
            $table->string('telefono')->nullable();
            $table->string('cuidad')->nullable();
            $table->string('zona')->nullable();
            $table->decimal('peso')->nullable();
            $table->unsignedBigInteger('fk_estado');
            $table->timestamps();
        });
        Schema::create('paquetes_ordi', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->string('cod_especial')->nullable();
            $table->string('destinatario')->nullable();
            $table->string('telefono')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('zona')->nullable();
            $table->decimal('peso')->nullable();
            $table->unsignedBigInteger('fk_estado');
            $table->timestamps();
        });
        Schema::create('paquetes_contrato', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->string('cod_especial')->nullable();
            $table->string('origen')->nullable();
            $table->string('nombre_d')->nullable();
            $table->string('telefono_d')->nullable();
            $table->string('destino')->nullable();
            $table->string('provincia')->nullable();
            $table->string('direccion_d')->nullable();
            $table->decimal('peso')->nullable();
            $table->unsignedBigInteger('estados_id');
            $table->timestamps();
        });
        Schema::create('solicitud_clientes', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo_solicitud')->nullable();
            $table->string('barcode')->nullable();
            $table->string('cod_especial')->nullable();
            $table->string('origen')->nullable();
            $table->string('nombre_destinatario')->nullable();
            $table->string('telefono_destinatario')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('direccion')->nullable();
            $table->decimal('peso')->nullable();
            $table->decimal('precio')->nullable();
            $table->unsignedBigInteger('estado_id');
            $table->timestamps();
        });
        Schema::create('cartero', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('id_paquetes_ems')->nullable();
            $table->unsignedBigInteger('id_paquetes_certi')->nullable();
            $table->unsignedBigInteger('id_paquetes_ordi')->nullable();
            $table->unsignedBigInteger('id_paquetes_contrato')->nullable();
            $table->unsignedBigInteger('id_solicitud_cliente')->nullable();
            $table->unsignedBigInteger('id_estados');
            $table->unsignedBigInteger('id_user')->nullable();
            $table->unsignedInteger('intento')->default(0);
            $table->string('recibido_por')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('imagen')->nullable();
            $table->string('imagen_devolucion')->nullable();
            $table->timestamps();
        });
    }
}
