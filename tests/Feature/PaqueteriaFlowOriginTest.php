<?php

namespace Tests\Feature;

use App\Exports\PaqueteriaFlowExport;
use App\Http\Controllers\PaqueteriaFlowController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaqueteriaFlowOriginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('empresa', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->integer('empresa_id')->nullable();
        });
        Schema::create('estados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_estado');
        });
        foreach (['paquetes_ems', 'paquetes_contrato', 'paquetes_int'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('codigo');
                $table->string('cod_especial')->nullable();
                $table->string('origen')->nullable();
                $table->string('destino');
                $table->string('ciudad');
                $table->integer('user_id')->nullable();
                $table->integer('empresa_id')->nullable();
                $table->integer('estado_id')->nullable();
                $table->integer('estados_id')->nullable();
                $table->integer('cantidad')->default(1);
                $table->decimal('peso', 10, 3);
                $table->timestamps();
            });
        }
        Schema::create('bitacoras', function (Blueprint $table) {
            $table->id();
            $table->integer('paquetes_ems_id')->nullable();
            $table->integer('paquetes_contrato_id')->nullable();
            $table->string('cod_especial');
            $table->string('transportadora');
            $table->decimal('peso', 10, 3);
            $table->timestamps();
        });

        DB::table('empresa')->insert(['id' => 1, 'nombre' => 'Cliente real']);
        DB::table('estados')->insert(['id' => 2, 'nombre_estado' => 'CANCELADO']);
        foreach (['paquetes_ems', 'paquetes_contrato', 'paquetes_int'] as $name) {
            foreach ([['LA PAZ', 'SANTA CRUZ', 10, 1], ['SANTA CRUZ', 'LA PAZ', 100, 1], ['LA PAZ', 'SANTA CRUZ', 1000, 2]] as $index => [$origin, $destination, $weight, $state]) {
                DB::table($name)->insert([
                    'id' => $index + 1,
                    'codigo' => $name.'-'.$index,
                    'cod_especial' => $name.'-cn-'.$index,
                    'origen' => $origin,
                    'destino' => $destination,
                    'ciudad' => $destination,
                    'empresa_id' => 1,
                    'estado_id' => $state,
                    'estados_id' => $state,
                    'peso' => $weight,
                    'created_at' => '2026-07-15 12:00:00',
                ]);
            }
        }
        foreach ([
            ['paquetes_ems-cn-0', null, 10],
            ['paquetes_ems-cn-1', null, 100],
            ['direct-contract', 1, 20],
            ['paquetes_int-cn-0', null, 30],
            ['unknown-origin', null, 40],
        ] as [$code, $contractId, $weight]) {
            DB::table('bitacoras')->insert([
                'cod_especial' => $code,
                'paquetes_contrato_id' => $contractId,
                'transportadora' => 'BOA',
                'peso' => $weight,
                'created_at' => '2026-07-15 12:00:00',
            ]);
        }
    }

    public function test_selected_department_uses_origin_for_guides_companies_and_cn33(): void
    {
        $data = $this->report(['LA PAZ']);

        $this->assertSame(1, $data['totals']['guias_ems']);
        $this->assertSame(1, $data['totals']['guias_contrato']);
        $this->assertEquals(20, $data['totals']['peso_recibido']);
        $this->assertSame(1, $data['companyRows'][0]['guias_total']);
        $this->assertEquals(10, $data['companyRows'][0]['peso_total']);
        $this->assertEquals(60, $data['totals']['aereo']);
        $this->assertSame('Departamentos de origen: La Paz', (new PaqueteriaFlowExport($data))->array()[0][2]);
    }

    public function test_without_department_filter_includes_all_origins_and_unlinked_cn33(): void
    {
        $data = $this->report([]);

        $this->assertSame(2, $data['totals']['guias_ems']);
        $this->assertSame(2, $data['totals']['guias_contrato']);
        $this->assertEquals(220, $data['totals']['peso_recibido']);
        $this->assertEquals(200, $data['totals']['aereo']);
    }

    private function report(array $departments): array
    {
        $request = Request::create('/', 'GET', [
            'anio' => 2026,
            'meses' => [7],
            'departamentos' => $departments,
        ]);

        return app(PaqueteriaFlowController::class)->index($request)->getData();
    }
}
