<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DailyDeliveryExport implements WithMultipleSheets
{
    public function __construct(private readonly array $report) {}

    public function sheets(): array
    {
        $summary = [
            ['Corte (Bolivia)', $this->report['cutoff']],
            ['Servicio', 'Registrados', 'Asignados', 'Entregados'],
        ];
        foreach ($this->report['modules'] as $module) {
            $summary[] = [$module['name'], $module['registered'], $module['assigned'], $module['delivered']];
        }
        $totals = $this->report['totals'];
        $summary[] = ['Total', $totals['registered'], $totals['assigned'], $totals['delivered']];

        $couriers = [[
            'Cartero o usuario', 'Departamento', 'EMS asignados', 'EMS entregados',
            'Contratos asignados', 'Contratos entregados', 'Total asignados', 'Total entregados',
        ]];
        foreach ($this->report['couriers'] as $courier) {
            $couriers[] = [
                $courier['name'],
                $courier['department'],
                $courier['modules']['ems']['assigned'],
                $courier['modules']['ems']['delivered'],
                $courier['modules']['contratos']['assigned'],
                $courier['modules']['contratos']['delivered'],
                $courier['assigned'],
                $courier['delivered'],
            ];
        }

        return [
            new DailyClosingSheet('Resumen', $summary, 2),
            new DailyClosingSheet('Carteros', $couriers),
        ];
    }
}
