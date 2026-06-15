<?php

namespace App\Exports;

use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class Cn33DespachoExport implements FromView, ShouldAutoSize, WithEvents, WithTitle
{
    public function __construct(
        private readonly Collection $rows,
        private readonly string $despacho,
        private readonly CarbonInterface $generatedAt,
        private readonly string $originCity,
        private readonly string $destinationCity,
        private readonly string $filterOrigin = '',
        private readonly string $filterDestination = ''
    ) {
    }

    public function view(): View
    {
        return view('paquetes_ems.reporte-regional-excel', [
            'paquetes' => $this->rows,
            'currentManifiesto' => $this->despacho,
            'generatedAt' => $this->generatedAt,
            'loggedInUserCity' => $this->originCity,
            'destinationCity' => $this->destinationCity,
            'selectedTransport' => 'N/A',
            'numeroVuelo' => '-',
            'filterOrigin' => $this->filterOrigin,
            'filterDestination' => $this->filterDestination,
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

                $sheet->mergeCells('A1:J1');
                $sheet->mergeCells('A2:J2');
                $sheet->mergeCells('A3:B3');
                $sheet->mergeCells('C3:D3');
                $sheet->mergeCells('E3:F3');
                $sheet->mergeCells('G3:J3');

                $sheet->getStyle('A1:J4')->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle('A1:J1')->applyFromArray([
                    'font' => ['size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '0F4C81'],
                    ],
                ]);

                $sheet->getStyle('A6:J6')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1F7A3A'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                $sheet->getStyle("A1:J{$highestRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '333333'],
                        ],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                $sheet->getStyle("A7:J{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G7:G{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("J7:J{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->freezePane('A7');
            },
        ];
    }
}
