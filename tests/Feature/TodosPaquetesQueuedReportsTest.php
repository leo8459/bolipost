<?php

namespace Tests\Feature;

use App\Jobs\GenerateTodosPaquetesExcelReport;
use App\Jobs\GenerateTodosPaquetesHistoryPdfReport;
use App\Models\PackageReportExport;
use App\Services\TodosPaquetesHistoryReportService;
use App\Services\TodosPaquetesReportQuery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class TodosPaquetesQueuedReportsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('package_report_exports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->string('report_type');
            $table->json('filters');
            $table->string('status');
            $table->string('file_path')->nullable();
            $table->string('file_name');
            $table->unsignedBigInteger('row_count')->default(0);
            $table->unsignedBigInteger('total_rows')->default(0);
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('progress_message')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('package_report_exports');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_excel_job_writes_100000_rows_in_bounded_chunks_and_marks_report_ready(): void
    {
        $report = $this->createReport('xlsx', ['search' => '']);
        $query = Mockery::mock(TodosPaquetesReportQuery::class);
        $query->shouldReceive('count')
            ->once()
            ->with(['search' => ''])
            ->andReturn(100000);
        $query->shouldReceive('chunked')
            ->once()
            ->andReturnUsing(function (array $filters, callable $callback): void {
                $this->assertSame(['search' => ''], $filters);

                for ($start = 1; $start <= 100000; $start += 2000) {
                    $chunk = [];
                    $end = min($start + 1999, 100000);

                    for ($index = $start; $index <= $end; $index++) {
                        $chunk[] = (object) [
                            'tipo' => 'EMS',
                            'codigo' => sprintf('C%08dBO', $index),
                            'cod_especial' => 'LPZ00530',
                            'origen' => 'LA PAZ',
                            'destino' => 'SANTA CRUZ',
                            'empresa' => '',
                            'remitente' => 'Remitente',
                            'destinatario' => $index === 1 ? '=1+1' : 'Destinatario',
                            'telefono' => '70000000',
                            'peso' => '1.250',
                            'precio' => '12.50',
                            'estado_nombre' => 'EN TRANSITO',
                            'justificacion' => '',
                            'fecha_recojo' => null,
                        ];
                    }

                    $callback(collect($chunk));
                }
            });

        (new GenerateTodosPaquetesExcelReport($report->id))->handle($query, app(\App\Services\TodosPaquetesXlsxWriter::class));

        $report->refresh();
        $this->assertSame('completed', $report->status);
        $this->assertSame(100000, $report->row_count);
        $this->assertSame(100000, $report->total_rows);
        $this->assertSame(100, $report->progress);
        $this->assertNotNull($report->expires_at);
        $this->assertSame('todos-paquetes/reportes/'.$report->id.'.xlsx', $report->file_path);

        $xlsxPath = Storage::disk('local')->path($report->file_path);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($xlsxPath) === true);
        foreach (['[Content_Types].xml', 'xl/workbook.xml', 'xl/worksheets/sheet1.xml'] as $part) {
            $this->assertNotFalse($zip->locateName($part), 'Falta la parte '.$part.' dentro del XLSX.');
        }

        $sheet = $zip->getStream('xl/worksheets/sheet1.xml');
        $this->assertIsResource($sheet);
        $sheetXmlPath = tempnam(sys_get_temp_dir(), 'todos-paquetes-sheet-');
        $sheetXmlHandle = fopen($sheetXmlPath, 'wb');
        $rowsFound = 0;
        $carry = '';
        $hasFirstPackage = false;
        $hasLastPackage = false;
        $hasLiteralFormulaLikeText = false;
        $hasNumericWeight = false;

        while (! feof($sheet)) {
            $chunk = fread($sheet, 65536);
            $searchable = $carry.$chunk;
            $rowsFound += substr_count($searchable, '<row r="') - substr_count($carry, '<row r="');
            $hasFirstPackage = $hasFirstPackage || str_contains($searchable, 'C00000001BO');
            $hasLastPackage = $hasLastPackage || str_contains($searchable, 'C00100000BO');
            $hasLiteralFormulaLikeText = $hasLiteralFormulaLikeText || str_contains($searchable, '<t xml:space="preserve">=1+1</t>');
            $hasNumericWeight = $hasNumericWeight || str_contains($searchable, '<c r="J2"><v>1.250</v></c>');
            fwrite($sheetXmlHandle, $chunk);
            $carry = substr($searchable, -128);
        }

        fclose($sheet);
        fclose($sheetXmlHandle);
        $zip->close();

        $xmlReader = new \XMLReader();
        $this->assertTrue($xmlReader->open($sheetXmlPath));
        $parsedRows = 0;
        while ($xmlReader->read()) {
            if ($xmlReader->nodeType === \XMLReader::ELEMENT && $xmlReader->localName === 'row') {
                $parsedRows++;
            }
        }
        $xmlReader->close();
        unlink($sheetXmlPath);

        $this->assertSame(100001, $rowsFound);
        $this->assertSame(100001, $parsedRows);
        $this->assertTrue($hasFirstPackage);
        $this->assertTrue($hasLastPackage);
        $this->assertTrue($hasLiteralFormulaLikeText);
        $this->assertTrue($hasNumericWeight);
    }

    public function test_history_pdf_job_saves_the_pdf_and_marks_found_guide_count(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Operador de prueba',
            'email' => 'operador@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $report = $this->createReport('history_pdf', ['codes' => ['C0040A5663BO']], $userId);
        $generator = Mockery::mock(TodosPaquetesHistoryReportService::class);
        $generator->shouldReceive('generate')
            ->once()
            ->with(
                ['C0040A5663BO'],
                Mockery::on(fn ($user) => (int) $user->id === (int) $userId),
                Mockery::on(fn ($callback) => is_callable($callback)),
            )
            ->andReturnUsing(function (array $codes, $user, callable $onProgress): array {
                $onProgress(85, 'Generando el documento PDF...');

                return [
                    'contents' => "%PDF-1.4\nfixture",
                    'package_count' => 1,
                ];
            });

        (new GenerateTodosPaquetesHistoryPdfReport($report->id))->handle($generator);

        $report->refresh();
        $this->assertSame('completed', $report->status);
        $this->assertSame(1, $report->row_count);
        $this->assertSame(100, $report->progress);
        $this->assertSame("%PDF-1.4\nfixture", Storage::disk('local')->get($report->file_path));
    }

    public function test_xlsx_workbook_opens_with_the_spreadsheet_reader(): void
    {
        $sheetPath = tempnam(sys_get_temp_dir(), 'todos-paquetes-sheet-');
        $secondSheetPath = tempnam(sys_get_temp_dir(), 'todos-paquetes-sheet-');
        $xlsxPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'todos-paquetes-'.str()->uuid().'.xlsx';
        $writer = new \App\Services\TodosPaquetesXlsxWriter(1);
        $handle = $writer->openSheet($sheetPath);
        $writer->writeRow($handle, [
            'EMS', 'C12345678BO', 'LPZ00530', 'LA PAZ', 'SANTA CRUZ', '',
            'Remitente', 'Destinatario', '70000000', '1.250', '12.50',
            'EN TRANSITO', '', '',
        ], 2, [9 => true, 10 => true]);
        $writer->finishSheet($handle, 1);
        $secondSheetHandle = $writer->openSheet($secondSheetPath);
        $writer->writeRow($secondSheetHandle, [
            'EMS', 'C87654321BO', 'LPZ00530', 'LA PAZ', 'SANTA CRUZ', '',
            'Remitente', 'Destinatario', '70000000', '2.000', '22.00',
            'EN TRANSITO', '', '',
        ], 2, [9 => true, 10 => true]);
        $writer->finishSheet($secondSheetHandle, 1);
        $writer->package([$sheetPath, $secondSheetPath], $xlsxPath);

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsxPath);
            $sheet = $spreadsheet->getActiveSheet();

            $this->assertSame(['Paquetes', 'Paquetes 2'], $spreadsheet->getSheetNames());
            $this->assertSame('CODIGO', $sheet->getCell('B1')->getValue()->getPlainText());
            $this->assertSame('C12345678BO', $sheet->getCell('B2')->getValue()->getPlainText());
            $this->assertEquals(1.25, $sheet->getCell('J2')->getValue());
            $this->assertSame('C87654321BO', $spreadsheet->getSheet(1)->getCell('B2')->getValue()->getPlainText());
            $spreadsheet->disconnectWorksheets();
        } finally {
            @unlink($sheetPath);
            @unlink($secondSheetPath);
            @unlink($xlsxPath);
        }
    }

    private function createReport(string $type, array $filters, int $userId = 1): PackageReportExport
    {
        return PackageReportExport::query()->create([
            'id' => (string) str()->uuid(),
            'user_id' => $userId,
            'report_type' => $type,
            'filters' => $filters,
            'status' => 'pending',
            'file_name' => $type === 'history_pdf' ? 'historial-test.pdf' : 'paquetes-test.xlsx',
            'row_count' => 0,
        ]);
    }
}
