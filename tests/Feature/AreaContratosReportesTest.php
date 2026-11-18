<?php

namespace Tests\Feature;

use App\Http\Controllers\AreaContratosController;
use App\Models\Recojo;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class AreaContratosReportesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('empresa', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('sigla')->nullable();
            $table->string('codigo_cliente')->nullable();
            $table->timestamps();
        });

        Schema::create('empresa_sucursales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id');
            $table->string('nombre');
            $table->string('ciudad')->nullable();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('empresa_id')->nullable();
            $table->foreignId('empresa_sucursal_id')->nullable();
            $table->softDeletes();
        });

        Schema::create('estados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_estado');
            $table->timestamps();
        });

        Schema::create('cartero', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_paquetes_contrato');
            $table->text('imagen_devolucion')->nullable();
            $table->timestamps();
        });

        Schema::create('paquetes_contrato', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('estados_id')->nullable();
            $table->string('codigo')->nullable();
            $table->string('codigo_madre')->nullable();
            $table->string('origen')->nullable();
            $table->string('provincia_origen')->nullable();
            $table->string('destino')->nullable();
            $table->string('destino_registrado')->nullable();
            $table->string('provincia')->nullable();
            $table->decimal('peso')->nullable();
            $table->decimal('precio')->nullable();
            $table->string('nombre_d')->nullable();
            $table->text('observacion')->nullable();
            $table->text('imagen')->nullable();
            $table->dateTime('fecha_recojo')->nullable();
            $table->timestamps();
        });

        DB::table('estados')->insert([
            ['id' => 1, 'nombre_estado' => 'ACTIVO'],
            ['id' => 2, 'nombre_estado' => 'CANCELADO'],
        ]);

        $now = now();
        for ($id = 1; $id <= 30; $id++) {
            $origen = $id <= 20 ? 'LA PAZ' : [null, '', '  '][$id % 3];

            DB::table('paquetes_contrato')->insert([
                'id' => $id,
                'estados_id' => $id === 30 ? 2 : 1,
                'codigo' => 'CONTRATO-'.$id,
                'origen' => $origen,
                'fecha_recojo' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('paquetes_contrato');
        Schema::dropIfExists('cartero');
        Schema::dropIfExists('estados');
        Schema::dropIfExists('users');
        Schema::dropIfExists('empresa_sucursales');
        Schema::dropIfExists('empresa');

        parent::tearDown();
    }

    public function test_excel_y_descarga_usan_foto_segun_estado(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('devolucion.png', 'foto-devolucion-reciente');
        Storage::disk('public')->put('entrega.png', 'foto-entrega-original');
        DB::table('estados')->insert([
            ['id' => 3, 'nombre_estado' => 'DEVOLUCION'],
            ['id' => 4, 'nombre_estado' => 'ENTREGADO'],
        ]);
        DB::table('paquetes_contrato')->whereIn('id', [1, 2, 3])->update(['imagen' => 'entrega.png']);
        DB::table('paquetes_contrato')->whereIn('id', [1, 3])->update(['estados_id' => 3]);
        DB::table('paquetes_contrato')->where('id', 2)->update(['estados_id' => 4]);
        DB::table('cartero')->insert([
            ['id_paquetes_contrato' => 1, 'imagen_devolucion' => 'vieja.png', 'updated_at' => '2026-09-17 12:00:00'],
            ['id_paquetes_contrato' => 1, 'imagen_devolucion' => 'devolucion.png', 'updated_at' => '2026-09-18 12:00:00'],
            ['id_paquetes_contrato' => 1, 'imagen_devolucion' => '', 'updated_at' => '2026-09-18 13:00:00'],
            ['id_paquetes_contrato' => 2, 'imagen_devolucion' => 'devolucion.png', 'updated_at' => '2026-09-18 12:00:00'],
        ]);
        $rows = Recojo::with(['estadoRegistro', 'asignacionConFotoDevolucion'])
            ->whereIn('id', [1, 2, 3])->orderBy('id')->get();
        $this->assertSame('devolucion.png', $rows[0]->imagenParaReporte());
        $this->assertSame('entrega.png', $rows[1]->imagenParaReporte());
        $this->assertNull($rows[2]->imagenParaReporte());

        $sheet = $this->exportWorkbook()->getSheetByName('LA PAZ');
        $this->assertSame('DESCARGAR IMAGEN', $sheet->getCell('U13')->getValue());
        $this->assertSame('DESCARGAR IMAGEN', $sheet->getCell('U14')->getValue());
        $this->assertEmpty($sheet->getCell('U15')->getValue());
        $this->assertFalse($sheet->getCell('U15')->hasHyperlink());

        $response = $this->get(route('area-contratos.imagen-entrega.download', ['contrato' => 1]));
        $response->assertOk();
        $response->assertDownload('imagen-devolucion-CONTRATO-1.png');
        $this->assertSame('foto-devolucion-reciente', $response->streamedContent());
        $response = $this->get(route('area-contratos.imagen-entrega.download', ['contrato' => 2]));
        $response->assertDownload('imagen-entrega-CONTRATO-2.png');
        $this->assertSame('foto-entrega-original', $response->streamedContent());
        $this->get(route('area-contratos.imagen-entrega.download', ['contrato' => 3]))->assertNotFound();
    }

    public function test_resume_en_la_base_de_datos_sin_cargar_todos_los_contratos(): void
    {
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries) {
            $queries[] = strtolower($query->sql);
        });

        $view = app(AreaContratosController::class)->reportes(
            Request::create('/area-contratos/reportes', 'GET')
        );
        $data = $view->getData();

        $this->assertSame(29, $data['totalReportes']);
        $this->assertCount(25, $data['contratos']);
        $this->assertSame([
            ['origen' => 'LA PAZ', 'total' => 20],
            ['origen' => 'SIN ORIGEN', 'total' => 9],
        ], $data['groupedSummary']->all());

        $this->assertTrue(collect($queries)->contains(
            fn (string $sql) => str_contains($sql, 'count(*) as total')
                && str_contains($sql, 'group by "origen"')
        ));
    }

    public function test_reporte_usa_fecha_recojo_y_excluye_contratos_sin_esa_fecha(): void
    {
        DB::table('paquetes_contrato')->where('id', 1)->update([
            'fecha_recojo' => '2026-08-10 08:00:00',
            'created_at' => '2026-08-15 08:00:00',
        ]);
        DB::table('paquetes_contrato')->where('id', 2)->update([
            'fecha_recojo' => null,
            'created_at' => '2026-08-10 08:00:00',
        ]);

        $view = app(AreaContratosController::class)->reportes(
            Request::create('/area-contratos/reportes', 'GET', [
                'from' => '2026-08-10',
                'to' => '2026-08-10',
            ])
        );
        $data = $view->getData();

        $this->assertSame(1, $data['totalReportes']);
        $this->assertSame([1], $data['contratos']->pluck('id')->all());
        $this->assertNotNull($data['contratos']->first()->fecha_recojo);
    }

    public function test_migracion_asigna_a_guias_hijas_su_propia_fecha_de_creacion(): void
    {
        DB::table('paquetes_contrato')->insert([
            'id' => 31,
            'estados_id' => 1,
            'codigo' => 'C0047A34547BO',
            'codigo_madre' => 'C0047A34530BO',
            'origen' => 'COCHABAMBA',
            'fecha_recojo' => null,
            'created_at' => '2026-07-16 19:26:57',
            'updated_at' => '2026-07-16 19:26:57',
        ]);

        $migration = require database_path(
            'migrations/2026_08_21_010000_backfill_fecha_recojo_on_guias_hijas.php'
        );
        $migration->up();

        $this->assertDatabaseHas('paquetes_contrato', [
            'codigo' => 'C0047A34547BO',
            'fecha_recojo' => '2026-07-16 19:26:57',
        ]);

        $view = app(AreaContratosController::class)->reportes(
            Request::create('/area-contratos/reportes', 'GET', [
                'from' => '2026-07-01',
                'to' => '2026-07-31',
            ])
        );

        $this->assertSame(
            ['C0047A34547BO'],
            $view->getData()['contratos']->pluck('codigo')->all()
        );
    }

    public function test_agrega_un_enlace_para_descargar_la_imagen_sin_incrustarla_en_el_excel(): void
    {
        $base64Png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

        DB::table('paquetes_contrato')->where('id', 1)->update([
            'imagen' => 'data:image/png;base64,'.$base64Png,
            'fecha_recojo' => '2026-08-10 08:00:00',
        ]);

        $sheet = $this->exportWorkbook(['from' => '2026-08-10', 'to' => '2026-08-10'])->getSheetByName('LA PAZ');
        $deliveryDrawing = collect($sheet->getDrawingCollection())
            ->first(fn ($drawing) => $drawing->getCoordinates() === 'U13');

        $this->assertNull($deliveryDrawing);
        $this->assertSame('10-08-26', $sheet->getCell('B13')->getValue());
        $this->assertSame('DESCARGAR IMAGEN', $sheet->getCell('U13')->getValue());
        $this->assertStringContainsString(
            '/area-contratos/imagen-entrega/1/descargar',
            $sheet->getCell('U13')->getHyperlink()->getUrl()
        );
    }

    public function test_excel_usa_destino_como_respaldo_si_no_hay_destino_registrado(): void
    {
        DB::table('paquetes_contrato')->where('id', 1)->update([
            'destino' => 'COCHABAMBA',
            'destino_registrado' => null,
        ]);
        DB::table('paquetes_contrato')->where('id', 2)->update([
            'destino' => 'SANTA CRUZ',
            'destino_registrado' => '   ',
        ]);
        DB::table('paquetes_contrato')->where('id', 3)->update([
            'destino' => 'ORURO',
            'destino_registrado' => 'POTOSI',
        ]);

        $sheet = $this->exportWorkbook()->getSheetByName('LA PAZ');
        $this->assertSame('COCHABAMBA', $sheet->getCell('G13')->getValue());
        $this->assertSame('SANTA CRUZ', $sheet->getCell('G14')->getValue());
        $this->assertSame('POTOSI', $sheet->getCell('G15')->getValue());
    }

    public function test_descarga_una_imagen_guardada_en_base64(): void
    {
        $base64Png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

        DB::table('paquetes_contrato')->where('id', 1)->update([
            'imagen' => 'data:image/png;base64,'.$base64Png,
        ]);

        $response = $this->get(route('area-contratos.imagen-entrega.download', ['contrato' => 1]));

        $response->assertOk();
        $response->assertHeader('content-type', 'image/png');
        $response->assertHeader('content-disposition', 'attachment; filename="imagen-entrega-CONTRATO-1.png"');
        $this->assertSame(base64_decode($base64Png), $response->getContent());
    }

    public function test_agrupa_empresas_y_filtra_contratos_por_codigo_cliente(): void
    {
        DB::table('empresa')->insert([
            ['id' => 1, 'nombre' => 'ABC Santa Cruz', 'sigla' => 'ABC SC', 'codigo_cliente' => ' CLI 001 '],
            ['id' => 2, 'nombre' => 'ABC La Paz', 'sigla' => 'ABC LP', 'codigo_cliente' => 'cli001'],
            ['id' => 3, 'nombre' => 'Otra empresa', 'sigla' => null, 'codigo_cliente' => 'CLI002'],
        ]);

        DB::table('paquetes_contrato')->where('id', 1)->update(['empresa_id' => 1]);
        DB::table('paquetes_contrato')->where('id', 2)->update(['empresa_id' => 2]);
        DB::table('paquetes_contrato')->where('id', 3)->update(['empresa_id' => 3]);

        $view = app(AreaContratosController::class)->reportes(
            Request::create('/area-contratos/reportes', 'GET', ['empresa_id' => 1])
        );
        $data = $view->getData();

        $this->assertSame(2, $data['totalReportes']);
        $this->assertSame([1, 2], $data['contratos']->pluck('empresa_id')->all());
        $this->assertCount(2, $data['empresas']);
        $this->assertSame([1, 2], $data['empresas']->firstWhere('codigo_cliente', 'CLI001')['ids']);
    }

    public function test_filtra_y_exporta_las_empresas_elegidas_aunque_compartan_codigo(): void
    {
        DB::table('empresa')->insert([
            ['id' => 1, 'nombre' => 'AJ La Paz', 'sigla' => null, 'codigo_cliente' => 'AJ'],
            ['id' => 2, 'nombre' => 'AJ Santa Cruz', 'sigla' => null, 'codigo_cliente' => ' aj '],
            ['id' => 3, 'nombre' => 'Otra empresa', 'sigla' => null, 'codigo_cliente' => 'OTRA'],
        ]);
        DB::table('paquetes_contrato')->whereIn('id', [1, 4])->update(['empresa_id' => 1]);
        DB::table('paquetes_contrato')->where('id', 2)->update(['empresa_id' => 2]);
        DB::table('paquetes_contrato')->where('id', 3)->update(['empresa_id' => 3]);
        DB::table('paquetes_contrato')->where('id', 4)->update(['origen' => 'COCHABAMBA']);

        foreach ([[1], [1, 2], [1, 3]] as $empresaIds) {
            $filters = ['empresa_ids' => $empresaIds];
            $data = app(AreaContratosController::class)->reportes(
                Request::create('/area-contratos/reportes', 'GET', $filters)
            )->getData();

            $this->assertSame($empresaIds, $data['empresaIds']);
            $this->assertSame([1, 2], $data['empresas']->firstWhere('codigo_cliente', 'AJ')['ids']);
            $this->assertSame([...$empresaIds, 4], $data['contratos']->pluck('id')->all());
            $this->assertSame([
                ['origen' => 'COCHABAMBA', 'total' => 1],
                ['origen' => 'LA PAZ', 'total' => count($empresaIds)],
            ], $data['groupedSummary']->all());

            $workbook = $this->exportWorkbook($filters);
            $this->assertSame(['COCHABAMBA', 'LA PAZ', 'RESUMEN'], $workbook->getSheetNames());
            $this->assertSame('CONTRATO-4', $workbook->getSheetByName('COCHABAMBA')->getCell('C13')->getValue());
            $laPaz = $workbook->getSheetByName('LA PAZ');
            $this->assertSame('CONTRATO-1', $laPaz->getCell('C13')->getValue());
            $this->assertSame(isset($empresaIds[1]) ? 'CONTRATO-'.$empresaIds[1] : null, $laPaz->getCell('C14')->getValue());
            $this->assertNull($laPaz->getCell('C15')->getValue());
        }
    }

    public function test_filtra_y_exporta_una_sucursal_de_las_empresas_seleccionadas(): void
    {
        DB::table('empresa')->insert([
            ['id' => 1, 'nombre' => 'Empresa Centro', 'codigo_cliente' => 'CLI 001'],
            ['id' => 2, 'nombre' => 'Empresa Norte', 'codigo_cliente' => 'cli001'],
            ['id' => 3, 'nombre' => 'Otra empresa', 'codigo_cliente' => 'CLI002'],
        ]);
        DB::table('empresa_sucursales')->insert([
            ['id' => 10, 'empresa_id' => 1, 'nombre' => 'Centro', 'ciudad' => 'LA PAZ'],
            ['id' => 20, 'empresa_id' => 2, 'nombre' => 'Norte', 'ciudad' => 'ORURO'],
        ]);
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Luis', 'empresa_id' => 1, 'empresa_sucursal_id' => 10, 'deleted_at' => null],
            ['id' => 2, 'name' => 'Ana', 'empresa_id' => 2, 'empresa_sucursal_id' => 20, 'deleted_at' => null],
            ['id' => 3, 'name' => 'Mario', 'empresa_id' => 2, 'empresa_sucursal_id' => null, 'deleted_at' => null],
            ['id' => 4, 'name' => 'Camila', 'empresa_id' => 2, 'empresa_sucursal_id' => 20, 'deleted_at' => now()],
        ]);
        foreach ([1 => [1, 1], 2 => [2, 2], 3 => [1, 2], 4 => [2, 3], 5 => [3, 2], 6 => [2, 4]] as $id => [$empresaId, $userId]) {
            DB::table('paquetes_contrato')->where('id', $id)->update(['empresa_id' => $empresaId, 'user_id' => $userId]);
        }

        $controller = app(AreaContratosController::class);
        $general = $controller->reportes(Request::create('/area-contratos/reportes', 'GET', [
            'empresa_ids' => [1, 2],
            'empresa_sucursal_id' => '',
        ]))->getData();
        $this->assertSame([1, 2, 3, 4, 6], $general['contratos']->pluck('id')->all());
        $this->assertSame('Norte - ORURO', $general['sucursalesPorEmpresa'][2]['sucursales'][0]['nombre']);

        $filters = ['empresa_ids' => [2], 'empresa_sucursal_id' => 20];
        $filtered = $controller->reportes(Request::create('/area-contratos/reportes', 'GET', $filters))->getData();
        $this->assertSame([2, 3, 6], $filtered['contratos']->pluck('id')->all());
        $this->assertSame(3, $filtered['totalReportes']);
        $this->assertSame([['origen' => 'LA PAZ', 'total' => 3]], $filtered['groupedSummary']->all());

        $workbook = $this->exportWorkbook($filters);
        $sheet = $workbook->getSheetByName('LA PAZ');
        $this->assertSame('Norte - ORURO', $sheet->getCell('C9')->getValue());
        $this->assertSame(['CONTRATO-2', 'CONTRATO-3', 'CONTRATO-6'], array_column($sheet->rangeToArray('C13:C15'), 0));
        $this->assertNull($sheet->getCell('C16')->getValue());
        $this->assertSame('SUCURSAL: Norte - ORURO', $workbook->getSheetByName('RESUMEN')->getCell('C4')->getValue());
    }

    public function test_rechaza_una_sucursal_ajena_a_las_empresas_seleccionadas(): void
    {
        DB::table('empresa')->insert([
            ['id' => 1, 'nombre' => 'Empresa Centro', 'codigo_cliente' => 'CLI001'],
            ['id' => 2, 'nombre' => 'Empresa Norte', 'codigo_cliente' => 'CLI001'],
        ]);
        DB::table('empresa_sucursales')->insert(['id' => 20, 'empresa_id' => 2, 'nombre' => 'Norte']);

        $controller = app(AreaContratosController::class);
        foreach (['reportes', 'exportReportesExcel'] as $action) {
            try {
                $controller->{$action}(Request::create('/area-contratos/reportes', 'GET', [
                    'empresa_ids' => [1],
                    'empresa_sucursal_id' => 20,
                ]));
                $this->fail('Se aceptó una sucursal de una empresa sin seleccionar.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('empresa_sucursal_id', $exception->errors());
            }
        }
    }

    private function exportWorkbook(array $filters = []): Spreadsheet
    {
        $response = app(AreaContratosController::class)->exportReportesExcel(
            Request::create('/area-contratos/reportes/excel', 'GET', $filters)
        );
        $path = $response->getFile()->getPathname();

        try {
            return IOFactory::load($path);
        } finally {
            unlink($path);
        }
    }
}
