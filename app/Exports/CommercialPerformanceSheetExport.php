<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CommercialPerformanceSheetExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $sheetTitle,
        private readonly array $headings,
        private readonly array $rows
    ) {
    }

    public function collection(): Collection
    {
        return collect($this->rows);
    }

    public function headings(): array
    {
        return $this->headings;
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
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $sheet->getHighestDataColumn();
                $highestRow = max(1, (int) $sheet->getHighestDataRow());

                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:{$lastColumn}{$highestRow}");
            },
        ];
    }

    public function title(): string
    {
        return mb_substr($this->sheetTitle, 0, 31);
    }
}
