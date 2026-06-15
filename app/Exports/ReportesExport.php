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
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

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
                $dataRows = $this->resolveDataRowsCount();
                $lastDataRow = $headerRow + max($dataRows, 1);
                $moduleTitleRow = $this->findRowByFirstCell($sheet, 'RESUMEN POR MODULO');
                $serviceTitleRow = $this->findRowByFirstCell($sheet, 'RESUMEN POR SERVICIO');
                $lastColumn = $sheet->getHighestDataColumn();
                $lastColumnIndex = Coordinate::columnIndexFromString($lastColumn);
                $dataRange = "A{$headerRow}:{$lastColumn}{$lastDataRow}";
                $reportHeaderRange = "A1:{$lastColumn}1";
                $reportMetaRange = "A2:{$lastColumn}5";
                $summaryRow = 8;
                $summaryRange = 'A' . $summaryRow . ':F' . ($summaryRow + 1);
                $moduleHeaderRow = $moduleTitleRow ? $moduleTitleRow + 1 : null;
                $serviceHeaderRow = $serviceTitleRow ? $serviceTitleRow + 1 : null;
                $moduleDataStartRow = $moduleHeaderRow ? $moduleHeaderRow + 1 : null;
                $serviceDataStartRow = $serviceHeaderRow ? $serviceHeaderRow + 1 : null;
                $moduleDataEndRow = $moduleTitleRow && $serviceTitleRow ? max($moduleDataStartRow ?? 0, $serviceTitleRow - 2) : null;
                $serviceDataEndRow = $serviceTitleRow ? max($serviceDataStartRow ?? 0, $headerRow - 2) : null;

                // Tabla principal filtrable (como Excel nativo).
                if ($dataRows > 0) {
                    $sheet->setAutoFilter($dataRange);
                }
                $sheet->getSheetView()->setZoomScale(90);
                $sheet->setSelectedCell('A1');

                $sheet->getStyle($reportHeaderRange)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '0F4C81'],
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(26);
                $sheet->getStyle($reportMetaRange)->applyFromArray([
                    'font' => [
                        'color' => ['rgb' => '334155'],
                        'size' => 11,
                    ],
                    'alignment' => [
                        'wrapText' => true,
                        'vertical' => Alignment::VERTICAL_TOP,
                    ],
                ]);

                foreach ([$moduleTitleRow, $serviceTitleRow] as $titleRow) {
                    if ($titleRow) {
                        $sheet->getStyle("A{$titleRow}:{$lastColumn}{$titleRow}")->applyFromArray([
                            'font' => [
                                'bold' => true,
                                'size' => 12,
                                'color' => ['rgb' => '0F4C81'],
                            ],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'DCEAF7'],
                            ],
                        ]);
                        $sheet->getRowDimension($titleRow)->setRowHeight(22);
                    }
                }

                foreach ([$moduleTitleRow ? $moduleTitleRow + 1 : null, $serviceTitleRow ? $serviceTitleRow + 1 : null] as $summaryHeaderRow) {
                    if ($summaryHeaderRow) {
                        $summaryLastColumn = min(6, $lastColumnIndex);
                        $summaryRange = 'A' . $summaryHeaderRow . ':' . Coordinate::stringFromColumnIndex($summaryLastColumn) . $summaryHeaderRow;
                        $sheet->getStyle($summaryRange)->applyFromArray([
                            'font' => [
                                'bold' => true,
                                'color' => ['rgb' => '1F2937'],
                            ],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'D9EAF7'],
                            ],
                        ]);
                        $sheet->getStyle($summaryRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                }

                $sheet->getStyle($summaryRange)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => '0F172A'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E2EEF9'],
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'B6C8D9'],
                        ],
                    ],
                ]);
                $sheet->getStyle("A{$summaryRow}:F{$summaryRow}")->getFont()->setSize(11);
                $sheet->getStyle('A' . ($summaryRow + 1) . ':F' . ($summaryRow + 1))->getFont()->setSize(12);

                $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1D5D8F'],
                    ],
                ]);
                $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->getStyle($dataRange)
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()
                    ->setRGB('D6DCE4');

                $sheet->getStyle('Q' . ($headerRow + 1) . ":Q{$lastDataRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.000');
                $sheet->getStyle('R' . ($headerRow + 1) . ":R{$lastDataRow}")
                    ->getNumberFormat()
                    ->setFormatCode('"Bs" #,##0.00');
                $sheet->getStyle('V' . ($headerRow + 1) . ":V{$lastDataRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');

                $sheet->getStyle($dataRange)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle("D" . ($headerRow + 1) . ":P{$lastDataRow}")->getAlignment()->setWrapText(true);
                $sheet->getStyle("A" . ($headerRow + 1) . ":A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("Q" . ($headerRow + 1) . ":R{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("V" . ($headerRow + 1) . ":V{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("E" . ($headerRow + 1) . ":F{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("S" . ($headerRow + 1) . ":V{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getRowDimension($headerRow)->setRowHeight(22);
                $this->applyAlternatingRowFill($sheet, $headerRow + 1, $lastDataRow, $lastColumn, 'F8FBFE', 'EEF5FB');
                $this->applyAlternatingRowFill($sheet, $moduleDataStartRow, $moduleDataEndRow, 'F', 'FFFFFF', 'F8FBFE');
                $this->applyAlternatingRowFill($sheet, $serviceDataStartRow, $serviceDataEndRow, 'D', 'FFFFFF', 'F8FBFE');

                if ($dataRows > 0) {
                    $sheet->getStyle("Q" . ($headerRow + 1) . ":R{$lastDataRow}")->applyFromArray([
                        'font' => ['bold' => true],
                    ]);
                }

                foreach ([
                    'A' => 8,
                    'B' => 15,
                    'C' => 18,
                    'D' => 20,
                    'E' => 16,
                    'F' => 16,
                    'G' => 18,
                    'H' => 20,
                    'I' => 24,
                    'J' => 26,
                    'K' => 22,
                    'L' => 22,
                    'M' => 26,
                    'N' => 18,
                    'O' => 22,
                    'P' => 24,
                    'Q' => 12,
                    'R' => 14,
                    'S' => 18,
                    'T' => 18,
                    'U' => 18,
                    'V' => 14,
                ] as $column => $width) {
                    $sheet->getColumnDimension($column)->setAutoSize(false);
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
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

    private function applyAlternatingRowFill(
        Worksheet $sheet,
        ?int $startRow,
        ?int $endRow,
        string $lastColumn,
        string $oddColor,
        string $evenColor
    ): void {
        if (!$startRow || !$endRow || $endRow < $startRow) {
            return;
        }

        for ($row = $startRow; $row <= $endRow; $row++) {
            $color = (($row - $startRow) % 2 === 0) ? $oddColor : $evenColor;
            $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $color],
                ],
            ]);
        }
    }
}
