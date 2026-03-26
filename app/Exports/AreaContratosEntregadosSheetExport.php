<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class AreaContratosEntregadosSheetExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithTitle, WithEvents
{
    public function __construct(
        private readonly string $origin,
        private readonly Collection $rows,
        private readonly array $filters = []
    ) {
    }

    public function collection(): Collection
    {
        return $this->rows->values()->map(function (Model $row, int $index) {
            return [
                'sequence' => $index + 1,
                'row' => $row,
            ];
        });
    }

    public function headings(): array
    {
        return [
            [
                'Nº',
                'FECHA DE ENVIO',
                'NUM. DE ENVIO',
                'ORIGEN',
                '',
                '',
                'DESTINO',
                '',
                '',
                'PIEZA',
                'TIPO DE ENVIO',
                'TARIFARIO',
                '',
                '',
                '',
                'ENTREGA',
                '',
                '',
                '',
                '',
            ],
            [
                '',
                '',
                '',
                'CIUDAD',
                'RURAL',
                'LOCAL',
                'CIUDAD',
                'RURAL',
                'LOCAL',
                '',
                '',
                'PESO',
                'EMS',
                'EXPRESS',
                'TOTAL',
                'FECHA DE ENTREGA',
                'HORA DE ENTREGA',
                'A QUIEN SE ENTREGO',
                'NOMBRE DEL CARTERO',
                'OBSERVACIONES',
            ],
        ];
    }

    public function map($row): array
    {
        if (!is_array($row) || !isset($row['row']) || !$row['row'] instanceof Model) {
            return [];
        }

        /** @var Model $model */
        $model = $row['row'];
        $provincia = trim((string) ($model->provincia ?? ''));
        $precio = (float) ($model->precio ?? 0);

        return [
            (int) ($row['sequence'] ?? 0),
            $this->formatDate($model->fecha_recojo),
            (string) ($model->codigo ?? ''),
            (string) ($model->origen ?? ''),
            '',
            'X',
            (string) ($model->destino ?? ''),
            $provincia,
            $provincia === '' ? 'X' : '',
            1,
            '',
            (float) ($model->peso ?? 0),
            $precio,
            '',
            $precio,
            $this->formatDate($model->updated_at),
            $this->formatTime($model->updated_at),
            (string) ($model->nombre_d ?? ''),
            (string) optional($model->user)->name,
            (string) ($model->observacion ?? ''),
        ];
    }

    public function title(): string
    {
        $clean = preg_replace('/[\[\]\*\/\\\\\?\:]/', ' ', $this->origin) ?? $this->origin;
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? $clean);

        if ($clean === '') {
            $clean = 'SIN ORIGEN';
        }

        return mb_substr($clean, 0, 31);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = 'T';
                $dataCount = $this->rows->count();
                $highestRow = max(1, $sheet->getHighestDataRow());
                $headerImagePath = $this->resolveHeaderImagePath();

                $sheet->insertNewRowBefore(1, 10);

                if ($headerImagePath !== null) {
                    $drawing = new Drawing();
                    $drawing->setName('Encabezado Contratos');
                    $drawing->setDescription('Encabezado contratos');
                    $drawing->setPath($headerImagePath);
                    $drawing->setCoordinates('A1');
                    $drawing->setHeight(78);
                    $drawing->setWorksheet($sheet);
                }

                $sheet->setCellValue('A4', 'REPORTE DE CONTRATOS ENTREGADOS');
                $sheet->setCellValue('A5', 'ORIGEN:');
                $sheet->setCellValue('C5', $this->origin);
                $sheet->setCellValue('A6', 'SERVICIO:');
                $sheet->setCellValue('C6', 'PAQUETES CONTRATO');
                $sheet->setCellValue('A7', 'CLIENTE:');
                $sheet->setCellValue('C7', $this->resolveEmpresaLabel());
                $sheet->setCellValue('A8', 'PERIODO:');
                $sheet->setCellValue('C8', $this->resolveDateRangeLabel());

                $headerTopRow = 11;
                $headerBottomRow = 12;
                $dataStartRow = 13;
                $highestRow += 10;

                $sheet->mergeCells("A4:{$lastColumn}4");
                $sheet->mergeCells('D11:F11');
                $sheet->mergeCells('G11:I11');
                $sheet->mergeCells('L11:O11');
                $sheet->mergeCells('P11:T11');
                $sheet->mergeCells('A11:A12');
                $sheet->mergeCells('B11:B12');
                $sheet->mergeCells('C11:C12');
                $sheet->mergeCells('J11:J12');
                $sheet->mergeCells('K11:K12');

                $sheet->getRowDimension(1)->setRowHeight(60);
                $sheet->getRowDimension(2)->setRowHeight(10);
                $sheet->getRowDimension(3)->setRowHeight(10);

                $sheet->getStyle("A4:A8")->getFont()->setBold(true);
                $sheet->getStyle('A4')->getFont()->setSize(14);
                $sheet->getStyle("A4:{$lastColumn}4")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1F4E78'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);

                $sheet->getStyle("A{$headerTopRow}:O{$headerBottomRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '628B35'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                $sheet->getStyle("P{$headerTopRow}:T{$headerBottomRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '8F4A12'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                $sheet->getRowDimension($headerTopRow)->setRowHeight(24);
                $sheet->getRowDimension($headerBottomRow)->setRowHeight(28);

                if ($dataCount > 0) {
                    $sheet->setAutoFilter("A{$headerBottomRow}:{$lastColumn}{$highestRow}");
                    $sheet->freezePane("A{$dataStartRow}");
                }

                $sheet->getStyle("A{$headerTopRow}:{$lastColumn}{$highestRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()
                    ->setRGB('3A3A3A');

                if ($dataCount > 0) {
                    $sheet->getStyle("L{$dataStartRow}:L{$highestRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0.000');
                    $sheet->getStyle("M{$dataStartRow}:M{$highestRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0.00');
                    $sheet->getStyle("O{$dataStartRow}:O{$highestRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0.00');
                }

                $totalRow = max($dataStartRow, $highestRow + 1);
                $sheet->mergeCells("A{$totalRow}:K{$totalRow}");
                $sheet->setCellValue("A{$totalRow}", 'TOTALES');
                $sheet->setCellValue("L{$totalRow}", (float) $this->rows->sum('peso'));
                $sheet->setCellValue("M{$totalRow}", (float) $this->rows->sum('precio'));
                $sheet->setCellValue("O{$totalRow}", (float) $this->rows->sum('precio'));

                $sheet->getStyle("A{$totalRow}:T{$totalRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E2F0D9'],
                    ],
                ]);

                $sheet->getStyle("L{$totalRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.000');
                $sheet->getStyle("M{$totalRow}:O{$totalRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');
            },
        ];
    }

    private function resolveEmpresaLabel(): string
    {
        $empresa = $this->filters['empresa'] ?? null;
        if (!$empresa) {
            return 'Todas las empresas';
        }

        $nombre = trim((string) ($empresa->nombre ?? ''));
        $sigla = trim((string) ($empresa->sigla ?? ''));

        return $sigla !== '' ? "{$nombre} ({$sigla})" : $nombre;
    }

    private function resolveDateRangeLabel(): string
    {
        $from = trim((string) ($this->filters['from'] ?? ''));
        $to = trim((string) ($this->filters['to'] ?? ''));

        if ($from !== '' && $to !== '') {
            return "{$from} AL {$to}";
        }
        if ($from !== '') {
            return 'DESDE ' . $from;
        }
        if ($to !== '') {
            return 'HASTA ' . $to;
        }

        return 'TODO EL HISTORIAL';
    }

    private function formatDate(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->format('d-m-y');
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d-m-y');
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d-m-y');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function formatTime(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->format('H:i');
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->format('H:i');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function resolveHeaderImagePath(): ?string
    {
        $path = public_path('images/encabezado_contratos.jpeg');

        return is_file($path) ? $path : null;
    }
}
