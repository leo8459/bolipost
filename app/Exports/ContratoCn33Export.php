<?php

namespace App\Exports;

use App\Models\Recojo;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class ContratoCn33Export implements FromView, WithEvents, WithTitle
{
    public function __construct(
        private readonly Recojo $contrato,
        private readonly CarbonInterface $generatedAt,
        private readonly string $verificationUrl
    ) {
    }

    public function view(): View
    {
        return view('paquetes_contrato.reporte-excel', [
            'contrato' => $this->contrato,
            'generatedAt' => $this->generatedAt,
            'verificationUrl' => $this->verificationUrl,
        ]);
    }

    public function title(): string
    {
        return 'CN-33';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestDataRow();

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
                    ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
                    ->setFitToWidth(1)
                    ->setFitToHeight(1);

                $sheet->getPageMargins()
                    ->setTop(0.2)
                    ->setRight(0.2)
                    ->setBottom(0.2)
                    ->setLeft(0.2);

                foreach (range('A', 'H') as $column) {
                    $sheet->getColumnDimension($column)->setWidth(14);
                }

                for ($row = 1; $row <= $highestRow; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(18);
                }

                foreach ([1, 18, 35] as $startRow) {
                    $endRow = $startRow + 14;

                    $sheet->mergeCells("A{$startRow}:B{$startRow}");
                    $sheet->mergeCells("C{$startRow}:F{$startRow}");
                    $sheet->mergeCells("G{$startRow}:H{$startRow}");
                    $sheet->mergeCells('A' . ($startRow + 1) . ':H' . ($startRow + 1));
                    $sheet->mergeCells('A' . ($startRow + 2) . ':D' . ($startRow + 2));
                    $sheet->mergeCells('E' . ($startRow + 2) . ':H' . ($startRow + 2));
                    $sheet->mergeCells('A' . ($startRow + 4) . ':D' . ($startRow + 4));
                    $sheet->mergeCells('E' . ($startRow + 4) . ':H' . ($startRow + 4));
                    $sheet->mergeCells('A' . ($startRow + 6) . ':D' . ($startRow + 6));
                    $sheet->mergeCells('E' . ($startRow + 6) . ':H' . ($startRow + 6));
                    $sheet->mergeCells('A' . ($startRow + 8) . ':D' . ($startRow + 8));
                    $sheet->mergeCells('E' . ($startRow + 8) . ':F' . ($startRow + 8));
                    $sheet->mergeCells('G' . ($startRow + 8) . ':H' . ($startRow + 8));
                    $sheet->mergeCells('A' . ($startRow + 9) . ':H' . ($startRow + 9));
                    $sheet->mergeCells('A' . ($startRow + 11) . ':H' . ($startRow + 11));
                    $sheet->mergeCells('A' . ($startRow + 13) . ':C' . ($startRow + 13));
                    $sheet->mergeCells('D' . ($startRow + 13) . ':F' . ($startRow + 13));
                    $sheet->mergeCells('G' . ($startRow + 13) . ':H' . ($startRow + 13));

                    $sheet->getStyle("A{$startRow}:H{$endRow}")->applyFromArray([
                        'font' => [
                            'name' => 'Verdana',
                            'size' => 8,
                        ],
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_TOP,
                            'wrapText' => true,
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => '000000'],
                            ],
                            'outline' => [
                                'borderStyle' => Border::BORDER_MEDIUM,
                                'color' => ['rgb' => '000000'],
                            ],
                        ],
                    ]);

                    $sheet->getStyle("A{$startRow}:H{$startRow}")->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 10,
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F2F2F2'],
                        ],
                    ]);

                    $sheet->getStyle('A' . ($startRow + 1) . ':H' . ($startRow + 1))->getFont()->setBold(true)->setSize(11);
                    $sheet->getStyle('A' . ($startRow + 1) . ':H' . ($startRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('A' . ($startRow + 9) . ':H' . ($startRow + 11))->getFont()->setSize(7);
                    $sheet->getStyle('A' . ($startRow + 13) . ':H' . ($startRow + 13))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getRowDimension($startRow)->setRowHeight(24);
                    $sheet->getRowDimension($startRow + 9)->setRowHeight(30);
                    $sheet->getRowDimension($startRow + 11)->setRowHeight(28);
                }
            },
        ];
    }
}
