<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PaqueteriaFlowTransitReceiptSheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
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
            'Recepciones registradas',
            'Promedio tránsito a recepción',
        ];
    }

    public function array(): array
    {
        $rows = [];
        $transitReceiptTime = $this->reportData['transitReceiptTime'];

        foreach ([
            ['EMS', $transitReceiptTime['ems']],
            ['Contratos', $transitReceiptTime['contrato']],
            ['Total del periodo', $transitReceiptTime['total']],
        ] as [$serviceName, $serviceStats]) {
            $rows[] = [
                $this->reportData['periodLabel'],
                $this->reportData['departmentLabel'],
                $serviceName,
                $serviceStats['recibidos'],
                $serviceStats['promedio_texto'],
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Tránsito a recepción';
    }
}
