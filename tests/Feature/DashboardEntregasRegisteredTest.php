<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardEntregasRegisteredTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-20 12:00:00'));

        Schema::create('estados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_estado');
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ciudad');
        });
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_evento');
        });
        foreach (['ems' => ['ciudad', 'estado_id'], 'certi' => ['cuidad', 'fk_estado']] as $module => [$destination, $state]) {
            Schema::create('paquetes_' . $module, function (Blueprint $table) use ($destination, $state) {
                $table->string('codigo');
                $table->string($destination);
                $table->integer($state)->nullable();
                $table->timestamp('created_at');
            });
            Schema::create('eventos_' . $module, function (Blueprint $table) {
                $table->string('codigo');
                $table->integer('evento_id');
                $table->integer('user_id');
                $table->timestamp('created_at');
            });
        }

        DB::table('estados')->insert(['id' => 9, 'nombre_estado' => 'CANCELADO']);
        DB::table('users')->insert(['id' => 1, 'name' => 'Cartero de prueba', 'ciudad' => 'LA PAZ']);
        DB::table('eventos')->insert(['id' => 101, 'nombre_evento' => 'Asignado a CARTERO']);
    }

    public function test_report_compares_non_canceled_registrations_with_assignments_and_deliveries(): void
    {
        $registeredAt = '2026-11-20 09:00:00';
        foreach ([
            ['unassigned', ' la paz ', null, $registeredAt],
            ['unassigned', 'LA PAZ', null, $registeredAt],
            ['other-department', 'ORURO', 1, $registeredAt],
            ['oruro-unassigned-1', 'ORURO', 1, $registeredAt],
            ['oruro-unassigned-2', 'ORURO', 1, $registeredAt],
            ['canceled', 'LA PAZ', 9, $registeredAt],
            ['registered-earlier', 'LA PAZ', 1, '2026-11-19 09:00:00'],
        ] as [$code, $city, $state, $createdAt]) {
            DB::table('paquetes_ems')->insert([
                'codigo' => $code,
                'ciudad' => $city,
                'estado_id' => $state,
                'created_at' => $createdAt,
            ]);
        }
        DB::table('paquetes_certi')->insert([
            'codigo' => 'certificate',
            'cuidad' => 'LA PAZ',
            'fk_estado' => 1,
            'created_at' => $registeredAt,
        ]);
        foreach (['registered-earlier', 'canceled'] as $code) {
            foreach ([101, 316] as $event) {
                DB::table('eventos_ems')->insert([
                    'codigo' => $code,
                    'evento_id' => $event,
                    'user_id' => 1,
                    'created_at' => $registeredAt,
                ]);
            }
        }

        $controller = app(DashboardController::class);
        $filters = ['from' => '2026-11-20', 'to' => '2026-11-20'];
        $data = $controller->entregas(Request::create('/entregas', 'GET', [
            ...$filters,
            'modules' => ['ems', 'certi'],
        ]))->getData();
        $departments = $data['resumenDepartamentos']->keyBy('departamento');

        $this->assertSame(5, $data['totalRegistrados']);
        $this->assertSame(1, $data['totalAsignados']);
        $this->assertSame('entregados', $data['ordenDepartamentos']);
        $this->assertSame(['LA PAZ', 'ORURO'], $data['resumenDepartamentos']->pluck('departamento')->all());
        $this->assertSame(2, $departments['LA PAZ']['total_registrados']);
        $this->assertSame(1, $departments['LA PAZ']['total_asignados']);
        $this->assertSame(1, $departments['LA PAZ']['total_entregados']);
        $this->assertSame(3, $departments['ORURO']['total_registrados']);
        $this->assertSame(0, $departments['ORURO']['cantidad_carteros']);

        $byRegistration = $controller->entregas(Request::create('/entregas', 'GET', [
            ...$filters,
            'modules' => ['ems', 'certi'],
            'orden' => 'registrados',
        ]))->getData();

        $this->assertSame('registrados', $byRegistration['ordenDepartamentos']);
        $this->assertSame(['ORURO', 'LA PAZ'], $byRegistration['resumenDepartamentos']->pluck('departamento')->all());

        $filtered = $controller->entregas(Request::create('/entregas', 'GET', [
            ...$filters,
            'modules' => ['ems'],
            'cartero_departamento' => 'LA PAZ',
        ]))->getData();

        $this->assertSame(1, $filtered['totalRegistrados']);
        $this->assertSame(['LA PAZ'], $filtered['resumenDepartamentos']->pluck('departamento')->all());
    }

    public function test_combined_destinations_are_counted_under_their_department(): void
    {
        foreach ([
            ['cochabamba', 'COCHABAMBA'],
            ['ivirgarzama', ' Cochabamba Ivirgarzama '],
            ['riberalta', 'Trinidad-Riberalta'],
            ['riberalta', 'TRINIDAD'],
        ] as [$code, $city]) {
            DB::table('paquetes_ems')->insert([
                'codigo' => $code,
                'ciudad' => $city,
                'estado_id' => 1,
                'created_at' => '2026-11-20 09:00:00',
            ]);
        }

        $controller = app(DashboardController::class);
        $data = $controller->entregas(Request::create('/entregas', 'GET', ['modules' => ['ems']]))->getData();
        $expected = ['COCHABAMBA' => 2, 'TRINIDAD' => 1];

        $this->assertSame(3, $data['totalRegistrados']);
        $this->assertSame($expected, $data['resumenDepartamentos']->pluck('total_registrados', 'departamento')->all());

        foreach ($expected as $department => $total) {
            $filtered = $controller->entregas(Request::create('/entregas', 'GET', [
                'modules' => ['ems'],
                'cartero_departamento' => $department,
            ]))->getData();

            $this->assertSame($total, $filtered['totalRegistrados']);
            $this->assertSame([$department], $filtered['resumenDepartamentos']->pluck('departamento')->all());
        }
    }

    public function test_future_dates_do_not_affect_totals_or_daily_averages(): void
    {
        foreach ([
            ['first-delivery', '2026-11-16 09:00:00', '2026-11-16 10:00:00'],
            ['last-delivery', '2026-11-19 09:00:00', '2026-11-20 10:00:00'],
            ['future-delivery', '2026-11-19 09:00:00', '2626-08-21 10:00:00'],
            ['future-registration', '2626-08-21 09:00:00', '2626-08-21 10:00:00'],
        ] as [$code, $registeredAt, $deliveredAt]) {
            DB::table('paquetes_ems')->insert([
                'codigo' => $code,
                'ciudad' => 'LA PAZ',
                'estado_id' => 1,
                'created_at' => $registeredAt,
            ]);
            foreach ([101, 316] as $event) {
                DB::table('eventos_ems')->insert([
                    'codigo' => $code,
                    'evento_id' => $event,
                    'user_id' => 1,
                    'created_at' => $deliveredAt,
                ]);
            }
        }

        $controller = app(DashboardController::class);
        foreach ([[], ['from' => '2026-11-16', 'to' => '2626-08-21']] as $dates) {
            $data = $controller->entregas(Request::create('/entregas', 'GET', [
                'modules' => ['ems'],
                ...$dates,
            ]))->getData();

            $this->assertSame(5, $data['diasLaborables']);
            $this->assertSame(3, $data['totalRegistrados']);
            $this->assertSame(2, $data['totalAsignados']);
            $this->assertSame(2, $data['entregadores']->first()->total_entregados);
            $this->assertSame(0.4, $data['promedioDiarioGeneral']);
            $this->assertSame(0.4, $data['entregadores']->first()->promedio_diario);
        }
    }
}
