<?php

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

$files = glob(__DIR__ . '/../outputs/certi_almacen_20260911/Ventanilla Certificados DD (*.xlsx');
usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));
$path = $files[0] ?? throw new RuntimeException('No se encontró el reporte generado.');
$book = IOFactory::load($path);
$sheet = $book->getActiveSheet();
$highestRow = $sheet->getHighestDataRow();
$headers = $sheet->rangeToArray('A1:I1', null, true, true, false)[0];
$stateCounts = [];
$errors = [];

for ($row = 2; $row <= $highestRow; $row++) {
    $state = (string) $sheet->getCell("H{$row}")->getValue();
    $stateCounts[$state] = ($stateCounts[$state] ?? 0) + 1;
    for ($column = 1; $column <= 9; $column++) {
        $value = $sheet->getCellByColumnAndRow($column, $row)->getCalculatedValue();
        if (is_string($value) && preg_match('/^#(REF!|DIV\/0!|VALUE!|NAME\?|N\/A)$/', $value)) {
            $errors[] = Coordinate::stringFromColumnIndex($column) . $row . ':' . $value;
        }
    }
}

echo json_encode([
    'sheet' => $sheet->getTitle(),
    'dimension' => $sheet->calculateWorksheetDimension(),
    'data_rows' => $highestRow - 1,
    'headers' => $headers,
    'states' => $stateCounts,
    'filter' => $sheet->getAutoFilter()->getRange(),
    'freeze' => $sheet->getFreezePane(),
    'print_area' => $sheet->getPageSetup()->getPrintArea(),
    'formula_errors' => $errors,
    'first_row' => $sheet->rangeToArray('A2:I2', null, true, true, false)[0],
    'last_row' => $sheet->rangeToArray("A{$highestRow}:I{$highestRow}", null, true, true, false)[0],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
