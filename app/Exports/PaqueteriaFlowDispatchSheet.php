<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PaqueteriaFlowDispatchSheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
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
            'Despachos registrados',
            'Promedio almacén a tránsito',
        ];
    }

    public function array(): array
    {
        $rows = [];
        $dispatchTime = $this->reportData['dispatchTime'];

        foreach ([
            ['EMS', $dispatchTime['ems']],
            ['Contratos', $dispatchTime['contrato']],
            ['Total del periodo', $dispatchTime['total']],
        ] as [$serviceName, $serviceStats]) {
            $rows[] = [
                $this->reportData['periodLabel'],
                $this->reportData['departmentLabel'],
                $serviceName,
                $serviceStats['despachados'],
                $serviceStats['promedio_texto'],
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Almacén a tránsito';
    }
}
