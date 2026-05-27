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

class MalencaminadosDetalleSheetExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithEvents, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private readonly array $reportData
    ) {
    }

    public function collection(): Collection
    {
        return collect($this->reportData['detalle'] ?? [])
            ->values()
            ->map(fn ($row) => [
                'id' => (int) ($row->id ?? 0),
                'codigo' => (string) ($row->codigo ?? ''),
                'tipo' => (string) ($row->tipo ?? ''),
                'departamento_origen' => (string) ($row->departamento_origen ?? ''),
                'destino_anterior' => (string) ($row->destino_anterior ?? ''),
                'destino_nuevo' => (string) ($row->destino_nuevo ?? ''),
                'malencaminamiento_nro' => (int) ($row->malencaminamiento ?? 0),
                'usuario_creador_guia' => (string) ($row->usuario_creador_guia ?? '-'),
                'departamento_usuario_creador' => (string) ($row->departamento_usuario_creador ?? '-'),
                'usuario_reporto_malencaminado' => (string) ($row->usuario_reporto_malencaminado ?? 'SIN DATO'),
                'observacion' => (string) ($row->observacion ?? ''),
                'fecha_registro' => (string) ($row->created_at ?? ''),
                'fecha_inicio' => (string) ($this->reportData['fechaInicio'] ?? ''),
                'fecha_fin' => (string) ($this->reportData['fechaFin'] ?? ''),
                'departamento_filtro' => (string) ($this->reportData['departamento'] ?? 'TODOS'),
            ]);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Codigo',
            'Tipo',
            'Departamento origen',
            'Destino anterior',
            'Destino nuevo',
            'Malencaminamiento nro',
            'Usuario creador guia',
            'Departamento usuario creador',
            'Usuario que reporto / mando',
            'Observacion',
            'Fecha registro',
            'Fecha inicio',
            'Fecha fin',
            'Departamento filtro',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_NUMBER,
            'G' => NumberFormat::FORMAT_NUMBER,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0F766E'],
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
                $sheet->setAutoFilter('A1:O1');
            },
        ];
    }

    public function title(): string
    {
        return 'Detalle casos';
    }
}
