<?php

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$files = glob(__DIR__ . '/../outputs/certi_almacen_20260911/Ventanilla Certificados DD (*.xlsx');
usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));
$path = $files[0] ?? throw new RuntimeException('No se encontró el reporte generado.');
$book = IOFactory::load($path);
$book->getActiveSheet()->getColumnDimension('C')->setAutoSize(false)->setWidth(30);
(new Xlsx($book))->save($path);
echo $path . PHP_EOL;
