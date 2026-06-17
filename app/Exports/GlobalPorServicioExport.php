<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class GlobalPorServicioExport implements FromView, ShouldAutoSize, WithTitle, WithEvents
{
    public function __construct(
        private readonly array $reportData
    ) {
    }

    public function view(): View
    {
        return view('reportes.global-por-servicio-excel', $this->reportData);
    }

    public function title(): string
    {
        return 'Global por servicio';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $sheet->getHighestDataColumn();
                $highestRow = max(1, (int) $sheet->getHighestDataRow());
                $headerRow = $this->findRowByFirstCell($sheet, '#') ?? 1;
                $dataStartRow = $headerRow + 1;
                $reportHeaderRange = "A1:{$lastColumn}1";
                $metaRange = "A2:{$lastColumn}6";
                $dataRange = "A{$headerRow}:{$lastColumn}{$highestRow}";

                $sheet->getStyle($reportHeaderRange)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 15,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '0F4C81'],
                    ],
                ]);
                $sheet->getStyle($metaRange)->applyFromArray([
                    'font' => [
                        'color' => ['rgb' => '334155'],
                        'size' => 10,
                    ],
                ]);
                $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1D5D8F'],
                    ],
                ]);
                $sheet->getStyle($dataRange)
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()
                    ->setRGB('D6DCE4');

                if ($highestRow >= $dataStartRow) {
                    $sheet->setAutoFilter($dataRange);
                    for ($row = $dataStartRow; $row <= $highestRow; $row++) {
                        $color = (($row - $dataStartRow) % 2 === 0) ? 'F8FBFE' : 'EEF5FB';
                        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => $color],
                            ],
                        ]);
                    }
                }

                foreach (['C', 'D', 'E', 'F', 'G', 'H', 'I'] as $column) {
                    $sheet->getStyle("{$column}{$dataStartRow}:{$column}{$highestRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                $sheet->getStyle("F{$dataStartRow}:F{$highestRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.000');
                $sheet->getStyle("G{$dataStartRow}:G{$highestRow}")
                    ->getNumberFormat()
                    ->setFormatCode('"Bs" #,##0.00');

                foreach ([
                    'A' => 8,
                    'B' => 22,
                    'C' => 12,
                    'D' => 12,
                    'E' => 14,
                    'F' => 12,
                    'G' => 14,
                    'H' => 22,
                    'I' => 28,
                    'J' => 18,
                ] as $column => $width) {
                    $sheet->getColumnDimension($column)->setAutoSize(false);
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
            },
        ];
    }

    private function findRowByFirstCell(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $value): ?int
    {
        $highest = max(1, (int) $sheet->getHighestDataRow());
        for ($row = 1; $row <= $highest; $row++) {
            if (trim((string) $sheet->getCell("A{$row}")->getValue()) === $value) {
                return $row;
            }
        }

        return null;
    }
}
