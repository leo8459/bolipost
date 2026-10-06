<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PaqueteriaFlowResolutionSheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
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
            'Promedio hasta entrega',
            'Devueltos',
            'Promedio hasta devolución',
            'Finalizados',
            'Promedio general',
        ];
    }

    public function array(): array
    {
        $rows = [];
        $resolutionTime = $this->reportData['resolutionTime'];

        foreach ([
            ['EMS', $resolutionTime['ems']],
            ['Contratos', $resolutionTime['contrato']],
            ['Total del periodo', $resolutionTime['total']],
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
        return 'Entrega promedio';
    }
}
