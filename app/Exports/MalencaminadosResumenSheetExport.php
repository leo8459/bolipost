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

class MalencaminadosResumenSheetExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithEvents, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private readonly array $reportData
    ) {
    }

    public function collection(): Collection
    {
        return collect($this->reportData['resumen'] ?? [])
            ->values()
            ->map(fn ($row, int $index) => [
                'puesto' => $index + 1,
                'departamento' => (string) ($row->departamento ?? ''),
                'total_envios' => (int) ($row->total_envios ?? 0),
                'malencaminados' => (int) ($row->total_registros ?? 0),
                'porcentaje_error' => (float) ($row->porcentaje_error ?? 0),
                'total_malencaminamientos' => (int) ($row->total_malencaminamientos ?? 0),
                'errores_ems' => (int) ($row->ems ?? 0),
                'errores_contratos' => (int) ($row->contratos ?? 0),
                'errores_certificados' => (int) ($row->certificados ?? 0),
                'errores_ordinarios' => (int) ($row->ordinarios ?? 0),
                'envios_ems' => (int) ($row->envios_ems ?? 0),
                'envios_contratos' => (int) ($row->envios_contratos ?? 0),
                'envios_certificados' => (int) ($row->envios_certificados ?? 0),
                'envios_ordinarios' => (int) ($row->envios_ordinarios ?? 0),
                'fecha_inicio' => (string) ($this->reportData['fechaInicio'] ?? ''),
                'fecha_fin' => (string) ($this->reportData['fechaFin'] ?? ''),
                'departamento_filtro' => (string) ($this->reportData['departamento'] ?? 'TODOS'),
                'emitido_en' => now()->format('Y-m-d H:i:s'),
            ]);
    }

    public function headings(): array
    {
        return [
            'Puesto',
            'Departamento',
            'Total envios',
            'Malencaminados',
            'Porcentaje error %',
            'Total malencaminamientos',
            'Errores EMS',
            'Errores contratos',
            'Errores certificados',
            'Errores ordinarios',
            'Envios EMS',
            'Envios contratos',
            'Envios certificados',
            'Envios ordinarios',
            'Fecha inicio',
            'Fecha fin',
            'Departamento filtro',
            'Emitido en',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_NUMBER,
            'C' => NumberFormat::FORMAT_NUMBER,
            'D' => NumberFormat::FORMAT_NUMBER,
            'E' => NumberFormat::FORMAT_NUMBER_00,
            'F' => NumberFormat::FORMAT_NUMBER,
            'G' => NumberFormat::FORMAT_NUMBER,
            'H' => NumberFormat::FORMAT_NUMBER,
            'I' => NumberFormat::FORMAT_NUMBER,
            'J' => NumberFormat::FORMAT_NUMBER,
            'K' => NumberFormat::FORMAT_NUMBER,
            'L' => NumberFormat::FORMAT_NUMBER,
            'M' => NumberFormat::FORMAT_NUMBER,
            'N' => NumberFormat::FORMAT_NUMBER,
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
                $sheet->setAutoFilter('A1:R1');
            },
        ];
    }

    public function title(): string
    {
        return 'Resumen departamentos';
    }
}
