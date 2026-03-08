<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReportesExport implements FromView, ShouldAutoSize, WithTitle, WithEvents
{
    public function __construct(
        private readonly array $reportData
    ) {
    }

    public function view(): View
    {
        return view('reportes.report-excel', $this->reportData);
    }

    public function title(): string
    {
        return 'Reportes';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $headerRow = $this->resolveDataHeaderRow($sheet);
                $dataRows = max(1, $this->resolveDataRowsCount());
                $lastDataRow = $headerRow + $dataRows;

                // Tabla principal filtrable (como Excel nativo).
                $sheet->setAutoFilter("A{$headerRow}:O{$lastDataRow}");
                $sheet->freezePane('A' . ($headerRow + 1));

                // Estilo de cabecera para hacerlo mas amigable y legible.
                $sheet->getStyle("A{$headerRow}:O{$headerRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1F4E78'],
                    ],
                ]);

                $sheet->getStyle("A{$headerRow}:O{$lastDataRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()
                    ->setRGB('D6DCE4');

                $sheet->getStyle('L' . ($headerRow + 1) . ":L{$lastDataRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.000');
                $sheet->getStyle('M' . ($headerRow + 1) . ":M{$lastDataRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');

                $sheet->getRowDimension($headerRow)->setRowHeight(22);
            },
        ];
    }

    private function resolveDataRowsCount(): int
    {
        $rows = $this->reportData['rows'] ?? [];
        if ($rows instanceof \Illuminate\Support\Collection) {
            return $rows->count();
        }

        if (is_array($rows)) {
            return count($rows);
        }

        return 0;
    }

    private function resolveDataHeaderRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): int
    {
        $highest = max(1, (int) $sheet->getHighestDataRow());
        for ($row = 1; $row <= $highest; $row++) {
            if (trim((string) $sheet->getCell("A{$row}")->getValue()) === '#') {
                return $row;
            }
        }

        return 1;
    }
}
