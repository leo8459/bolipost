<?php

namespace App\Exports;

use App\Support\DeliveryFulfillment;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class DashboardEntregasResumenExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithEvents, WithTitle
{
    public function __construct(
        private readonly array $reportData
    ) {
    }

    public function array(): array
    {
        $departamentos = collect($this->reportData['resumenDepartamentos'] ?? []);
        $carteros = collect($this->reportData['entregadores'] ?? []);
        $totalAsignados = (int) $carteros->sum('total_asignados');
        $totalCarteroEntregados = (int) $carteros->sum('total_cartero_entregados');
        $totalVentanilla = (int) $carteros->sum('total_ventanilla');
        $totalEntregados = (int) $carteros->sum('total_entregados');
        $diasLaborables = (int) ($this->reportData['diasLaborables'] ?? 0);
        $totalPendientes = (int) $carteros->sum('pendientes_asignados');
        $cumplimiento = DeliveryFulfillment::percentage(
            $totalAsignados,
            $totalCarteroEntregados,
            $totalVentanilla
        );

        $rows = [
            ['RESUMEN EJECUTIVO DE ENTREGAS'],
            ['Periodo', (string) ($this->reportData['rangoLabel'] ?? 'Todo el historial')],
            ['Días laborables (lunes a sábado)', $diasLaborables],
            ['Departamento del cartero', (string) (($this->reportData['departamentoCartero'] ?? '') ?: 'Todos')],
            ['Módulos', collect($this->reportData['modulosSeleccionados'] ?? [])
                ->map(fn ($key) => $this->reportData['modulosDisponibles'][$key]['label'] ?? strtoupper((string) $key))
                ->implode(', ')],
            [''],
            [
                'Departamento',
                'Carteros',
                'Asignados',
                'Entrega física',
                'Ventanilla',
                'Total entregados',
                'Promedio diario',
                'Pendientes',
                'Cumplimiento %',
            ],
        ];

        foreach ($departamentos as $departamento) {
            $rows[] = [
                $this->formatDepartment((string) ($departamento['departamento'] ?? '')),
                (int) ($departamento['cantidad_carteros'] ?? 0),
                (int) ($departamento['total_asignados'] ?? 0),
                (int) ($departamento['total_cartero_entregados'] ?? 0),
                (int) ($departamento['total_ventanilla'] ?? 0),
                (int) ($departamento['total_entregados'] ?? 0),
                (float) ($departamento['promedio_diario'] ?? 0),
                (int) ($departamento['pendientes_asignados'] ?? 0),
                (float) ($departamento['cumplimiento'] ?? 0),
            ];
        }

        $rows[] = [
            'TOTAL',
            $carteros->count(),
            $totalAsignados,
            $totalCarteroEntregados,
            $totalVentanilla,
            $totalEntregados,
            $diasLaborables > 0 ? $totalEntregados / $diasLaborables : 0,
            $totalPendientes,
            $cumplimiento,
        ];

        return $rows;
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_NUMBER,
            'C' => NumberFormat::FORMAT_NUMBER,
            'D' => NumberFormat::FORMAT_NUMBER,
            'E' => NumberFormat::FORMAT_NUMBER,
            'F' => NumberFormat::FORMAT_NUMBER,
            'G' => NumberFormat::FORMAT_NUMBER_00,
            'H' => NumberFormat::FORMAT_NUMBER,
            'I' => NumberFormat::FORMAT_NUMBER_00,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $departmentCount = count($this->reportData['resumenDepartamentos'] ?? []);
                $totalRow = 8 + $departmentCount;

                $sheet->freezePane('A8');
                $sheet->setAutoFilter('A7:I' . max(7, 7 + $departmentCount));
                $sheet->getStyle('A1:I1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('123B70');
                $sheet->getStyle('A7:I7')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A7:I7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F5FAE');

                if ($lastRow >= $totalRow) {
                    $sheet->getStyle('A' . $totalRow . ':I' . $totalRow)->getFont()->setBold(true);
                    $sheet->getStyle('A' . $totalRow . ':I' . $totalRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F0FB');
                }
            },
        ];
    }

    public function title(): string
    {
        return 'Resumen ejecutivo';
    }

    private function formatDepartment(string $department): string
    {
        return mb_convert_case(mb_strtolower(trim($department), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }
}
