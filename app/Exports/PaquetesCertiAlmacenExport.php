<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PaquetesCertiAlmacenExport implements FromCollection, WithColumnFormatting, WithEvents, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private readonly Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'CODIGO',
            'DESTINATARIO',
            'BANDEJA',
            'TELEFONO',
            'CUIDAD',
            'VENTANILLA',
            'PESO',
            'ESTADO',
            'FECHA',
        ];
    }

    public function map($row): array
    {
        return [
            (string) ($row->codigo ?? ''),
            (string) ($row->destinatario ?? ''),
            (string) ($row->zona ?? ''),
            (string) ($row->telefono ?? ''),
            (string) ($row->cuidad ?? ''),
            (string) ($row->ventanillaRef?->nombre_ventanilla ?? $row->ventanilla ?? ''),
            $this->numericOrNull($row->peso ?? null),
            (string) ($row->estado?->nombre_estado ?? 'SIN ESTADO'),
            $this->excelDate($row->created_at ?? null),
        ];
    }

    public function title(): string
    {
        return 'Ventanilla Certificados';
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'G' => '0.###',
            'I' => 'yyyy-mm-dd hh:mm',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(1, $this->rows->count() + 1);

                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:I{$lastRow}");
                $sheet->getStyle("A1:I{$lastRow}")->getFont()
                    ->setName('Calibri')
                    ->setSize(12);
                $sheet->getStyle('A1:I1')->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(22);

                if ($lastRow > 1) {
                    $sheet->getStyle("A2:I{$lastRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                foreach ([
                    'A' => 20,
                    'B' => 48,
                    'C' => 30,
                    'D' => 15,
                    'E' => 14,
                    'F' => 16,
                    'G' => 11,
                    'H' => 16,
                    'I' => 21,
                ] as $column => $width) {
                    $sheet->getColumnDimension($column)->setAutoSize(false);
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
            },
        ];
    }

    private function numericOrNull($value): ?float
    {
        $value = trim((string) $value);

        return $value !== '' && is_numeric($value) ? (float) $value : null;
    }

    private function excelDate($value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return Date::dateTimeToExcel($value instanceof \DateTimeInterface ? $value : Carbon::parse($value));
    }
}
