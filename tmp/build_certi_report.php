<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$sourcePath = 'C:/Users/Ryzen/Desktop/Ventanilla Certificados DD (86).xlsx';
$outputDir = __DIR__ . '/../outputs/certi_almacen_20260911';

$rows = DB::table('paquetes_certi as p')
    ->leftJoin('estados as e', 'e.id', '=', 'p.fk_estado')
    ->leftJoin('ventanilla as v', 'v.id', '=', 'p.fk_ventanilla')
    ->whereRaw("UPPER(TRIM(COALESCE(p.cuidad, ''))) = 'LA PAZ'")
    ->whereRaw("UPPER(TRIM(COALESCE(v.nombre_ventanilla, p.ventanilla, ''))) = 'DD'")
    ->whereIn(DB::raw('UPPER(TRIM(e.nombre_estado))'), ['VENTANILLA', 'RECIBIDO'])
    ->orderBy('p.id')
    ->select([
        'p.codigo',
        'p.destinatario',
        'p.zona',
        'p.telefono',
        'p.cuidad',
        DB::raw("COALESCE(v.nombre_ventanilla, p.ventanilla, '') AS nombre_ventanilla"),
        'p.peso',
        DB::raw("COALESCE(e.nombre_estado, 'SIN ESTADO') AS nombre_estado"),
        'p.created_at',
    ])
    ->get();

$count = $rows->count();
$outputPath = $outputDir . '/Ventanilla Certificados DD (' . $count . ').xlsx';

$book = IOFactory::load($sourcePath);
$sheet = $book->getActiveSheet();
$sheet->setTitle('Ventanilla Certificados DD');

$oldLastRow = max(2, $sheet->getHighestRow());
$sheet->removeRow(2, $oldLastRow - 1);
$sheet->fromArray([
    ['CODIGO', 'DESTINATARIO', 'BANDEJA', 'TELEFONO', 'CUIDAD', 'VENTANILLA', 'PESO', 'ESTADO', 'FECHA'],
], null, 'A1', true);

$templateBodyStyle = $book->getActiveSheet()->getStyle('A2:I2');

$rowNumber = 2;
foreach ($rows as $row) {
    $sheet->setCellValueExplicit("A{$rowNumber}", (string) ($row->codigo ?? ''), DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("B{$rowNumber}", (string) ($row->destinatario ?? ''), DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("C{$rowNumber}", (string) ($row->zona ?? ''), DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("D{$rowNumber}", (string) ($row->telefono ?? ''), DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("E{$rowNumber}", (string) ($row->cuidad ?? ''), DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("F{$rowNumber}", (string) ($row->nombre_ventanilla ?? ''), DataType::TYPE_STRING);
    $sheet->setCellValue("G{$rowNumber}", $row->peso === null ? null : (float) $row->peso);
    $sheet->setCellValueExplicit("H{$rowNumber}", (string) ($row->nombre_estado ?? 'SIN ESTADO'), DataType::TYPE_STRING);
    if ($row->created_at !== null) {
        $sheet->setCellValue("I{$rowNumber}", Date::PHPToExcel(new DateTimeImmutable((string) $row->created_at)));
    }
    $rowNumber++;
}

$lastRow = max(2, $rowNumber - 1);
if ($count > 0) {
    $sheet->duplicateStyle($templateBodyStyle, "A2:I{$lastRow}");
    $sheet->getStyle("G2:G{$lastRow}")->getNumberFormat()->setFormatCode('0.###');
    $sheet->getStyle("I2:I{$lastRow}")->getNumberFormat()->setFormatCode('yyyy-mm-dd hh:mm');
}

$sheet->getAutoFilter()->setRange("A1:I{$lastRow}");
$sheet->freezePane('A2');
$columnWidths = [
    'A' => 20,
    'B' => 48,
    'C' => 30,
    'D' => 15,
    'E' => 14,
    'F' => 16,
    'G' => 11,
    'H' => 16,
    'I' => 21,
];
foreach ($columnWidths as $column => $width) {
    $sheet->getColumnDimension($column)->setAutoSize(false)->setWidth($width);
}
$sheet->setSelectedCell('A1');
$sheet->getPageSetup()->setPrintArea("A1:I{$lastRow}");
$sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
$sheet->getPageMargins()->setTop(0.35)->setBottom(0.35)->setLeft(0.25)->setRight(0.25);
$book->setActiveSheetIndex(0);

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

(new Xlsx($book))->save($outputPath);

echo json_encode([
    'output' => realpath($outputPath),
    'count' => $count,
    'first_code' => $rows->first()->codigo ?? null,
    'last_code' => $rows->last()->codigo ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
