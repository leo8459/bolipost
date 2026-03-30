<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class AreaContratosEntregadosResumenSheetExport implements FromArray, ShouldAutoSize, WithCustomStartCell, WithTitle, WithEvents
{
    public function __construct(
        private readonly Collection $rows,
        private readonly array $filters = []
    ) {
    }

    public function array(): array
    {
        $grouped = $this->rows
            ->groupBy(fn ($row) => $this->normalizeOrigin((string) ($row->origen ?? '')))
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE);

        $data = [];
        foreach ($grouped as $origin => $items) {
            $peso = (float) $items->sum('peso');
            $guias = $items->count();
            $subtotal = (float) $items->sum('precio');

            $data[] = [
                $origin,
                $peso,
                $guias,
                $subtotal,
            ];
        }

        return $data;
    }

    public function title(): string
    {
        return 'RESUMEN';
    }

    public function startCell(): string
    {
        return 'C10';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $dataRows = max(1, $this->rows->groupBy(fn ($row) => $this->normalizeOrigin((string) ($row->origen ?? '')))->count());
                $headerImagePath = $this->resolveHeaderImagePath();

                if ($headerImagePath !== null) {
                    $drawing = new Drawing();
                    $drawing->setName('Encabezado Contratos');
                    $drawing->setDescription('Encabezado contratos');
                    $drawing->setPath($headerImagePath);
                    $drawing->setCoordinates('B1');
                    $drawing->setHeight(82);
                    $drawing->setWorksheet($sheet);
                }

                $sheet->mergeCells('C5:F5');
                $sheet->mergeCells('C6:F6');
                $sheet->mergeCells('C7:D7');
                $sheet->mergeCells('E7:F7');
                $sheet->mergeCells('C8:C9');
                $sheet->mergeCells('D8:D9');
                $sheet->mergeCells('E8:E9');
                $sheet->mergeCells('F8:F9');

                $sheet->setCellValue('C5', $this->resolveHeaderTitle());
                $sheet->setCellValue('C6', $this->resolvePeriodLabel());
                $sheet->setCellValue('C7', 'RESUMEN POR REGIONAL');
                $sheet->setCellValue('E7', 'TOTALES');
                $sheet->setCellValue('C8', 'REGIONALES');
                $sheet->setCellValue('D8', 'PESO');
                $sheet->setCellValue('E8', 'GUIAS');
                $sheet->setCellValue('F8', 'TOTAL');

                $startRow = 10;
                $endRow = $startRow + $dataRows - 1;

                $sheet->getDefaultRowDimension()->setRowHeight(22);
                $sheet->getRowDimension(1)->setRowHeight(64);
                $sheet->getRowDimension(2)->setRowHeight(8);
                $sheet->getRowDimension(3)->setRowHeight(8);
                $sheet->getRowDimension(5)->setRowHeight(28);
                $sheet->getRowDimension(6)->setRowHeight(24);
                $sheet->getRowDimension(7)->setRowHeight(24);
                $sheet->getRowDimension(8)->setRowHeight(26);

                $sheet->getStyle('C5:F6')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 13,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1F4E78'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle('C7:F9')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '5A7D2B'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                $sheet->getStyle('C5:F9')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                if ($dataRows > 0) {
                    $sheet->getStyle("C{$startRow}:F{$endRow}")->applyFromArray([
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                    $sheet->getStyle("C{$startRow}:F{$endRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    $sheet->getStyle("C{$startRow}:F{$endRow}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setRGB('F8FAFC');
                    $sheet->getStyle("D{$startRow}:D{$endRow}")->getNumberFormat()->setFormatCode('#,##0.000');
                    $sheet->getStyle("F{$startRow}:F{$endRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                }

                $totalPesoRow = $endRow + 1;
                $totalGuiasRow = $endRow + 2;
                $totalFinalRow = $endRow + 3;
                $sonRow = $endRow + 4;
                $pesoTotal = (float) $this->rows->sum('peso');
                $guiasTotal = (int) $this->rows->count();
                $montoTotal = (float) $this->rows->sum('precio');

                $sheet->setCellValue("C{$totalPesoRow}", 'TOTAL PESO');
                $sheet->setCellValue("D{$totalPesoRow}", $pesoTotal);
                $sheet->setCellValue("C{$totalGuiasRow}", 'TOTAL GUIAS');
                $sheet->setCellValue("E{$totalGuiasRow}", $guiasTotal);
                $sheet->setCellValue("C{$totalFinalRow}", 'TOTAL FINAL BOLIVIANOS');
                $sheet->setCellValue("F{$totalFinalRow}", $montoTotal);
                $sheet->mergeCells("C{$sonRow}:F{$sonRow}");
                $sheet->setCellValue("C{$sonRow}", 'SON: ' . $this->numberToWords($montoTotal));

                $sheet->getStyle("C{$totalPesoRow}:F{$totalFinalRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("C{$totalPesoRow}:F{$totalFinalRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E8F1D9'],
                    ],
                ]);
                $sheet->getStyle("D{$totalPesoRow}")->getNumberFormat()->setFormatCode('#,##0.000');
                $sheet->getStyle("E{$totalGuiasRow}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("F{$totalFinalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("C{$sonRow}:F{$sonRow}")->applyFromArray([
                    'font' => [
                        'italic' => true,
                        'bold' => true,
                        'color' => ['rgb' => '3A3A3A'],
                    ],
                    'alignment' => [
                        'wrapText' => true,
                    ],
                ]);

                $realizadoRow = $sonRow + 4;
                $lineaRow = $sonRow + 5;
                $nombreRow = $sonRow + 6;
                $cargoRow = $sonRow + 7;
                $institucionRow = $sonRow + 8;

                $sheet->setCellValue("B{$realizadoRow}", 'REALIZADO POR:');
                $sheet->setCellValue("G{$realizadoRow}", 'REVISADO POR:');
                $sheet->mergeCells("B{$lineaRow}:D{$lineaRow}");
                $sheet->mergeCells("G{$lineaRow}:I{$lineaRow}");
                $sheet->setCellValue("B{$nombreRow}", $this->resolveLoggedUserName());
                $sheet->setCellValue("G{$nombreRow}", 'Lucy Tinta Torrez');
                $sheet->setCellValue("B{$cargoRow}", 'AGENCIA BOLIVIANA DE CORREOS');
                $sheet->setCellValue("G{$cargoRow}", 'ENCARGADO DE CONTRATOS');
                $sheet->setCellValue("B{$institucionRow}", 'AGENCIA BOLIVIANA DE CORREOS');
                $sheet->setCellValue("G{$institucionRow}", 'AGENCIA BOLIVIANA DE CORREOS');

                $sheet->getStyle("B{$realizadoRow}:I{$institucionRow}")->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);
                $sheet->getStyle("B{$realizadoRow}:I{$realizadoRow}")->getFont()->setBold(true);
                $sheet->getStyle("B{$lineaRow}:I{$lineaRow}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("B{$nombreRow}:I{$institucionRow}")->getFont()->getColor()->setRGB('4A5568');

                foreach (range('A', 'J') as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }
            },
        ];
    }

    private function resolveHeaderTitle(): string
    {
        $empresa = $this->filters['empresa'] ?? null;
        $nombre = trim((string) ($empresa->nombre ?? 'TODAS LAS EMPRESAS'));

        return 'CLIENTE ' . $nombre . ' EXPRESS MAIL SERVICE-EMS';
    }

    private function resolvePeriodLabel(): string
    {
        $from = trim((string) ($this->filters['from'] ?? ''));
        if ($from !== '') {
            try {
                return strtoupper(Carbon::parse($from)->locale('es')->translatedFormat('F'));
            } catch (\Throwable) {
                return strtoupper($from);
            }
        }

        return 'TODO EL PERIODO';
    }

    private function resolveLoggedUserName(): string
    {
        $user = $this->filters['logged_user'] ?? null;
        $name = trim((string) ($user->name ?? 'USUARIO DEL SISTEMA'));

        return $name !== '' ? $name : 'USUARIO DEL SISTEMA';
    }

    private function normalizeOrigin(string $origin): string
    {
        $origin = trim($origin);
        return $origin !== '' ? $origin : 'SIN ORIGEN';
    }

    private function numberToWords(float $amount): string
    {
        $formatted = number_format($amount, 2, '.', '');
        return strtoupper($formatted . ' BOLIVIANOS');
    }

    private function resolveHeaderImagePath(): ?string
    {
        $path = public_path('images/encabezado_contratos.jpeg');

        return is_file($path) ? $path : null;
    }
}
