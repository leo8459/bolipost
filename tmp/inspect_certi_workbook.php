<?php

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$path = 'C:/Users/Ryzen/Desktop/Ventanilla Certificados DD (86).xlsx';
$book = IOFactory::load($path);

foreach ($book->getWorksheetIterator() as $sheet) {
    $styles = [];
    foreach (['A1', 'B1', 'I1', 'A2', 'B2', 'G2', 'I2', 'J1', 'L184'] as $coordinate) {
        $cellStyle = $sheet->getStyle($coordinate);
        $styles[$coordinate] = [
            'font' => [
                'name' => $cellStyle->getFont()->getName(),
                'size' => $cellStyle->getFont()->getSize(),
                'bold' => $cellStyle->getFont()->getBold(),
                'color' => $cellStyle->getFont()->getColor()->getARGB(),
            ],
            'fill' => $cellStyle->getFill()->getStartColor()->getARGB(),
            'number_format' => $cellStyle->getNumberFormat()->getFormatCode(),
            'horizontal' => $cellStyle->getAlignment()->getHorizontal(),
            'vertical' => $cellStyle->getAlignment()->getVertical(),
            'wrap' => $cellStyle->getAlignment()->getWrapText(),
            'border_bottom' => $cellStyle->getBorders()->getBottom()->getBorderStyle(),
        ];
    }
    $columns = [];
    foreach (range('A', 'L') as $column) {
        $columns[$column] = [
            'width' => $sheet->getColumnDimension($column)->getWidth(),
            'auto' => $sheet->getColumnDimension($column)->getAutoSize(),
            'hidden' => $sheet->getColumnDimension($column)->getVisible() === false,
        ];
    }
    echo json_encode([
        'sheet' => $sheet->getTitle(),
        'dimension' => $sheet->calculateWorksheetDimension(),
        'merged' => array_values($sheet->getMergeCells()),
        'freeze' => $sheet->getFreezePane(),
        'orientation' => $sheet->getPageSetup()->getOrientation(),
        'paper' => $sheet->getPageSetup()->getPaperSize(),
        'print_area' => $sheet->getPageSetup()->getPrintArea(),
        'auto_filter' => $sheet->getAutoFilter()->getRange(),
        'styles' => $styles,
        'columns' => $columns,
        'rows' => $sheet->rangeToArray($sheet->calculateWorksheetDimension(), null, true, true, true),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
