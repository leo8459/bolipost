<?php

namespace Tests\Feature;

use App\Http\Controllers\BastionReportController;
use App\Http\Controllers\DeliveryImageController;
use App\Services\BastionReportService;
use App\Support\AclPermissionRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class BastionReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('app_settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value')->nullable();
        });
        foreach (['estados' => 'nombre_estado', 'eventos' => 'nombre_evento'] as $table => $name) {
            Schema::create($table, function (Blueprint $t) use ($name) {
                $t->id();
                $t->string($name);
            });
        }
        Schema::create('bastion_contratos', function (Blueprint $t) {
            $t->id();
            $t->integer('id_origen')->nullable();
            $t->string('codigo');
            $t->integer('estados_id')->nullable();
            $t->string('nombre_d')->nullable();
            $t->text('imagen')->nullable();
        });
        Schema::create('bastion_carteros', function (Blueprint $t) {
            $t->id();
            $t->integer('id_paquetes_contrato');
            $t->integer('id_estados');
            $t->text('imagen')->nullable();
            $t->timestamps();
        });
        foreach (['bastion_eventos', 'eventos_contrato'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->id();
                $t->string('codigo');
                $t->integer('evento_id');
                $t->timestamps();
                if ($table === 'bastion_eventos') {
                    $t->string('tabla_origen');
                    $t->integer('id_origen');
                }
            });
        }
        DB::table('estados')->insert([['id' => 1, 'nombre_estado' => 'ENTREGADO'], ['id' => 2, 'nombre_estado' => 'EN DISTRIBUCION']]);
        DB::table('eventos')->insert([['id' => 1, 'nombre_evento' => 'ADMITIDO'], ['id' => 316, 'nombre_evento' => 'PAQUETE ENTREGADO EXITOSAMENTE']]);
    }

    public function test_source_preserves_all_excel_rows_and_duplicate_codes(): void
    {
        $rows = collect(app(BastionReportService::class)->source()['paquetes']);
        $this->assertCount(499, $rows);
        $this->assertCount(474, $rows->pluck('codigo')->unique());
        $this->assertSame('LLALLAGUA', $rows->first()['origen']);
        $this->assertSame('LPB', $rows->first()['destino']);
    }

    public function test_history_merges_archive_and_live_without_duplicating_archived_events_and_uses_original_package_id(): void
    {
        $service = app(BastionReportService::class);
        $code = $service->source()['paquetes'][0]['codigo'];
        DB::table('bastion_contratos')->insert(['id' => 3, 'id_origen' => 80, 'codigo' => strtolower($code), 'estados_id' => 1, 'nombre_d' => 'DESTINATARIO']);
        DB::table('bastion_carteros')->insert(['id_paquetes_contrato' => 80, 'id_estados' => 1, 'imagen' => 'data:image/png;base64,aGVsbG8=', 'created_at' => '2026-04-03 10:00:00', 'updated_at' => '2026-04-03 10:00:00']);
        DB::table('bastion_eventos')->insert(['codigo' => $code, 'evento_id' => 1, 'tabla_origen' => 'eventos_contrato', 'id_origen' => 10, 'created_at' => '2026-04-02 10:00:00']);
        DB::table('eventos_contrato')->insert(['id' => 10, 'codigo' => $code, 'evento_id' => 1, 'created_at' => '2026-04-02 10:00:00']);
        $row = $service->rows()->first();
        $this->assertCount(2, $row['historial']);
        $this->assertSame('ADMITIDO', $row['historial'][0]['nombre']);
        $this->assertTrue($row['entregado']);
        $this->assertSame(1, $row['imagen']);
        $this->assertSame('DESTINATARIO', $row['destinatario']);
        $response = app(DeliveryImageController::class)->bastionReport($code, $service);
        $this->assertSame('hello', $response->getContent());
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
    }

    public function test_missing_packages_and_pending_packages_with_images_are_not_marked_delivered(): void
    {
        $service = app(BastionReportService::class);
        $code = $service->source()['paquetes'][0]['codigo'];
        DB::table('bastion_contratos')->insert(['codigo' => $code, 'estados_id' => 2, 'imagen' => 'pending.png']);
        $rows = $service->rows();
        $this->assertFalse($rows->first()['entregado']);
        $this->assertNull($rows->first()['imagen']);
        $missing = $rows->first(fn ($row) => $row['codigo'] !== $code);
        $this->assertFalse($missing['encontrado']);
        $view = app(BastionReportController::class)->index(Request::create('/bastiones/reporte', 'GET', ['hoja' => 'LLALLAGUA', 'buscar' => $code]), $service);
        $this->assertSame('bastiones.reporte', $view->getName());
        $this->assertSame(1, $view->getData()['paquetes']->total());
        $html = $view->render();
        $this->assertStringContainsString('Estados por los que pasó', $html);
        $this->assertStringContainsString('Sin entrega registrada', $html);
    }

    public function test_delivery_event_316_is_recognized_without_a_package_record(): void
    {
        $service = app(BastionReportService::class);
        $code = $service->source()['paquetes'][0]['codigo'];
        DB::table('eventos_contrato')->insert(['codigo' => $code, 'evento_id' => 316, 'created_at' => '2026-04-03 10:00:00']);
        $row = $service->rows()->first();
        $this->assertTrue($row['entregado']);
        $this->assertTrue($row['encontrado']);
        $this->assertNull($row['imagen']);
    }

    public function test_report_and_evidence_require_internal_authentication(): void
    {
        $this->get('/bastiones/reporte')->assertRedirect('/login');
        $this->get('/bastiones/reporte/excel')->assertRedirect('/login');
        $this->get('/bastiones/reporte/imagen/C0007A04877BO')->assertRedirect('/login');
        $this->assertSame(['bastiones.reporte'], AclPermissionRegistry::authorizationPermissionsForRouteAccess('bastiones.reporte.imagen'));
    }

    public function test_excel_download_exports_all_matching_rows_across_pages_and_preserves_history_and_image_links(): void
    {
        $service = app(BastionReportService::class);
        $code = $service->source()['paquetes'][0]['codigo'];
        DB::table('bastion_contratos')->insert(['codigo' => $code, 'estados_id' => 1, 'nombre_d' => '=1+1', 'imagen' => 'proof.png']);
        DB::table('eventos_contrato')->insert(['codigo' => $code, 'evento_id' => 1, 'created_at' => '2026-04-02 10:00:00']);
        $response = app(BastionReportController::class)->excel(Request::create('/bastiones/reporte/excel', 'GET', ['hoja' => 'LLALLAGUA', 'page' => 2]), $service);
        try {
            $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));
            $workbook = IOFactory::load($response->getFile()->getPathname());
            $sheet = $workbook->getActiveSheet();
            $this->assertSame(41, $sheet->getHighestRow());
            $this->assertSame($code, $sheet->getCell('C2')->getValue());
            $this->assertSame('=1+1', $sheet->getCell('G2')->getValue());
            $this->assertSame('s', $sheet->getCell('G2')->getDataType());
            $this->assertSame("02/04/2026 10:00 · ADMITIDO\nEstado actual: ENTREGADO", $sheet->getCell('H2')->getValue());
            $this->assertSame(route('bastiones.reporte.imagen', $code), $sheet->getCell('I2')->getHyperlink()->getUrl());
            $workbook->disconnectWorksheets();
        } finally {
            @unlink($response->getFile()->getPathname());
        }
        $response = app(BastionReportController::class)->excel(Request::create('/bastiones/reporte/excel', 'GET', ['buscar' => $code, 'estado' => 'entregado']), $service);
        try {
            $workbook = IOFactory::load($response->getFile()->getPathname());
            $this->assertSame(3, $workbook->getActiveSheet()->getHighestRow());
            $workbook->disconnectWorksheets();
        } finally {
            @unlink($response->getFile()->getPathname());
        }
    }

    public function test_live_package_state_takes_precedence_over_archived_state(): void
    {
        $service = app(BastionReportService::class);
        $code = $service->source()['paquetes'][0]['codigo'];
        Schema::create('paquetes_contrato', function (Blueprint $t) {
            $t->id();
            $t->string('codigo');
            $t->integer('estados_id');
            $t->string('nombre_d')->nullable();
        });
        DB::table('bastion_contratos')->insert(['codigo' => $code, 'estados_id' => 2]);
        DB::table('paquetes_contrato')->insert(['codigo' => $code, 'estados_id' => 1]);
        $this->assertTrue($service->rows()->first()['entregado']);
        $view = app(BastionReportController::class)->index(Request::create('/bastiones/reporte', 'GET', ['estado' => 'entregado']), $service);
        $this->assertSame(2, $view->getData()['paquetes']->total());
    }

    public function test_may_view_and_evidence_use_the_may_source(): void
    {
        $service = app(BastionReportService::class);
        $source = $service->source('mayo');
        $this->assertCount(261, $source['paquetes']);
        $this->assertCount(261, collect($source['paquetes'])->pluck('codigo')->unique());
        $code = $source['paquetes'][0]['codigo'];
        DB::table('bastion_contratos')->insert(['codigo' => $code, 'estados_id' => 1, 'imagen' => 'data:image/png;base64,aGVsbG8=']);
        $view = app(BastionReportController::class)->index(Request::create('/bastiones/reporte', 'GET', ['mes' => 'mayo']), $service);
        $this->assertSame(261, $view->getData()['paquetes']->total());
        $this->assertSame('mayo', $view->getData()['month']);
        $this->assertSame($code, $view->getData()['paquetes']->first()['codigo']);
        $html = $view->render();
        $this->assertStringContainsString('name="mes" value="mayo"', $html);
        $this->assertStringContainsString('>Abril</a>', $html);
        $this->assertStringContainsString('>Mayo</a>', $html);
        $this->assertSame('hello', app(DeliveryImageController::class)->bastionReport($code, $service)->getContent());
    }

    public function test_excel_contains_complete_april_and_may_in_separate_sheets(): void
    {
        $service = app(BastionReportService::class);
        $response = app(BastionReportController::class)->excel(Request::create('/bastiones/reporte/excel', 'GET', ['mes' => 'mayo', 'page' => 2]), $service);
        try {
            $workbook = IOFactory::load($response->getFile()->getPathname());
            $this->assertSame(['Abril', 'Mayo'], $workbook->getSheetNames());
            foreach (['abril' => 'Abril', 'mayo' => 'Mayo'] as $month => $title) {
                $source = $service->source($month)['paquetes'];
                $sheet = $workbook->getSheetByName($title);
                $this->assertSame(count($source) + 1, $sheet->getHighestRow());
                $this->assertSame(array_column($source, 'codigo'), array_column($sheet->rangeToArray('C2:C'.$sheet->getHighestRow()), 0));
                $this->assertSame('Estados por los que pasó', $sheet->getCell('H1')->getValue());
            }
            $workbook->disconnectWorksheets();
        } finally {
            @unlink($response->getFile()->getPathname());
        }
    }
}
