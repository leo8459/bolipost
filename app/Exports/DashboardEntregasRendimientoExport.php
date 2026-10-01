<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DashboardEntregasRendimientoExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithEvents, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private readonly array $reportData,
        private readonly string $sheetTitle = 'Rendimiento entregas'
    ) {
    }

    public function collection(): Collection
    {
        return collect($this->reportData['entregadores'] ?? [])
            ->values()
            ->map(function ($item, int $index) {
                return [
                    'puesto' => $index + 1,
                    'entregador' => (string) ($item->name ?? ''),
                    'total_asignados' => (int) ($item->total_asignados ?? 0),
                    'entregados_por_cartero' => (int) ($item->total_cartero_entregados ?? 0),
                    'entregados_por_ventanilla' => (int) ($item->total_ventanilla ?? 0),
                    'total_entregados' => (int) ($item->total_entregados ?? 0),
                    'promedio_diario' => (float) ($item->promedio_diario ?? 0),
                    'pendientes_asignados' => (int) ($item->pendientes_asignados ?? 0),
                    'cumplimiento_asignados_porcentaje' => min(100.0, max(0.0, (float) ($item->cumplimiento_asignados ?? 0))),
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Puesto',
            'Cartero',
            'Total asignados',
            'Entrega física',
            'Ventanilla',
            'Total entregados',
            'Promedio diario (lunes a sabado)',
            'Pendientes asignados',
            'Cumplimiento (incluye ventanilla) %',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_NUMBER,
            'C' => NumberFormat::FORMAT_NUMBER,
            'D' => NumberFormat::FORMAT_NUMBER,
            'E' => NumberFormat::FORMAT_NUMBER,
            'F' => NumberFormat::FORMAT_NUMBER,
            'G' => NumberFormat::FORMAT_NUMBER_00,
            'H' => NumberFormat::FORMAT_NUMBER,
            'I' => NumberFormat::FORMAT_NUMBER_00,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F5FAE'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');
                $sheet->setAutoFilter('A1:I1');
            },
        ];
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }
}
