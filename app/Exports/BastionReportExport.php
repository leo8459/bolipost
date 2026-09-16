<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class BastionReportExport extends DefaultValueBinder implements FromCollection, WithCustomValueBinder, WithEvents, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private readonly Collection $rows, private readonly string $sheetTitle = 'Reporte de Bastión') {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function headings(): array
    {
        return ['Hoja del Excel', 'Fila del Excel', 'Código', 'Fecha del Excel', 'Origen', 'Destino', 'Destinatario', 'Estados por los que pasó', 'Imagen de entrega'];
    }

    public function map($row): array
    {
        $history = collect($row['historial'])->map(fn ($event) => ($event['fecha'] ? Carbon::parse($event['fecha'])->format('d/m/Y H:i') : 'Sin fecha').' · '.$event['nombre'])->all();
        if ($row['estado_actual']) {
            $history[] = 'Estado actual: '.$row['estado_actual'];
        }
        if (! $row['encontrado']) {
            $history[] = 'Sin registro en el sistema';
        } elseif (empty($row['historial'])) {
            $history[] = 'Sin historial de eventos';
        }

        return [$row['hoja'], $row['fila'], $row['codigo'], $row['fecha'], $row['origen'], $row['destino'], $row['destinatario'], implode("\n", $history),
            $row['imagen'] ? route('bastiones.reporte.imagen', $row['codigo']) : ($row['entregado'] ? 'Entregado, sin imagen registrada' : 'Sin entrega registrada')];
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet->getDelegate();
            $last = $this->rows->count() + 1;
            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:I'.$last);
            $sheet->getStyle('A1:I1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            ]);
            $sheet->getStyle('A1:I'.$last)->getAlignment()->setWrapText(true)->setVertical('top');
            foreach (['A' => 18, 'B' => 12, 'C' => 23, 'D' => 18, 'E' => 20, 'F' => 20, 'G' => 28, 'H' => 65, 'I' => 42] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }
            foreach ($this->rows->values() as $index => $row) {
                if ($row['imagen']) {
                    $cell = $sheet->getCell('I'.($index + 2));
                    $cell->getHyperlink()->setUrl($cell->getValue());
                    $cell->getStyle()->getFont()->setUnderline('single')->getColor()->setRGB('0563C1');
                }
            }
        }];
    }
}
