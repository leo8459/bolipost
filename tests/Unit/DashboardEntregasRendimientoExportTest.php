<?php

namespace Tests\Unit;

use App\Exports\DashboardEntregasRendimientoExport;
use PHPUnit\Framework\TestCase;

class DashboardEntregasRendimientoExportTest extends TestCase
{
    public function test_it_limits_fulfillment_percentage_to_one_hundred(): void
    {
        $export = new DashboardEntregasRendimientoExport([
            'entregadores' => [
                (object) [
                    'id' => 1,
                    'name' => 'Cartero de prueba',
                    'cumplimiento_asignados' => 125.4,
                ],
            ],
        ]);

        $row = $export->collection()->first();

        $this->assertSame(100.0, $row['cumplimiento_asignados_porcentaje']);
    }
}
