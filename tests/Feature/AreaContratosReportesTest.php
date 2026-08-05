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
            $table->string('origen')->nullable();
            $table->text('imagen')->nullable();
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

    public function test_incrusta_una_imagen_base64_en_el_excel_para_impresion(): void
    {
        $base64Png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

        DB::table('paquetes_contrato')->where('id', 1)->update([
            'imagen' => 'data:image/png;base64,'.$base64Png,
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

            $this->assertNotNull($deliveryDrawing);
            $this->assertNull($sheet->getCell('U13')->getValue());
            $this->assertSame(58.0, $sheet->getRowDimension(13)->getRowHeight());
        } finally {
            if (is_string($path) && is_file($path)) {
                unlink($path);
            }
        }
    }
}
