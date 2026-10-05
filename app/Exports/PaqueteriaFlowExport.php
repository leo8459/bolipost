<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PaqueteriaFlowExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(
        private readonly array $reportData
    ) {
    }

    public function headings(): array
    {
        return ['Tipo', 'Mes / periodo', 'Detalle', 'Guías', 'Paquetes EMS', 'Peso (kg)'];
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = [
            'Cobertura',
            $this->reportData['periodLabel'],
            'Departamentos de origen: '.$this->reportData['departmentLabel'],
            null,
            null,
            null,
        ];

        foreach ($this->reportData['months'] as $month) {
            $monthName = $month['nombre'];
            $rows[] = ['Admisión', $monthName, 'Guías de contratos', $month['guias_contrato'], null, null];
            $rows[] = ['Admisión', $monthName, 'Guías EMS', $month['guias_ems'], null, null];
            $rows[] = ['EMS', $monthName, 'Paquetes EMS', null, $month['paquetes_ems'], $month['peso_ems']];
            $rows[] = ['CN-33 (bitácora)', $monthName, 'Aéreo: BOA / BOA Cargo / Boliviana de Aviación', null, null, $month['transporte']['aereo']];
            $rows[] = ['CN-33 (bitácora)', $monthName, 'Terrestre: otras transportadoras', null, null, $month['transporte']['terrestre']];
        }

        foreach ($this->reportData['companyRows'] as $company) {
            foreach ($company['meses'] as $monthNumber => $values) {
                $monthName = $this->reportData['months'][$monthNumber]['nombre'];
                $rows[] = ['Empresa', $monthName, $company['empresa'], $values['guias'], null, $values['peso']];
            }
            $rows[] = [
                'Empresa - acumulado',
                $this->reportData['periodLabel'],
                $company['empresa'],
                $company['guias_total'],
                null,
                $company['peso_total'],
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Flujo paquetería';
    }
}
