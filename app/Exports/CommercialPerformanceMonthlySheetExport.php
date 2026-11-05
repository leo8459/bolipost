<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CommercialPerformanceMonthlySheetExport implements FromCollection, ShouldAutoSize, WithCharts, WithEvents, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private readonly array $rows)
    {
    }

    public function collection(): Collection
    {
        return collect($this->rows);
    }

    public function headings(): array
    {
        return ['Mes', 'Registros', 'Mes anterior', 'Variación (registros)', 'Variación (%)', 'Entregados', 'No entregados', 'Peso (kg)'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F5FAE']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(1, (int) $sheet->getHighestDataRow());

                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:H{$lastRow}");
                if ($lastRow > 1) {
                    $sheet->getStyle("E2:E{$lastRow}")->getNumberFormat()->setFormatCode('0.00"%"');
                    $sheet->getStyle("H2:H{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.000');
                }
            },
        ];
    }

    public function charts(): array
    {
        $lastRow = count($this->rows) + 1;
        if ($lastRow < 2) {
            return [];
        }

        $sheetReference = "'Evolución mensual'!";
        $categories = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $sheetReference . '$A$2:$A$' . $lastRow, null, count($this->rows));

        $volumeSeries = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            [0, 1],
            [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $sheetReference . '$B$1', null, 1),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $sheetReference . '$C$1', null, 1),
            ],
            [$categories],
            [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $sheetReference . '$B$2:$B$' . $lastRow, null, count($this->rows)),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $sheetReference . '$C$2:$C$' . $lastRow, null, count($this->rows)),
            ],
            DataSeries::DIRECTION_COL
        );
        $volumeChart = new Chart(
            'commercialMonthlyVolume',
            new Title('Registros del mes frente al mes anterior'),
            new Legend(Legend::POSITION_BOTTOM, null, false),
            new PlotArea(null, [$volumeSeries])
        );
        $volumeChart->setTopLeftPosition('J2');
        $volumeChart->setBottomRightPosition('T18');

        $growthSeries = new DataSeries(
            DataSeries::TYPE_LINECHART,
            DataSeries::GROUPING_STANDARD,
            [0],
            [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $sheetReference . '$E$1', null, 1)],
            [$categories],
            [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $sheetReference . '$E$2:$E$' . $lastRow, '0.00"%"', count($this->rows), [], DataSeries::STYLE_MARKER)],
            DataSeries::DIRECTION_COL,
            false,
            DataSeries::STYLE_LINEMARKER
        );
        $growthChart = new Chart(
            'commercialMonthlyGrowth',
            new Title('Variación porcentual frente al mes anterior'),
            new Legend(Legend::POSITION_BOTTOM, null, false),
            new PlotArea(null, [$growthSeries])
        );
        $growthChart->setTopLeftPosition('J20');
        $growthChart->setBottomRightPosition('T36');

        return [$volumeChart, $growthChart];
    }

    public function title(): string
    {
        return 'Evolución mensual';
    }
}
