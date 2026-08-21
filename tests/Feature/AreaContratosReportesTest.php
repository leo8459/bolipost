<?php

namespace Tests\Feature;

use App\Exports\AreaContratosEntregadosExport;
use App\Http\Controllers\AreaContratosController;
use App\Models\Recojo;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
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

        Schema::create('estados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_estado');
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
        Schema::dropIfExists('estados');
        Schema::dropIfExists('empresa');

        parent::tearDown();
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

        $rows = Recojo::query()->where('id', 1)->get();
        $contents = Excel::raw(new AreaContratosEntregadosExport($rows), ExcelWriter::XLSX);
        $path = tempnam(sys_get_temp_dir(), 'contratos-excel-');
        $this->assertNotFalse($path);

        try {
            file_put_contents($path, $contents);
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getSheet(0);

            $deliveryDrawing = collect($sheet->getDrawingCollection())
                ->first(fn ($drawing) => $drawing->getCoordinates() === 'U13');

            $this->assertNull($deliveryDrawing);
            $this->assertSame('10-08-26', $sheet->getCell('B13')->getValue());
            $this->assertSame('DESCARGAR IMAGEN', $sheet->getCell('U13')->getValue());
            $this->assertStringContainsString(
                '/area-contratos/imagen-entrega/1/descargar',
                $sheet->getCell('U13')->getHyperlink()->getUrl()
            );
        } finally {
            if (is_string($path) && is_file($path)) {
                unlink($path);
            }
        }
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
}
