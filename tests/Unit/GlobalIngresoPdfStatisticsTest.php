<?php

namespace Tests\Unit;

use App\Http\Controllers\ReportesController;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class GlobalIngresoPdfStatisticsTest extends TestCase
{
    public function test_it_builds_statistics_from_every_filtered_row(): void
    {
        $rows = collect([
            $this->row('contrato', 'CONTRATOS', 'entregado', true, 2.0, 'LA PAZ', 'EXPRESO', '2026-06-01'),
            $this->row('contrato', 'CONTRATOS', 'correcto', false, 4.0, 'LA PAZ', 'EXPRESO', '2026-06-15'),
            $this->row('ems', 'EMS', 'rezago', false, 9.0, 'SANTA CRUZ', 'EMS', '2026-07-01'),
        ]);

        $method = new ReflectionMethod(ReportesController::class, 'buildGlobalIngresoPdfStatistics');
        $statistics = $method->invoke(new ReportesController(), $rows);

        $this->assertSame(33.3, $statistics['tasa_entrega']);
        $this->assertSame(5.0, $statistics['peso_promedio']);
        $this->assertSame(4.0, $statistics['peso_mediano']);
        $this->assertSame(9.0, $statistics['peso_maximo']);
        $this->assertCount(2, $statistics['modulos']);
        $this->assertCount(2, $statistics['meses']);
        $this->assertSame(2, $statistics['destinos'][0]['cantidad']);
    }

    public function test_pdf_template_has_statistics_instead_of_package_details_or_prices(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/resources/views/reportes/global-ingreso-pdf.blade.php');

        $this->assertStringContainsString('Distribución por situación', $template);
        $this->assertStringContainsString('Evolución mensual', $template);
        $this->assertStringContainsString('Gráficos estadísticos', $template);
        $this->assertStringContainsString('Volumen mensual', $template);
        $this->assertStringContainsString('Destinos principales', $template);
        $this->assertStringNotContainsString('@forelse($rows', $template);
        $this->assertStringNotContainsString("['precio']", $template);
        $this->assertStringNotContainsString('precio_total', $template);
    }

    private function row(
        string $moduleKey,
        string $moduleLabel,
        string $bucket,
        bool $delivered,
        float $weight,
        string $destination,
        string $service,
        string $createdAt
    ): array {
        return [
            'modulo_key' => $moduleKey,
            'modulo_label' => $moduleLabel,
            'situacion_bucket' => $bucket,
            'is_entregado' => $delivered,
            'peso' => $weight,
            'destino' => $destination,
            'servicio' => $service,
            'created_at_ts' => Carbon::parse($createdAt)->timestamp,
        ];
    }
}
