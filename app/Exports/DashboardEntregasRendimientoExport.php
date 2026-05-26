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
        private readonly array $reportData
    ) {
    }

    public function collection(): Collection
    {
        return collect($this->reportData['entregadores'] ?? [])
            ->values()
            ->map(function ($item, int $index) {
                return [
                    'puesto' => $index + 1,
                    'usuario_id' => (int) ($item->id ?? 0),
                    'entregador' => (string) ($item->name ?? ''),
                    'departamento_cartero' => (string) (($item->ciudad ?? '') ?: 'SIN DEPARTAMENTO'),
                    'total_asignados' => (int) ($item->total_asignados ?? 0),
                    'entregados_por_cartero' => (int) ($item->total_cartero_entregados ?? 0),
                    'entregados_por_ventanilla' => (int) ($item->total_ventanilla ?? 0),
                    'total_entregados' => (int) ($item->total_entregados ?? 0),
                    'pendientes_asignados' => (int) ($item->pendientes_asignados ?? 0),
                    'cumplimiento_asignados_porcentaje' => (float) ($item->cumplimiento_asignados ?? 0),
                    'ems_entregados' => (int) ($item->ems ?? 0),
                    'contratos_entregados' => (int) ($item->contrato ?? 0),
                    'certificados_entregados' => (int) ($item->certi ?? 0),
                    'ordinarios_entregados' => (int) ($item->ordi ?? 0),
                    'ems_ventanilla' => (int) ($item->ventanilla_ems ?? 0),
                    'contratos_ventanilla' => (int) ($item->ventanilla_contrato ?? 0),
                    'certificados_ventanilla' => (int) ($item->ventanilla_certi ?? 0),
                    'ordinarios_ventanilla' => (int) ($item->ventanilla_ordi ?? 0),
                    'ems_asignados' => (int) ($item->asignado_ems ?? 0),
                    'contratos_asignados' => (int) ($item->asignado_contrato ?? 0),
                    'certificados_asignados' => (int) ($item->asignado_certi ?? 0),
                    'ordinarios_asignados' => (int) ($item->asignado_ordi ?? 0),
                    'servicio_mas_entregado' => (string) ($item->servicio_mas_entregado ?? 'SIN DATOS'),
                    'entregas_servicio_mas_entregado' => (int) ($item->servicio_mas_entregado_total ?? 0),
                    'rango' => (string) ($this->reportData['rangoLabel'] ?? ''),
                    'fecha_desde' => (string) ($this->reportData['rangoDesde'] ?? ''),
                    'fecha_hasta' => (string) ($this->reportData['rangoHasta'] ?? ''),
                    'departamento_filtro' => (string) (($this->reportData['departamentoCartero'] ?? '') ?: 'TODOS'),
                    'modulos' => collect($this->reportData['modulosSeleccionados'] ?? [])
                        ->map(fn ($key) => $this->reportData['modulosDisponibles'][$key]['label'] ?? strtoupper((string) $key))
                        ->implode(', '),
                    'emitido_en' => now()->format('Y-m-d H:i:s'),
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Puesto',
            'Usuario ID',
            'Entregador',
            'Departamento cartero',
            'Total asignados',
            'Entregados por cartero',
            'Entregados por ventanilla',
            'Total entregados',
            'Pendientes asignados',
            'Cumplimiento asignados %',
            'EMS entregados',
            'Contratos entregados',
            'Certificados entregados',
            'Ordinarios entregados',
            'EMS ventanilla',
            'Contratos ventanilla',
            'Certificados ventanilla',
            'Ordinarios ventanilla',
            'EMS asignados',
            'Contratos asignados',
            'Certificados asignados',
            'Ordinarios asignados',
            'Servicio mas entregado',
            'Entregas servicio mas entregado',
            'Rango',
            'Fecha desde',
            'Fecha hasta',
            'Departamento filtro',
            'Modulos',
            'Emitido en',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_NUMBER,
            'B' => NumberFormat::FORMAT_NUMBER,
            'E' => NumberFormat::FORMAT_NUMBER,
            'F' => NumberFormat::FORMAT_NUMBER,
            'G' => NumberFormat::FORMAT_NUMBER,
            'H' => NumberFormat::FORMAT_NUMBER,
            'I' => NumberFormat::FORMAT_NUMBER,
            'J' => NumberFormat::FORMAT_NUMBER_00,
            'K' => NumberFormat::FORMAT_NUMBER,
            'L' => NumberFormat::FORMAT_NUMBER,
            'M' => NumberFormat::FORMAT_NUMBER,
            'N' => NumberFormat::FORMAT_NUMBER,
            'O' => NumberFormat::FORMAT_NUMBER,
            'P' => NumberFormat::FORMAT_NUMBER,
            'Q' => NumberFormat::FORMAT_NUMBER,
            'R' => NumberFormat::FORMAT_NUMBER,
            'S' => NumberFormat::FORMAT_NUMBER,
            'T' => NumberFormat::FORMAT_NUMBER,
            'U' => NumberFormat::FORMAT_NUMBER,
            'V' => NumberFormat::FORMAT_NUMBER,
            'X' => NumberFormat::FORMAT_NUMBER,
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
                $sheet->setAutoFilter('A1:AD1');
            },
        ];
    }

    public function title(): string
    {
        return 'Rendimiento entregas';
    }
}
