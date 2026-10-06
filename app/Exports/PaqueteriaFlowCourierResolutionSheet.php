<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PaqueteriaFlowCourierResolutionSheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(
        private readonly array $reportData
    ) {
    }

    public function headings(): array
    {
        return [
            'Periodo',
            'Departamento de origen',
            'Servicio',
            'Entregados',
            'Promedio desde asignación hasta entrega',
            'Devueltos',
            'Promedio desde asignación hasta devolución',
            'Finalizados',
            'Promedio general desde asignación',
        ];
    }

    public function array(): array
    {
        $rows = [];
        $courierResolutionTime = $this->reportData['courierResolutionTime'];

        foreach ([
            ['EMS', $courierResolutionTime['ems']],
            ['Contratos', $courierResolutionTime['contrato']],
            ['Total del periodo', $courierResolutionTime['total']],
        ] as [$serviceName, $serviceStats]) {
            $rows[] = [
                $this->reportData['periodLabel'],
                $this->reportData['departmentLabel'],
                $serviceName,
                $serviceStats['entregados'],
                $serviceStats['promedio_entrega_texto'],
                $serviceStats['devueltos'],
                $serviceStats['promedio_devolucion_texto'],
                $serviceStats['finalizados'],
                $serviceStats['promedio_total_texto'],
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Asignación a cierre';
    }
}
