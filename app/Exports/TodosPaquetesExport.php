<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class TodosPaquetesExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithCustomStartCell, WithEvents, WithHeadings, WithMapping, WithTitle
{
    public function __construct(
        private readonly Collection $rows,
        private readonly array $filters,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'TIPO',
            'CÓDIGO',
            'CN-33',
            'ORIGEN',
            'DESTINO',
            'EMPRESA',
            'REMITENTE',
            'DESTINATARIO',
            'TELÉFONO',
            'PESO',
            'PRECIO (BS)',
            'ESTADO',
            'JUSTIFICACIÓN',
            'ACTUALIZADO',
        ];
    }

    public function map($row): array
    {
        return [
            (string) ($row->tipo ?? ''),
            (string) ($row->codigo ?? ''),
            (string) ($row->cod_especial ?? ''),
            (string) ($row->origen ?? ''),
            (string) ($row->destino ?? ''),
            (string) ($row->empresa ?? ''),
            (string) ($row->remitente ?? ''),
            (string) ($row->destinatario ?? ''),
            (string) ($row->telefono ?? ''),
            $this->numericOrNull($row->peso ?? null),
            $this->numericOrNull($row->precio ?? null),
            (string) ($row->estado_nombre ?? 'SIN ESTADO'),
            (string) ($row->justificacion ?? ''),
            $this->excelDate($row->updated_at ?? null),
        ];
    }

    public function startCell(): string
    {
        return 'A5';
    }

    public function title(): string
    {
        return 'PAQUETES FILTRADOS';
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'I' => NumberFormat::FORMAT_TEXT,
            'J' => '#,##0.000',
            'K' => '#,##0.00',
            'N' => 'dd/mm/yyyy hh:mm',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(5, 5 + $this->rows->count());

                $sheet->mergeCells('A1:N1');
                $sheet->mergeCells('A2:N2');
                $sheet->mergeCells('A3:N3');
                $sheet->setCellValue('A1', 'REPORTE DE PAQUETES FILTRADOS');
                $sheet->setCellValue('A2', $this->filterSummary());
                $sheet->setCellValue('A3', sprintf(
                    'Generado: %s | Total de registros: %s',
                    $this->generatedAt()->format('d/m/Y H:i:s'),
                    number_format($this->rows->count()),
                ));

                $sheet->getStyle('A1:N1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '205AA5']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getStyle('A2:N3')->applyFromArray([
                    'font' => ['color' => ['rgb' => '334155']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EAF2FB']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'wrapText' => true],
                ]);
                $sheet->getStyle('A5:N5')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                $sheet->getRowDimension(1)->setRowHeight(26);
                $sheet->getRowDimension(2)->setRowHeight(24);
                $sheet->getRowDimension(5)->setRowHeight(24);
                $sheet->freezePane('A6');
                $sheet->setAutoFilter("A5:N{$lastRow}");

                if ($this->rows->isNotEmpty()) {
                    $sheet->getStyle("J6:K{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("A6:N{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                    $sheet->getStyle("M6:M{$lastRow}")->getAlignment()->setWrapText(true);
                }

                foreach (['F' => 18, 'G' => 22, 'H' => 22, 'M' => 30] as $column => $width) {
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

        return Date::dateTimeToExcel(Carbon::parse($value));
    }

    private function generatedAt(): Carbon
    {
        $value = $this->filters['generated_at'] ?? now();

        return $value instanceof Carbon ? $value : Carbon::parse($value);
    }

    private function filterSummary(): string
    {
        $search = trim((string) ($this->filters['search'] ?? ''));
        $minimum = $this->filters['peso_min'] ?? null;
        $maximum = $this->filters['peso_max'] ?? null;

        return implode(' | ', [
            'Búsqueda: '.($search !== '' ? $search : 'TODAS'),
            'Tipo: '.($this->filters['type_label'] ?? 'TODOS'),
            'Estado: '.($this->filters['estado_label'] ?? 'TODOS'),
            'Peso mínimo: '.($minimum !== null ? $minimum : 'SIN LÍMITE'),
            'Peso máximo: '.($maximum !== null ? $maximum : 'SIN LÍMITE'),
        ]);
    }
}
