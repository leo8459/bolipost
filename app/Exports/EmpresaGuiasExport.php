<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class EmpresaGuiasExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping
{
    private int $sequence = 0;

    public function __construct(private readonly Collection $rows)
    {
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Nº',
            'Fecha de registro',
            'Código de guía',
            'Código madre',
            'CN-33',
            'Empresa',
            'Código cliente',
            'Origen',
            'Destino',
            'Remitente',
            'Destinatario',
            'Estado',
            'Cantidad',
            'Peso',
            'Precio',
        ];
    }

    public function map($row): array
    {
        $empresa = $row->empresa ?? $row->user?->empresa;

        return [
            ++$this->sequence,
            $row->created_at?->format('d/m/Y H:i') ?? '',
            (string) ($row->codigo ?? ''),
            (string) ($row->codigo_madre ?? ''),
            (string) ($row->cod_especial ?? ''),
            (string) ($empresa?->nombre ?? ''),
            (string) ($empresa?->codigo_cliente ?? ''),
            (string) ($row->origen ?? ''),
            (string) ($row->destino ?? ''),
            (string) ($row->nombre_r ?? ''),
            (string) ($row->nombre_d ?? ''),
            (string) ($row->estadoRegistro?->nombre_estado ?? ''),
            (int) ($row->cantidad ?? 0),
            (float) ($row->peso ?? 0),
            (float) ($row->precio ?? 0),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(1, $sheet->getHighestRow());

                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:O{$lastRow}");
                $sheet->getStyle('A1:O1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '20539A'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                if ($lastRow > 1) {
                    $sheet->getStyle("N2:O{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                }
            },
        ];
    }
}
