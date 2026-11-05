<?php

namespace Tests\Feature;

use App\Exports\CommercialPerformanceExport;
use App\Http\Controllers\ReportesController;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use ReflectionMethod;
use Tests\TestCase;

class CommercialRegionalReportTest extends TestCase
{
    private function regionalFilter(array $rows, array $selected, string $type = 'origen'): Collection
    {
        return (new ReflectionMethod(ReportesController::class, 'filterCommercialRowsByRegional'))
            ->invoke(new ReportesController(), collect($rows), $selected, $type);
    }

    public function test_multiple_regionals_and_city_aliases_work_for_origin_and_destination(): void
    {
        $rows = [
            ['codigo' => 'EMS', 'origen_registro' => 'Sucre', 'destino' => 'Trinidad'],
            ['codigo' => 'CONTRATO', 'origen_registro' => 'Potosí', 'destino' => 'Cobija'],
            ['codigo' => 'DELIVERY', 'origen_registro' => 'El Alto', 'destino' => 'Sucre'],
            ['codigo' => 'UNKNOWN', 'origen_registro' => '-', 'destino' => '-'],
        ];

        $this->assertSame(['EMS', 'CONTRATO'], $this->regionalFilter($rows, ['CHUQUISACA', 'POTOSI'])->pluck('codigo')->all());
        $this->assertSame(['EMS', 'CONTRATO'], $this->regionalFilter($rows, ['BENI', 'PANDO'], 'destino')->pluck('codigo')->all());
        $this->assertSame(['DELIVERY'], $this->regionalFilter($rows, ['LA PAZ'])->pluck('codigo')->all());
        $this->assertCount(4, $this->regionalFilter($rows, []));
        $this->assertSame('SIN REGIONAL IDENTIFICADA', $this->regionalFilter($rows, [])->last()['regional_comercial']);
        $this->assertCount(0, $this->regionalFilter($rows, ['ORURO']));
    }

    public function test_monthly_growth_uses_only_the_selected_regionals_in_both_months(): void
    {
        $rows = [];
        foreach (['2026-05' => 2, '2026-06' => 3] as $month => $count) {
            for ($i = 0; $i < $count; $i++) {
                $rows[] = ['origen_registro' => 'Sucre', 'created_at_month' => $month, 'is_entregado' => true, 'is_cancelado' => false, 'peso' => 1];
            }
        }
        $rows[] = ['origen_registro' => 'Santa Cruz', 'created_at_month' => '2026-06', 'is_entregado' => false, 'is_cancelado' => false, 'peso' => 8];
        $summary = (new ReflectionMethod(ReportesController::class, 'buildCommercialMonthlySummary'))->invoke(
            new ReportesController(), $this->regionalFilter($rows, ['CHUQUISACA']), Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30')
        );
        $this->assertCount(1, $summary);
        $this->assertSame(3, $summary[0]['registros']);
        $this->assertSame(2, $summary[0]['mes_anterior_registros']);
        $this->assertEquals(50, $summary[0]['variacion_pct']);
    }

    public function test_exports_include_regional_scope_and_monthly_charts(): void
    {
        $data = [
            'scopeLabel' => 'Rendimiento comercial', 'from' => '2026-06-01', 'to' => '2026-06-30',
            'periodLabel' => 'Junio 2026', 'selectedLines' => [], 'regionalType' => 'origen',
            'regionalLabel' => 'Origen: CHUQUISACA', 'lineRows' => [], 'serviceRows' => [],
            'regionalRows' => [['regional' => 'CHUQUISACA', 'registros' => 3, 'entregados' => 3, 'no_entregados' => 0, 'efectividad' => 100, 'peso' => 3]],
            'monthlyRows' => [['periodo' => 'Junio 2026', 'registros' => 3, 'mes_anterior_registros' => 2, 'variacion' => 1, 'variacion_pct' => 50, 'entregados' => 3, 'no_entregados' => 0, 'peso' => 3]],
        ];
        $html = view('reportes.commercial-performance-pdf', $data)->render();
        $this->assertStringContainsString('Origen: CHUQUISACA', $html);
        $this->assertStringContainsString('Regional de origen', $html);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('A4', 'landscape')->output();
        $this->assertStringStartsWith('%PDF', $pdf);

        $file = tempnam(sys_get_temp_dir(), 'commercial-regional-');
        try {
            file_put_contents($file, Excel::raw(new CommercialPerformanceExport($data), \Maatwebsite\Excel\Excel::XLSX));
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $reader->setIncludeCharts(true);
            $book = $reader->load($file);
            $this->assertSame('Origen: CHUQUISACA', $book->getSheetByName('Resumen comercial')->getCell('J2')->getValue());
            $this->assertSame('CHUQUISACA', $book->getSheetByName('Resumen por regional')->getCell('A2')->getValue());
            $this->assertSame(3, $book->getSheetByName('Resumen por regional')->getCell('B2')->getValue());
            $this->assertSame(2, $book->getSheetByName('Evolución mensual')->getChartCount());
            $book->disconnectWorksheets();
        } finally {
            unlink($file);
        }
    }
}
