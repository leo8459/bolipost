<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DailyClosingSheet extends DefaultValueBinder implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithStyles, WithTitle
{
    public function __construct(private readonly string $name, private readonly array $rows, private readonly int $headerRow = 1) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return $this->name;
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A'.($this->headerRow + 1));
        if ($this->headerRow === 1) {
            $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
            if ($sheet->getHighestColumn() === 'H') {
                $sheet->getColumnDimension('H')->setAutoSize(false)->setWidth(75);
                $sheet->getStyle('H')->getAlignment()->setWrapText(true);
            }
        }

        return [$this->headerRow => [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '164B82']],
        ]];
    }
}
